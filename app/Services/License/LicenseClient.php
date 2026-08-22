<?php

namespace App\Services\License;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de licencia de ESTE sistema (el que corre en casa del comprador).
 *
 * Valida contra el servidor central del proveedor antes de emitir a SUNAT
 * producción. Diseño tolerante a fallos:
 *   - Cachea la validación exitosa (no llama al servidor en cada emisión).
 *   - Si el servidor está caído, aplica un periodo de gracia desde la última
 *     validación buena, para no detener la facturación del cliente.
 *   - Una respuesta explícita de "no válida" (revocada/suspendida) bloquea
 *     de inmediato, aunque haya gracia disponible.
 */
class LicenseClient
{
    private const CACHE_OK = 'license:ok';          // validación positiva (TTL corto)

    private const CACHE_LAST_OK = 'license:last_ok'; // timestamp de la última validación buena

    public function __construct(
        private readonly MachineId $machineId,
    ) {}

    /**
     * Comprueba si esta instalación puede operar en producción.
     */
    public function check(): LicenseCheck
    {
        // El interruptor de apagado SOLO surte efecto en desarrollo local.
        // En un servidor real (production/staging) se ignora: poner
        // LICENSE_ENABLED=false en el .env del cliente no desactiva el candado.
        if (! config('license.enabled') && app()->environment('local')) {
            return LicenseCheck::allow('deshabilitada', 'Validación de licencia deshabilitada (entorno local).');
        }

        $key = (string) config('license.key');
        $secret = (string) config('license.secret');

        if ($key === '' || $secret === '') {
            return LicenseCheck::deny(
                'licencia_no_configurada',
                'Falta configurar LICENSE_KEY y LICENSE_SECRET para emitir en producción.',
            );
        }

        // Validación reciente en caché: evita golpear el servidor en cada emisión.
        if (Cache::get(self::CACHE_OK) === true) {
            return LicenseCheck::allow();
        }

        return $this->validarContraServidor();
    }

    /**
     * Activa esta instalación en el servidor (registra el dominio). La usa el
     * comando license:activate al instalar.
     *
     * @return array{ok: bool, code: string, message: string, http: int|null}
     */
    public function activate(): array
    {
        return $this->llamar('activar');
    }

    /**
     * Valida contra el servidor y decide con caché + periodo de gracia.
     */
    private function validarContraServidor(): LicenseCheck
    {
        $resultado = $this->llamar('validar');

        // Respuesta clara del servidor.
        if ($resultado['http'] !== null) {
            if ($resultado['ok']) {
                Cache::put(self::CACHE_OK, true, (int) config('license.cache_ttl'));
                Cache::forever(self::CACHE_LAST_OK, now()->timestamp);

                return LicenseCheck::allow('ok', $resultado['message']);
            }

            // El servidor respondió que NO es válida (revocada/suspendida/etc.):
            // se bloquea de inmediato, sin gracia.
            Cache::forget(self::CACHE_OK);

            return LicenseCheck::deny($resultado['code'], $resultado['message']);
        }

        // No hubo respuesta (servidor caído / sin red): aplica gracia.
        return $this->resolverGracia();
    }

    /**
     * Permite operar si estamos dentro del periodo de gracia desde la última
     * validación buena; si no, bloquea.
     */
    private function resolverGracia(): LicenseCheck
    {
        $lastOk = (int) Cache::get(self::CACHE_LAST_OK, 0);
        $graceDays = (int) config('license.grace_days');

        if ($lastOk > 0 && now()->timestamp - $lastOk <= $graceDays * 86400) {
            return LicenseCheck::allow(
                'gracia_offline',
                'Servidor de licencias no disponible; operando en periodo de gracia.',
                offlineGrace: true,
            );
        }

        return LicenseCheck::deny(
            'servidor_licencia_inalcanzable',
            'No se pudo validar la licencia y venció el periodo de gracia. Revisa la conexión con el servidor de licencias.',
        );
    }

    /**
     * Llama a un endpoint del servidor de licencias.
     *
     * @return array{ok: bool, code: string, message: string, http: int|null}
     *                                                                        http = null cuando no hubo respuesta (caída/red).
     */
    private function llamar(string $accion): array
    {
        $url = config('license.server_url').'/api/v1/licencias/'.$accion;

        try {
            $respuesta = Http::timeout((int) config('license.timeout'))
                ->withHeaders([
                    'X-License-Key' => (string) config('license.key'),
                    'X-License-Secret' => (string) config('license.secret'),
                    'Accept' => 'application/json',
                ])
                ->post($url, [
                    'machine_id' => $this->machineId->get(),
                    'domain' => $this->dominio(),
                    'app_version' => (string) config('license.app_version'),
                    'fingerprint' => BuildInfo::fingerprint(),
                ]);

            $datos = $respuesta->json() ?? [];

            return [
                'ok' => (bool) ($datos['valida'] ?? false),
                'code' => (string) ($datos['codigo'] ?? 'respuesta_invalida'),
                'message' => (string) ($datos['mensaje'] ?? 'Respuesta inesperada del servidor de licencias.'),
                'http' => $respuesta->status(),
            ];
        } catch (ConnectionException $e) {
            Log::warning('Licencia: servidor inalcanzable', ['error' => $e->getMessage()]);

            return ['ok' => false, 'code' => 'sin_conexion', 'message' => $e->getMessage(), 'http' => null];
        }
    }

    /**
     * Dominio/host informativo de esta instalación (solo dato, no identidad).
     */
    private function dominio(): string
    {
        $valor = (string) config('license.domain');

        return parse_url($valor, PHP_URL_HOST) ?: $valor;
    }
}

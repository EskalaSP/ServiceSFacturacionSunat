<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Revisa el certificado de cada tenant y avisa cuál no puede firmar.
 *
 * Existe porque un certificado roto no da la cara: la carga se reporta como
 * exitosa, el tenant emite comprobantes con normalidad, y el problema recién
 * aparece cuando SUNAT nunca los recibe. Para cuando alguien lo nota, hay
 * boletas dentro del plazo de 7 días a punto de vencer.
 */
class VerificarCertificadosCommand extends Command
{
    protected $signature = 'certificados:verificar {--tenant= : revisar solo este ID}';

    protected $description = 'Verifica que el certificado de cada tenant pueda firmar comprobantes.';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q) => $q->whereKey((int) $this->option('tenant')))
            ->orderBy('id')
            ->get();

        $filas = [];
        $rotos = 0;

        foreach ($tenants as $tenant) {
            [$estado, $detalle] = $this->revisar($tenant);

            if ($estado !== 'OK') {
                $rotos++;
            }

            $filas[] = [
                $tenant->id,
                mb_strimwidth((string) $tenant->razon_social, 0, 34, '…'),
                $tenant->environment,
                $estado,
                $detalle,
            ];
        }

        $this->table(['ID', 'Razón social', 'Entorno', 'Estado', 'Detalle'], $filas);

        if ($rotos > 0) {
            $this->error("{$rotos} tenant(s) no pueden firmar comprobantes.");

            return self::FAILURE;
        }

        $this->info('Todos los certificados pueden firmar.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function revisar(Tenant $tenant): array
    {
        $contenido = $tenant->getCertificateContent();

        if ($contenido === null) {
            return ['SIN CERT', 'No hay archivo cargado'];
        }

        // PKCS#12 crudo: se prueba con la contraseña guardada.
        if (! str_starts_with(ltrim($contenido), '-----')) {
            $certs = [];
            if (! @openssl_pkcs12_read($contenido, $certs, (string) $tenant->certificate_password)) {
                return ['ROTO', 'PFX ilegible — contraseña incorrecta o archivo dañado'];
            }

            $contenido = ($certs['pkey'] ?? '').($certs['cert'] ?? '');
        }

        if (@openssl_pkey_get_private($contenido) === false) {
            return ['ROTO', 'Sin clave privada — subieron el .cer en vez del .pfx'];
        }

        $cert = @openssl_x509_read($contenido);

        if ($cert === false) {
            return ['ROTO', 'El certificado no se puede leer'];
        }

        $info = openssl_x509_parse($cert);
        $vence = $info['validTo_time_t'] ?? null;

        if ($vence !== null && $vence < time()) {
            return ['VENCIDO', 'Venció el '.date('Y-m-d', $vence)];
        }

        // Un certificado que vence en menos de un mes conviene renovarlo antes
        // de que corte la facturación de un día para otro.
        if ($vence !== null && $vence < strtotime('+30 days')) {
            return ['POR VENCER', 'Vence el '.date('Y-m-d', $vence)];
        }

        return ['OK', $vence ? 'Vence el '.date('Y-m-d', $vence) : 'Válido'];
    }
}

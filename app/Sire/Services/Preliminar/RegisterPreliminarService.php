<?php

namespace App\Sire\Services\Preliminar;

use App\Models\Tenant;
use App\Sire\Services\Http\SireHttpClient;
use App\Sire\Support\PeriodoTributario;

/**
 * Servicio 5.4 — Registrar preliminar del RCE.
 *
 * POST /v1/contribuyente/migeigv/libros/rce/preliminar/web/registroslibros/{perTributario}/registrapreliminares
 *
 * Según manual v22: Devuelve respuesta T/F.
 * (A diferencia de 5.2/5.3/5.34, este servicio es síncrono — no genera ticket.)
 *
 * Errores posibles:
 *   1008 - El registro electrónico ya se encuentra en el módulo preliminar.
 *   1009 - El registro electrónico ya ha sido generado.
 */
class RegisterPreliminarService
{
    public function __construct(
        private readonly SireHttpClient $http,
    ) {}

    /**
     * @return array{exitoso: bool, respuesta: array}
     */
    public function registrar(Tenant $tenant, PeriodoTributario $periodo): array
    {
        $path = sprintf(
            'contribuyente/migeigv/libros/rce/preliminar/web/registroslibros/%s/registrapreliminares',
            $periodo->toString(),
        );

        $response = $this->http->post($tenant, $path);

        // SUNAT devuelve respuesta T/F — normalizamos
        $exitoso = ($response['respuesta'] ?? $response['resultado'] ?? null) === 'T'
            || (bool) ($response['success'] ?? false)
            || empty($response['errors']); // si no hay errors explícitos, lo tomamos como OK

        return [
            'exitoso' => $exitoso,
            'respuesta' => $response,
        ];
    }
}

<?php

namespace App\Sire\Services\Resumen;

use App\Models\Tenant;
use App\Sire\Enums\CodLibro;
use App\Sire\Enums\CodTipoArchivo;
use App\Sire\Enums\CodTipoResumen;
use App\Sire\Services\Http\SireHttpClient;
use App\Sire\Support\PeriodoTributario;
use Psr\Http\Message\ResponseInterface;

/**
 * Servicio 5.35 — Descargar resumen.
 *
 * GET /v1/contribuyente/migeigv/libros/rvierce/resumen/web/resumencomprobantes
 *     /{perTributario}/{codTipoResumen}/{codTipoArchivo}/exporta?codLibro={codLibro}
 *
 * Respuesta: buffer binario (archivo directo, NO es async).
 */
class ResumenService
{
    public function __construct(
        private readonly SireHttpClient $http,
    ) {}

    public function descargar(
        Tenant $tenant,
        PeriodoTributario $periodo,
        CodTipoResumen $tipo = CodTipoResumen::PROPUESTA,
        CodTipoArchivo $formato = CodTipoArchivo::TXT,
        CodLibro $libro = CodLibro::RCE,
    ): ResponseInterface {
        $path = sprintf(
            'contribuyente/migeigv/libros/rvierce/resumen/web/resumencomprobantes/%s/%s/%s/exporta',
            $periodo->toString(),
            $tipo->value,
            $formato->value,
        );

        return $this->http->getRaw($tenant, $path, [
            'codLibro' => $libro->value,
        ]);
    }
}

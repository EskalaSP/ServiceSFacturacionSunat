<?php

namespace App\Sire\Services\Propuesta;

use App\Models\Tenant;
use App\Sire\Enums\CodProceso;
use App\Sire\Enums\CodTipoArchivo;
use App\Sire\Jobs\PollTicketJob;
use App\Sire\Models\SireTicket;
use App\Sire\Services\Http\SireHttpClient;
use App\Sire\Services\Tickets\TicketService;
use App\Sire\Support\PeriodoTributario;

/**
 * Servicio 5.34 — Descargar propuesta RCE.
 *
 * GET /v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/{perTributario}/exportacioncomprobantepropuesta
 *     ?codTipoArchivo=&codOrigenEnvio=&fecEmisionIni=&fecEmisionFin=&codTipoCDP=&...
 *
 * Respuesta: { numTicket: "AAAA99999999" }
 *
 * Después del ticket TERMINADO, el DownloadTicketFileJob baja el ZIP.
 * Luego ProcessPropuestaJob parsea el TXT → sire_comprobantes.
 */
class DownloadPropuestaService
{
    public function __construct(
        private readonly SireHttpClient $http,
        private readonly TicketService $tickets,
    ) {}

    /**
     * Solicita a SUNAT la generación del archivo con la propuesta.
     * Devuelve el SireTicket recién creado.
     */
    public function solicitar(
        Tenant $tenant,
        PeriodoTributario $periodo,
        CodTipoArchivo $formato = CodTipoArchivo::TXT,
        array $filtros = [],
    ): SireTicket {
        $path = sprintf(
            'contribuyente/migeigv/libros/rce/propuesta/web/propuesta/%s/exportacioncomprobantepropuesta',
            $periodo->toString(),
        );

        $query = array_filter([
            'codTipoArchivo'     => $formato->value,
            'codOrigenEnvio'     => config('sire.cod_origen_envio'),
            'fecEmisionIni'      => $filtros['fec_emision_ini'] ?? null,
            'fecEmisionFin'      => $filtros['fec_emision_fin'] ?? null,
            'codTipoCDP'         => $filtros['cod_tipo_cdp'] ?? null,
            'numSerieCDP'        => $filtros['num_serie_cdp'] ?? null,
            'numCDP'             => $filtros['num_cdp'] ?? null,
            'codInconsistencia'  => $filtros['cod_inconsistencia'] ?? null,
            'numDocAdquiriente'  => $filtros['num_doc_adquiriente'] ?? null,
            'mtoDesde'           => $filtros['mto_desde'] ?? null,
            'mtoHasta'           => $filtros['mto_hasta'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->http->get($tenant, $path, $query);

        $numTicket = $response['numTicket'] ?? null;
        if (! $numTicket) {
            throw new \RuntimeException('SUNAT no devolvió numTicket al solicitar la propuesta.');
        }

        $ticket = $this->tickets->register(
            tenant: $tenant,
            numTicket: $numTicket,
            perTributario: $periodo->toString(),
            codProceso: CodProceso::GENERAR_EXPORT_PROPUESTA->value,
            requestPayload: $query,
        );

        PollTicketJob::dispatch($ticket->id);

        return $ticket;
    }
}

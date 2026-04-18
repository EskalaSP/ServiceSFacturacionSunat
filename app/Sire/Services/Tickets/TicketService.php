<?php

namespace App\Sire\Services\Tickets;

use App\Models\Tenant;
use App\Sire\Enums\EstadoTicket;
use App\Sire\Models\SireTicket;
use App\Sire\Services\Http\SireHttpClient;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Servicios 5.31 (consultar estado ticket) y 5.32 (descargar archivo) del manual v22.
 *
 * 5.31 — GET /rvierce/gestionprocesosmasivos/web/masivo/consultaestadotickets
 *        ?perIni={yyyymm}&perFin={yyyymm}&page={n}&perPage={n}&numTicket={AAAA99999999}
 *
 * 5.32 — GET /rvierce/gestionprocesosmasivos/web/masivo/archivoreporte
 *        ?nomArchivoReporte={nombre}&codTipoArchivoReporte={cod}
 */
class TicketService
{
    private const PATH_ESTADO  = 'contribuyente/migeigv/libros/rvierce/gestionprocesosmasivos/web/masivo/consultaestadotickets';
    private const PATH_ARCHIVO = 'contribuyente/migeigv/libros/rvierce/gestionprocesosmasivos/web/masivo/archivoreporte';

    public function __construct(
        private readonly SireHttpClient $http,
    ) {}

    /**
     * Registra un ticket recién creado por SUNAT en la BD.
     * Es idempotente por la unique (tenant_id, num_ticket).
     */
    public function register(
        Tenant $tenant,
        string $numTicket,
        string $perTributario,
        string $codProceso,
        ?string $nomArchivoImportacion = null,
        ?array $requestPayload = null,
    ): SireTicket {
        return SireTicket::updateOrCreate(
            [
                'tenant_id'  => $tenant->id,
                'num_ticket' => $numTicket,
            ],
            [
                'per_tributario'          => $perTributario,
                'cod_proceso'             => $codProceso,
                'cod_estado_proceso'      => EstadoTicket::PENDIENTE->value,
                'des_estado_proceso'      => EstadoTicket::PENDIENTE->label(),
                'nom_archivo_importacion' => $nomArchivoImportacion,
                'sunat_request_payload'   => $requestPayload,
            ],
        );
    }

    /**
     * Consulta el estado del ticket en SUNAT (5.31) y actualiza la fila local.
     * No dispara jobs: solo devuelve el ticket actualizado.
     */
    public function fetchStatus(SireTicket $ticket): SireTicket
    {
        $response = $this->http->get($ticket->tenant, self::PATH_ESTADO, [
            'perIni'    => $ticket->per_tributario,
            'perFin'    => $ticket->per_tributario,
            'page'      => 1,
            'perPage'   => 20,
            'numTicket' => $ticket->num_ticket,
        ]);

        $registro = $this->findTicketRecord($response, $ticket->num_ticket);

        $ticket->poll_attempts = $ticket->poll_attempts + 1;
        $ticket->last_polled_at = now();
        $ticket->sunat_last_response = $response;

        if ($registro === null) {
            $ticket->save();
            return $ticket;
        }

        $ticket->cod_estado_proceso = $registro['codEstadoProceso'] ?? $ticket->cod_estado_proceso;
        $ticket->des_estado_proceso = $registro['desEstadoProceso'] ?? $ticket->des_estado_proceso;
        $ticket->des_proceso        = $registro['desProceso'] ?? $ticket->des_proceso;

        $ticket->cnt_filas_validadas = $this->firstInDetalle($registro, 'cntFilasvalidada') ?? $ticket->cnt_filas_validadas;
        $ticket->cnt_cp_informados   = $this->firstInDetalle($registro, 'cntCPInformados') ?? $ticket->cnt_cp_informados;
        $ticket->cnt_cp_error        = $this->firstInDetalle($registro, 'cntCPError') ?? $ticket->cnt_cp_error;

        $archivoReporte = $registro['archivoReporte'][0] ?? null;
        if ($archivoReporte) {
            $ticket->nom_archivo_reporte      = $archivoReporte['nomArchivoReporte'] ?? $ticket->nom_archivo_reporte;
            $ticket->cod_tipo_archivo_reporte = $archivoReporte['codTipoAchivoReporte']
                ?? $archivoReporte['codTipoArchivoReporte']
                ?? $ticket->cod_tipo_archivo_reporte;
        }

        if ($ticket->estadoEnum()?->isFinal()) {
            $ticket->finished_at = now();
        }

        $ticket->save();

        return $ticket;
    }

    /**
     * Descarga el ZIP generado (5.32) y lo guarda localmente.
     * Requiere que el ticket ya tenga `nom_archivo_reporte` y `cod_tipo_archivo_reporte`.
     * Devuelve la ruta local del archivo.
     */
    public function downloadFile(SireTicket $ticket): string
    {
        if (empty($ticket->nom_archivo_reporte)) {
            throw new \RuntimeException(
                "El ticket {$ticket->num_ticket} no tiene nom_archivo_reporte. ¿Ya fue poolled?"
            );
        }

        $response = $this->http->getRaw($ticket->tenant, self::PATH_ARCHIVO, [
            'nomArchivoReporte'     => $ticket->nom_archivo_reporte,
            'codTipoArchivoReporte' => $ticket->cod_tipo_archivo_reporte, // puede ser null (manual lo permite)
        ]);

        $disk = Storage::disk(config('sire.storage.disk'));
        $relativePath = sprintf(
            '%s/%d/%s/%s',
            config('sire.storage.base_path'),
            $ticket->tenant_id,
            $ticket->per_tributario,
            $ticket->nom_archivo_reporte,
        );

        $disk->put($relativePath, Utils::copyToString($response->getBody()));

        $ticket->archivo_local_path = $relativePath;
        $ticket->save();

        Log::channel('stack')->info('[SIRE] Archivo descargado', [
            'tenant_id' => $ticket->tenant_id,
            'ticket'    => $ticket->num_ticket,
            'path'      => $relativePath,
            'size'      => $disk->size($relativePath),
        ]);

        return $relativePath;
    }

    /**
     * Busca el ticket específico dentro de la respuesta paginada de 5.31.
     * La respuesta trae `registros[]` — buscamos por numTicket.
     */
    private function findTicketRecord(array $response, string $numTicket): ?array
    {
        $registros = $response['registros'] ?? [];

        foreach ($registros as $registro) {
            if (($registro['numTicket'] ?? null) === $numTicket) {
                return $registro;
            }
        }

        // Algunos endpoints devuelven un solo registro en vez de paginado
        if (isset($response['numTicket']) && $response['numTicket'] === $numTicket) {
            return $response;
        }

        return null;
    }

    private function firstInDetalle(array $registro, string $key): mixed
    {
        $detalle = $registro['detalleTicket'] ?? [];

        foreach ($detalle as $d) {
            if (isset($d[$key])) {
                return $d[$key];
            }
        }

        return $registro[$key] ?? null;
    }
}

<?php

namespace App\Sire\Services\Ajustes;

use App\Models\Tenant;
use App\Sire\Enums\CodLibro;
use App\Sire\Enums\CodTipoArchivo;
use App\Sire\Enums\VariantAjuste;
use App\Sire\Jobs\PollTicketJob;
use App\Sire\Models\SireTicket;
use App\Sire\Models\SireUploadFile;
use App\Sire\Services\Http\SireHttpClient;
use App\Sire\Services\Tickets\TicketService;
use App\Sire\Services\Upload\TusUploader;
use App\Sire\Services\Upload\ZipBuilder;
use App\Sire\Support\PeriodoTributario;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio unificado para Ajustes Posteriores RCE.
 *
 * Cubre las 4 variantes (periodo actual / no domiciliados / periodos anteriores / ...)
 * y las 4 acciones (cargar / enviar / descargar / eliminar) que el manual SIRE v22
 * describe en las secciones 5.18-5.29 y 5.45-5.48.
 *
 * Rutas encapsuladas según `VariantAjuste`:
 *   - cargar()    — servicios 5.18 / 5.21 / 5.24 / 5.27 (TUS upload, devuelve ticket)
 *   - enviar()    — servicios 5.19 / 5.22 / 5.25 / 5.28 (POST, devuelve ticket)
 *   - descargar() — servicios 5.45 / 5.46 / 5.47 / 5.48 (GET, devuelve ticket)
 *   - eliminar()  — servicios 5.20 / 5.23 / 5.26 / 5.29 (POST con detalle)
 */
class AjustesPosterioresService
{
    public function __construct(
        private readonly TusUploader $tus,
        private readonly ZipBuilder $zipBuilder,
        private readonly SireHttpClient $http,
        private readonly TicketService $tickets,
    ) {}

    // ==================================================================
    // 1. CARGAR (5.18 / 5.21 / 5.24 / 5.27) — TUS
    // ==================================================================
    public function cargar(
        Tenant $tenant,
        PeriodoTributario $periodo,
        VariantAjuste $variant,
        string $txtContent,
        int $secuencia = 1,
    ): SireTicket {
        $codProceso = $variant->codProceso();

        $built = $this->zipBuilder->build(
            ruc: $tenant->ruc,
            perTributario: $periodo->toString(),
            codLibro: CodLibro::RCE->value,
            codProceso: $codProceso->value,
            txtContent: $txtContent,
            destDir: storage_path('app/sire-temp/' . $tenant->id),
            secuencia: $secuencia,
        );

        $metadata = [
            'filename'              => $built->zipName,
            'filetype'              => 'application/zip',
            'numRuc'                => $tenant->ruc,
            'perTributario'         => $periodo->toString(),
            'codOrigenEnvio'        => config('sire.cod_origen_envio'),
            'codProceso'            => $codProceso->value,
            'codTipoCorrelativo'    => config('sire.cod_tipo_correlativo'),
            'nomArchivoImportacion' => $built->zipName,
            'codLibro'              => CodLibro::RCE->value,
        ];

        $url = sprintf(
            '%s/contribuyente/migeigv/libros/rvierce/%s',
            rtrim(config('sire.hosts.api'), '/'),
            $variant->uploadSegment(),
        );

        $response = $this->tus->upload($tenant, $url, $built->zipPath, $metadata);

        $numTicket = $response['numTicket'] ?? null;
        if (! $numTicket) {
            throw new \RuntimeException('SUNAT no devolvió numTicket tras el upload de ajustes posteriores.');
        }

        $ticket = $this->tickets->register(
            tenant: $tenant,
            numTicket: $numTicket,
            perTributario: $periodo->toString(),
            codProceso: $codProceso->value,
            nomArchivoImportacion: $built->zipName,
            requestPayload: [
                'variant'  => $variant->value,
                'zip_name' => $built->zipName,
                'zip_size' => $built->size,
            ],
        );

        SireUploadFile::create([
            'tenant_id'      => $tenant->id,
            'sire_ticket_id' => $ticket->id,
            'per_tributario' => $periodo->toString(),
            'cod_proceso'    => $codProceso->value,
            'nom_archivo'    => $built->zipName,
            'local_path'     => $this->guardarCopia($tenant, $periodo, $built),
            'size_bytes'     => $built->size,
            'sha256'         => $built->sha256,
            'uploaded_at'    => now(),
        ]);

        PollTicketJob::dispatch($ticket->id);

        return $ticket;
    }

    // ==================================================================
    // 2. ENVIAR (5.19 / 5.22 / 5.25 / 5.28) — POST, devuelve ticket
    // ==================================================================
    public function enviar(
        Tenant $tenant,
        PeriodoTributario $periodo,
        VariantAjuste $variant,
        string $numTicketCarga,
    ): SireTicket {
        // URL: /{operationSegment}/{perTributario}/{codOrigenEnvio}/registrarajustesposterioresrc...
        $path = sprintf(
            'contribuyente/migeigv/libros/%s/%s/%s/registrarajustesposterioresrc',
            $variant->operationSegment(),
            $periodo->toString(),
            config('sire.cod_origen_envio'),
        );

        $body = [
            'perTributario'      => $periodo->toString(),
            'numAjustePosterior' => $numTicketCarga,
            'codLibro'           => CodLibro::RCE->value,
            'numTicket'          => $numTicketCarga,
        ];

        $response = $this->http->post($tenant, $path, ['json' => $body]);

        $numTicket = $response['numTicket'] ?? $response['data']['numTicket'] ?? null;
        if (! $numTicket) {
            throw new \RuntimeException('SUNAT no devolvió numTicket tras enviar ajustes posteriores.');
        }

        $ticket = $this->tickets->register(
            tenant: $tenant,
            numTicket: $numTicket,
            perTributario: $periodo->toString(),
            codProceso: $variant->codProceso()->value,
            requestPayload: array_merge($body, ['variant' => $variant->value, 'action' => 'enviar']),
        );

        PollTicketJob::dispatch($ticket->id);

        return $ticket;
    }

    // ==================================================================
    // 3. DESCARGAR (5.45 / 5.46 / 5.47 / 5.48) — GET, devuelve ticket
    // ==================================================================
    public function descargar(
        Tenant $tenant,
        PeriodoTributario $periodo,
        VariantAjuste $variant,
        CodTipoArchivo $formato = CodTipoArchivo::TXT,
    ): SireTicket {
        $path = sprintf(
            'contribuyente/migeigv/libros/%s/%s/%s',
            $variant->operationSegment(),
            $periodo->toString(),
            $variant->exportSegment(),
        );

        $query = [
            'codTipoArchivo' => $formato->value,
            'codOrigenEnvio' => config('sire.cod_origen_envio'),
        ];

        $response = $this->http->get($tenant, $path, $query);

        $numTicket = $response['numTicket'] ?? null;
        if (! $numTicket) {
            throw new \RuntimeException('SUNAT no devolvió numTicket tras solicitar descarga de ajustes.');
        }

        $ticket = $this->tickets->register(
            tenant: $tenant,
            numTicket: $numTicket,
            perTributario: $periodo->toString(),
            codProceso: $variant->codProceso()->value,
            requestPayload: array_merge($query, ['variant' => $variant->value, 'action' => 'descargar']),
        );

        PollTicketJob::dispatch($ticket->id);

        return $ticket;
    }

    // ==================================================================
    // 4. ELIMINAR (5.20 / 5.23 / 5.26 / 5.29) — POST con body detalle
    // ==================================================================

    /**
     * @param array<int, array{
     *   cod_tipo_cdp: string,
     *   num_serie_cdp: string,
     *   num_cdp: string,
     *   cod_car: string,
     *   id?: string
     * }> $detalles
     */
    public function eliminar(
        Tenant $tenant,
        PeriodoTributario $periodo,
        VariantAjuste $variant,
        string $codAjustePosterior,
        array $detalles,
    ): array {
        $path = sprintf(
            'contribuyente/migeigv/libros/%s/%s/%s/eliminarcomprobanteaprc',
            $variant->operationSegment(),
            $periodo->toString(),
            $variant->indTipoAjustePosterior(),
        );

        $body = [
            'codAjustePosterior' => $codAjustePosterior,
            'detalleAjustes' => array_map(fn ($d) => [
                'codTipoCDP'  => $d['cod_tipo_cdp'],
                'numSerieCDP' => $d['num_serie_cdp'],
                'numCDP'      => $d['num_cdp'],
                'codCar'      => $d['cod_car'],
                'Id'          => $d['id'] ?? uniqid('cp_', true),
            ], $detalles),
        ];

        return $this->http->post($tenant, $path, ['json' => $body]);
    }

    // ==================================================================
    // Utilidades
    // ==================================================================

    private function guardarCopia(Tenant $tenant, PeriodoTributario $periodo, object $built): string
    {
        $disk = Storage::disk(config('sire.storage.disk'));

        $path = sprintf(
            '%s/%d/%s/uploads/%s',
            config('sire.storage.base_path'),
            $tenant->id,
            $periodo->toString(),
            $built->zipName,
        );

        $disk->put($path, file_get_contents($built->zipPath));

        return $path;
    }
}

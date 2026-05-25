<?php

namespace App\Sire\Services\Upload;

use App\Models\Tenant;
use App\Sire\Enums\CodLibro;
use App\Sire\Enums\CodProceso;
use App\Sire\Jobs\PollTicketJob;
use App\Sire\Models\SireTicket;
use App\Sire\Models\SireUploadFile;
use App\Sire\Services\Tickets\TicketService;
use App\Sire\Support\PeriodoTributario;
use Illuminate\Support\Facades\Storage;

/**
 * Lógica común de los 3 servicios de upload SIRE (5.3 / 5.5 / 5.6).
 *
 * Cada servicio concreto solo define:
 *   - La URL del endpoint
 *   - El CodProceso a usar
 *   - El CodLibro (RCE por default)
 *
 * El resto (armar ZIP, subirlo por TUS, registrar ticket, dispatch poll)
 * queda aquí para evitar duplicación.
 */
abstract class BaseUploadService
{
    public function __construct(
        protected readonly TusUploader $tus,
        protected readonly ZipBuilder $zipBuilder,
        protected readonly TicketService $tickets,
    ) {}

    abstract protected function endpointUrl(): string;
    abstract protected function codProceso(): CodProceso;

    protected function codLibro(): CodLibro
    {
        return CodLibro::RCE;
    }

    /**
     * Sube un TXT (se empaqueta automáticamente en ZIP con nombre SUNAT).
     */
    public function uploadTxt(
        Tenant $tenant,
        PeriodoTributario $periodo,
        string $txtContent,
        int $secuencia = 1,
    ): SireTicket {
        $built = $this->zipBuilder->build(
            ruc: $tenant->ruc,
            perTributario: $periodo->toString(),
            codLibro: $this->codLibro()->value,
            codProceso: $this->codProceso()->value,
            txtContent: $txtContent,
            destDir: storage_path('app/sire-temp/' . $tenant->id),
            secuencia: $secuencia,
        );

        return $this->uploadZipFile($tenant, $periodo, $built);
    }

    /**
     * Sube un ZIP ya armado. El archivo debe respetar la convención de nombre SUNAT.
     */
    public function uploadZipFile(
        Tenant $tenant,
        PeriodoTributario $periodo,
        object $built,
    ): SireTicket {
        $this->validarArchivo($built);

        $metadata = [
            'filename'              => $built->zipName,
            'filetype'              => 'application/zip',
            'numRuc'                => $tenant->ruc,
            'perTributario'         => $periodo->toString(),
            'codOrigenEnvio'        => config('sire.cod_origen_envio'),
            'codProceso'            => $this->codProceso()->value,
            'codTipoCorrelativo'    => config('sire.cod_tipo_correlativo'),
            'nomArchivoImportacion' => $built->zipName,
            'codLibro'              => $this->codLibro()->value,
        ];

        $response = $this->tus->upload(
            tenant: $tenant,
            url: $this->endpointUrl(),
            filePath: $built->zipPath,
            metadata: $metadata,
        );

        $numTicket = $response['numTicket'] ?? null;
        if (! $numTicket) {
            throw new \RuntimeException('SUNAT no devolvió numTicket tras el upload TUS.');
        }

        $ticket = $this->tickets->register(
            tenant: $tenant,
            numTicket: $numTicket,
            perTributario: $periodo->toString(),
            codProceso: $this->codProceso()->value,
            nomArchivoImportacion: $built->zipName,
            requestPayload: [
                'endpoint'    => $this->endpointUrl(),
                'zip_name'    => $built->zipName,
                'zip_size'    => $built->size,
                'zip_sha256'  => $built->sha256,
            ],
        );

        // Guarda historial de la subida
        SireUploadFile::create([
            'tenant_id'      => $tenant->id,
            'sire_ticket_id' => $ticket->id,
            'per_tributario' => $periodo->toString(),
            'cod_proceso'    => $this->codProceso()->value,
            'nom_archivo'    => $built->zipName,
            'local_path'     => $this->guardarCopia($tenant, $periodo, $built),
            'size_bytes'     => $built->size,
            'sha256'         => $built->sha256,
            'uploaded_at'    => now(),
        ]);

        PollTicketJob::dispatch($ticket->id);

        return $ticket;
    }

    private function validarArchivo(object $built): void
    {
        $min = config('sire.upload_limits.min_size_bytes');
        $max = config('sire.upload_limits.max_size_bytes');

        if ($built->size <= $min) {
            throw new \RuntimeException('El archivo está vacío (error 1350).');
        }
        if ($built->size > $max) {
            throw new \RuntimeException('El archivo excede 6 GB (error 1346).');
        }
    }

    /**
     * Copia el ZIP a storage permanente para auditoría/reintentos.
     */
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

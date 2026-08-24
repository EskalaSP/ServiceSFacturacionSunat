<?php

namespace App\Jobs;

use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\VoidedDocument;
use App\Services\Greenter\GreenterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class CheckVoidedTicketStatus implements ShouldQueue
{
    use Queueable;

    public int $tries = 15;

    public array $backoff = [15, 30, 30, 60, 60, 60, 120, 120, 120, 300, 300, 300, 600, 600, 600];

    public function __construct(
        private int $voidedId
    ) {}

    public function handle(): void
    {
        $voided = VoidedDocument::findOrFail($this->voidedId);

        if (! $voided->ticket) {
            return;
        }

        if (in_array($voided->sunat_status, ['aceptado', 'rechazado'])) {
            return;
        }

        $tenant = Tenant::findOrFail($voided->tenant_id);
        $service = new GreenterService($tenant);
        $result = $service->getStatus($voided->ticket);

        if ($result['success']) {
            $accepted = $result['accepted'] ?? false;

            $voided->update([
                'sunat_status' => $accepted ? 'aceptado' : 'rechazado',
                'sunat_code' => $result['code'] ?? null,
                'sunat_description' => $result['description'] ?? null,
                'sunat_notes' => $result['notes'] ?? null,
            ]);

            // Guardar la constancia (CDR) que SUNAT devuelve al procesar la baja.
            if (! empty($result['cdr_zip']) && $voided->xml_path) {
                $cdrPath = str_replace('/xml/', '/cdr/', $voided->xml_path);
                $cdrPath = preg_replace('/\.xml$/', '.zip', $cdrPath);
                $cdrPath = str_replace($voided->identifier, 'R-'.$voided->identifier, $cdrPath);
                Storage::disk('public')->put($cdrPath, $result['cdr_zip']);
                $voided->update(['cdr_path' => $cdrPath]);
            }

            // Los documentos originales solo se marcan 'anulado' si SUNAT ACEPTÓ la baja.
            // Si la rechazó, revertimos a 'aceptado' (la baja no procedió).
            if ($accepted) {
                $this->updateOriginalDocuments($voided, 'anulado');
            } else {
                $this->updateOriginalDocuments($voided, 'aceptado');
            }

            if ($tenant->webhook_url) {
                NotifyWebhookJob::dispatch(VoidedDocument::class, $voided->id, 'voided.status_updated');
            }
        } else {
            $errorCode = $result['error_code'] ?? '';

            // Codigo 0/187 = SUNAT aún procesando; no numérico (ej. "HTTP") = fallo de red → reintentar
            if (in_array($errorCode, ['0', '187', 0, 187], true) || ! is_numeric((string) $errorCode)) {
                $this->release($this->nextBackoff());

                return;
            }

            // Error definitivo (código numérico SUNAT: 1xxx, 2xxx, 4xxx)
            $voided->update([
                'sunat_status' => 'rechazado',
                'sunat_code' => $errorCode,
                'sunat_description' => $result['error_message'] ?? null,
            ]);

            // La baja falló: los documentos vuelven a estar 'aceptado', no anulados.
            $this->updateOriginalDocuments($voided, 'aceptado');

            if ($tenant->webhook_url) {
                NotifyWebhookJob::dispatch(VoidedDocument::class, $voided->id, 'voided.status_updated');
            }
        }
    }

    /**
     * Actualiza el estado de los documentos originales de la baja.
     * Solo toca los que están 'anulacion_en_proceso' para no pisar otros estados.
     *
     * @param  'anulado'|'aceptado'  $estadoFinal
     */
    private function updateOriginalDocuments(VoidedDocument $voided, string $estadoFinal): void
    {
        $modelMap = [
            '01' => Invoice::class,
            '03' => Boleta::class,
            '07' => CreditNote::class,
            '08' => DebitNote::class,
        ];

        foreach ($voided->detalles ?? [] as $detalle) {
            $tipo = $detalle['tipo_documento'] ?? null;
            $model = $modelMap[$tipo] ?? null;
            if (! $model) {
                continue;
            }

            $serie = $detalle['serie'] ?? null;
            $correlativo = $detalle['correlativo'] ?? null;
            if (! $serie || ! $correlativo) {
                continue;
            }

            $model::where('tenant_id', $voided->tenant_id)
                ->where('serie', $serie)
                ->where('correlativo', $correlativo)
                ->where('sunat_status', 'anulacion_en_proceso')
                ->update(['sunat_status' => $estadoFinal]);
        }
    }

    private function nextBackoff(): int
    {
        $attempt = $this->attempts();

        return $this->backoff[$attempt - 1] ?? 600;
    }
}

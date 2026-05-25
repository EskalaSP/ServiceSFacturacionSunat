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

            // Actualizar el estado de los documentos originales cuando la baja es aceptada
            if ($accepted) {
                $this->updateOriginalDocuments($voided);
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

            if ($tenant->webhook_url) {
                NotifyWebhookJob::dispatch(VoidedDocument::class, $voided->id, 'voided.status_updated');
            }
        }
    }

    /**
     * Marca los documentos originales como 'anulado' según los detalles de la baja.
     */
    private function updateOriginalDocuments(VoidedDocument $voided): void
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
            if (! $model) continue;

            $serie = $detalle['serie'] ?? null;
            $correlativo = $detalle['correlativo'] ?? null;
            if (! $serie || ! $correlativo) continue;

            $model::where('tenant_id', $voided->tenant_id)
                ->where('serie', $serie)
                ->where('correlativo', $correlativo)
                ->update(['sunat_status' => 'anulado']);
        }
    }

    private function nextBackoff(): int
    {
        $attempt = $this->attempts();
        return $this->backoff[$attempt - 1] ?? 600;
    }
}

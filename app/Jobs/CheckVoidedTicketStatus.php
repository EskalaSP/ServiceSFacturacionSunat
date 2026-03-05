<?php

namespace App\Jobs;

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

            if ($tenant->webhook_url) {
                NotifyWebhookJob::dispatch(VoidedDocument::class, $voided->id, 'voided.status_updated');
            }
        } else {
            $errorCode = $result['error_code'] ?? '';

            // Codigo 0 o 187 = SUNAT aún procesando → reintentar
            if (in_array($errorCode, ['0', '187', 0, 187], true)) {
                $this->release($this->nextBackoff());
                return;
            }

            // Error definitivo
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

    private function nextBackoff(): int
    {
        $attempt = $this->attempts();
        return $this->backoff[$attempt - 1] ?? 600;
    }
}

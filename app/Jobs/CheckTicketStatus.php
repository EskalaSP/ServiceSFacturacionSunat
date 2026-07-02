<?php

namespace App\Jobs;

use App\Models\DispatchGuide;
use App\Models\Tenant;
use App\Services\Greenter\GreenterService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckTicketStatus implements ShouldQueue
{
    use Queueable;

    public int $tries = 15;

    public array $backoff = [15, 30, 30, 60, 60, 60, 120, 120, 120, 300, 300, 300, 600, 600, 600];

    public function __construct(
        private int $tenantId,
        private string $ticket,
        private string $modelClass,
        private int $modelId
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $model = $this->modelClass::findOrFail($this->modelId);

        // Si ya tiene estado final, no hacer nada
        if (in_array($model->sunat_status, ['aceptado', 'rechazado'])) {
            return;
        }

        $service = new GreenterService($tenant);
        $storage = new DocumentStorageService();

        // Guías GRE usan API REST, otros documentos usan SOAP
        $result = $this->modelClass === DispatchGuide::class
            ? $service->getGreStatus($this->ticket)
            : $service->getStatus($this->ticket);

        if ($result['success']) {
            $accepted = $result['accepted'] ?? true;

            $model->update([
                'sunat_status' => $accepted ? 'aceptado' : 'rechazado',
                'sunat_code' => isset($result['code']) ? substr((string) $result['code'], 0, 20) : null,
                'sunat_description' => isset($result['description']) ? substr($result['description'], 0, 500) : null,
                'sent_at' => now(),
            ]);

            if (! empty($result['cdr_zip'])) {
                $storage->storeCdr($model, $tenant, $result['cdr_zip']);
            }

            if ($tenant->webhook_url) {
                NotifyWebhookJob::dispatch($this->modelClass, $this->modelId, 'document.status_updated');
            }
        } else {
            $errorCode = $result['error_code'] ?? '';

            // Codigo 0 o 187 = SUNAT aún procesando.
            // 401 Token NO existe = token GRE beta inválido/expirado; reintentar con token fresco.
            if (
                in_array($errorCode, ['0', '187', '401', 0, 187, 401], true)
                && ! $this->attemptsExhausted()
            ) {
                $this->release($this->nextBackoff());
                return;
            }

            // Error definitivo
            $model->update([
                'sunat_status' => 'rechazado',
                'sunat_code' => substr((string) $errorCode, 0, 20),
                'sunat_description' => isset($result['error_message']) ? substr($result['error_message'], 0, 500) : null,
            ]);

            if ($tenant->webhook_url) {
                NotifyWebhookJob::dispatch($this->modelClass, $this->modelId, 'document.status_updated');
            }
        }
    }

    private function nextBackoff(): int
    {
        $attempt = $this->attempts();
        return $this->backoff[$attempt - 1] ?? 600;
    }

    private function attemptsExhausted(): bool
    {
        return $this->attempts() >= $this->tries;
    }
}

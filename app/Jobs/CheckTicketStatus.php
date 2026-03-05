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

    public int $tries = 10;

    public int $backoff = 30;

    public function __construct(
        private int $tenantId,
        private string $ticket,
        private string $modelClass,
        private int $modelId
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $service = new GreenterService($tenant);
        $storage = new DocumentStorageService();
        $result = $service->getStatus($this->ticket);

        $model = $this->modelClass::findOrFail($this->modelId);

        if ($result['success']) {
            $model->update([
                'sunat_status' => ($result['accepted'] ?? true) ? 'aceptado' : 'rechazado',
                'sunat_code' => $result['code'] ?? null,
                'sunat_description' => $result['description'] ?? null,
                'sent_at' => now(),
            ]);

            // Guardar CDR en disco
            if (! empty($result['cdr_zip'])) {
                $storage->storeCdr($model, $tenant, $result['cdr_zip']);
            }

            if ($tenant->webhook_url) {
                NotifyWebhookJob::dispatch($this->modelClass, $this->modelId, 'document.status_updated');
            }
        } else {
            // Si aún está procesando, se reintentará automáticamente
            if (($result['error_code'] ?? '') === '0') {
                $this->release(30);

                return;
            }

            $model->update([
                'sunat_status' => 'rechazado',
                'sunat_code' => $result['error_code'] ?? null,
                'sunat_description' => $result['error_message'] ?? null,
            ]);
        }
    }
}

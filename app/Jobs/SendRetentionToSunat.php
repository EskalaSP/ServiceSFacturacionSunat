<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Retention;
use App\Models\Tenant;
use App\Services\Greenter\Builders\RetentionBuilder;
use App\Services\Greenter\GreenterService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendRetentionToSunat implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public array $backoff = [15, 30, 60, 60, 120, 120, 300, 300, 600, 600];

    public function __construct(
        private int $retentionId
    ) {}

    public function handle(): void
    {
        $retention = Retention::with('items')->findOrFail($this->retentionId);
        $tenant = Tenant::findOrFail($retention->tenant_id);

        $service = new GreenterService($tenant);
        $builder = new RetentionBuilder($tenant);

        $data = $this->retentionToArray($retention);

        try {
            $greenterRetention = $builder->build($data);
            $result = $service->send($greenterRetention);
        } catch (\SoapFault $e) {
            $this->handleRetryableError($retention, $tenant, $e->getMessage());
            return;
        } catch (\Greenter\XMLSecLibs\Exception\XmlSignException $e) {
            $this->markAsRejected($retention, $tenant, 'CERT_ERROR', 'Error de certificado: ' . $e->getMessage());
            return;
        }

        if ($result['success']) {
            $retention->update([
                'sunat_status' => ($result['accepted'] ?? true) ? 'aceptado' : 'rechazado',
                'sunat_code' => $result['code'] ?? null,
                'sunat_description' => $result['description'] ?? null,
                'sunat_notes' => $result['notes'] ?? null,
                'hash_cpe' => $result['hash'] ?? null,
                'sent_at' => now(),
            ]);

            $storage = new DocumentStorageService();
            if (! empty($result['xml'])) {
                $xmlPath = "{$tenant->ruc}/retentions/{$retention->serie}-{$retention->correlativo}.xml";
                \Illuminate\Support\Facades\Storage::disk('public')->put($xmlPath, $result['xml']);
                $retention->update(['xml_path' => $xmlPath]);
            }
            if (! empty($result['cdr_zip'])) {
                $cdrPath = "{$tenant->ruc}/retentions/R-{$retention->serie}-{$retention->correlativo}.zip";
                \Illuminate\Support\Facades\Storage::disk('public')->put($cdrPath, $result['cdr_zip']);
                $retention->update(['cdr_path' => $cdrPath]);
            }

            if ($tenant->webhook_url) {
                NotifyWebhookJob::dispatch(Retention::class, $retention->id, 'retention.sent');
            }
        } else {
            $errorCode = $result['error_code'] ?? '';

            if ($this->isRetryableError($errorCode)) {
                $this->handleRetryableError($retention, $tenant, $result['error_message'] ?? 'Error temporal SUNAT');
                return;
            }

            $this->markAsRejected($retention, $tenant, $errorCode, $result['error_message'] ?? null);
        }
    }

    private function isRetryableError(string $errorCode): bool
    {
        return in_array($errorCode, ['0', '100', '109', '500', '1033', '2800'], true);
    }

    private function handleRetryableError(Retention $retention, Tenant $tenant, string $message): void
    {
        if ($this->attempts() >= $this->tries) {
            $this->markAsRejected($retention, $tenant, 'MAX_RETRIES', "Agotados {$this->tries} intentos: {$message}");
            return;
        }

        $delay = $this->backoff[$this->attempts() - 1] ?? 600;
        $this->release($delay);
    }

    private function markAsRejected(Retention $retention, Tenant $tenant, string $code, ?string $message): void
    {
        $retention->update([
            'sunat_status' => 'rechazado',
            'sunat_code' => substr($code, 0, 20),
            'sunat_description' => $message ? substr($message, 0, 500) : null,
        ]);

        if ($tenant->webhook_url) {
            NotifyWebhookJob::dispatch(Retention::class, $retention->id, 'retention.rejected');
        }
    }

    private function retentionToArray(Retention $retention): array
    {
        return [
            'serie' => $retention->serie,
            'correlativo' => $retention->correlativo,
            'cod_local' => $retention->cod_local,
            'fecha_emision' => $retention->fecha_emision->format('Y-m-d'),
            'proveedor' => [
                'tipo_doc' => $retention->proveedor_tipo_doc,
                'num_doc' => $retention->proveedor_num_doc,
                'razon_social' => $retention->proveedor_razon_social,
                'direccion' => $retention->proveedor_direccion,
            ],
            'regimen' => $retention->regimen,
            'tasa' => (float) $retention->tasa,
            'imp_retenido' => (float) $retention->imp_retenido,
            'imp_pagado' => (float) $retention->imp_pagado,
            'observacion' => $retention->observacion,
            'documentos' => $retention->items->map(fn ($item) => [
                'tipo_doc' => $item->tipo_doc,
                'num_doc' => $item->num_doc,
                'fecha_emision' => $item->fecha_emision_doc->format('Y-m-d'),
                'imp_total' => (float) $item->imp_total,
                'moneda' => $item->moneda,
                'pagos' => $item->pagos,
                'fecha_retencion' => $item->fecha_retencion->format('Y-m-d'),
                'imp_retenido' => (float) $item->imp_retenido,
                'imp_pagar' => (float) $item->imp_pagar,
                'tipo_cambio' => $item->tipo_cambio,
            ])->toArray(),
        ];
    }
}

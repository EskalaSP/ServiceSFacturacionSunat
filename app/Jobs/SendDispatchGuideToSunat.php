<?php

namespace App\Jobs;

use App\Models\DispatchGuide;
use App\Models\Tenant;
use App\Services\Greenter\GreenterService;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDispatchGuideToSunat implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public array $backoff = [15, 30, 60, 60, 120, 120, 300, 300, 600, 600];

    public function __construct(
        private int $guideId
    ) {}

    public function handle(): void
    {
        $guide = DispatchGuide::findOrFail($this->guideId);
        $tenant = Tenant::findOrFail($guide->tenant_id);

        $service = new GreenterService($tenant);
        $storage = new DocumentStorageService();

        $data = $guide->toArray();
        // Fechas como string plano (Y-m-d) para evitar desfase por timezone UTC→Lima
        $data['fecha_emision'] = $guide->fecha_emision->format('Y-m-d');
        $data['fecha_traslado'] = $guide->fecha_traslado->format('Y-m-d');
        $data['fecha_entrega_transportista'] = $guide->fecha_entrega_transportista?->format('Y-m-d');
        $data['destinatario'] = [
            'tipo_doc' => $guide->destinatario_tipo_doc,
            'num_doc' => $guide->destinatario_num_doc,
            'razon_social' => $guide->destinatario_razon_social,
        ];

        try {
            if ($guide->tipo_documento === '31' && empty($data['remitente'])) {
                // Si no se especificó remitente explícito, asumir que el tenant = remitente
                // (caso poco común, pero evita errores).
                $data['remitente'] = $guide->remitente ?? [
                    'tipo_doc' => '6',
                    'num_doc' => $tenant->ruc,
                    'razon_social' => $tenant->razon_social,
                ];
            }

            // GRE 2022 requiere inyecciones XML antes de firmar en algunos casos:
            // - GRT: DespatchParty
            $sendResult = $service->sendDespatch($data);
            $result = $sendResult['result'];
            $xml = $sendResult['xml'];
        } catch (\Throwable $e) {
            // Error de conexión REST API (GRE caído, timeout) → reintentar
            if ($this->isConnectionError($e)) {
                $this->handleRetryableError($guide, $tenant, $e->getMessage());
                return;
            }
            // Error permanente (certificado, datos) → rechazar
            $this->markAsRejected($guide, $tenant, 'BUILD_ERROR', $e->getMessage());
            return;
        }

        if ($xml) {
            $storage->storeXml($guide, $tenant, $xml);
        }

        if ($result->isSuccess()) {
            $ticket = $result->getTicket();
            $guide->update([
                'ticket' => $ticket,
                'sunat_status' => 'enviado',
                'sunat_code' => null,
                'sunat_description' => null,
            ]);

            if (config('pdf.auto_generate', true)) {
                try {
                    app(PdfGeneratorService::class)->generateAndStore($guide, $tenant);
                } catch (\Throwable) {
                }
            }

            CheckTicketStatus::dispatch(
                $tenant->id,
                $ticket,
                DispatchGuide::class,
                $guide->id
            )->delay(now()->addSeconds(15));

            if ($tenant->webhook_url) {
                NotifyWebhookJob::dispatch(DispatchGuide::class, $guide->id, 'document.sent');
            }
        } else {
            $error = $result->getError();
            $errorCode = (string) $error->getCode();

            if ($this->isRetryableError($errorCode)) {
                $this->handleRetryableError($guide, $tenant, $error->getMessage());
                return;
            }

            $this->markAsRejected($guide, $tenant, $errorCode, $error->getMessage());
        }
    }

    private function isConnectionError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'curl') ||
               str_contains($message, 'timeout') ||
               str_contains($message, 'connection') ||
               str_contains($message, 'could not resolve') ||
               $e instanceof \GuzzleHttp\Exception\ConnectException;
    }

    private function isRetryableError(string $errorCode): bool
    {
        return in_array($errorCode, ['0', '100', '109', '500', '1033', '2800'], true);
    }

    private function handleRetryableError(DispatchGuide $guide, Tenant $tenant, string $message): void
    {
        if ($this->attempts() >= $this->tries) {
            $this->markAsRejected($guide, $tenant, 'MAX_RETRIES', "Agotados {$this->tries} intentos: {$message}");
            return;
        }

        $delay = $this->backoff[$this->attempts() - 1] ?? 600;
        $this->release($delay);
    }

    private function markAsRejected(DispatchGuide $guide, Tenant $tenant, string $code, ?string $message): void
    {
        $guide->update([
            'sunat_status' => 'rechazado',
            'sunat_code' => substr($code, 0, 20),
            'sunat_description' => $message ? substr($message, 0, 500) : null,
        ]);

        if ($tenant->webhook_url) {
            NotifyWebhookJob::dispatch(DispatchGuide::class, $guide->id, 'document.rejected');
        }
    }
}

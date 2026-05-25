<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Documentable;
use App\Events\DocumentRejected;
use App\Events\DocumentSent;
use App\Services\Greenter\GreenterService;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

class SendDocumentToSunat implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public array $backoff = [15, 30, 60, 60, 120, 120, 300, 300, 600, 600];

    public function __construct(
        private string $modelClass,
        private int $documentId
    ) {}

    public function handle(): void
    {
        $document = $this->modelClass::with('items')->findOrFail($this->documentId);
        $tenant = $document->tenant;

        $service = new GreenterService($tenant);
        $storage = new DocumentStorageService();

        $tipoDocumento = $document instanceof Documentable
            ? $document->getTipoDocumento()
            : $document->tipo_documento;

        try {
            $greenterDoc = match ($tipoDocumento) {
                '01', '03' => $service->buildInvoice($this->documentToArray($document, $tipoDocumento)),
                '07', '08' => $service->buildNote($this->documentToArray($document, $tipoDocumento)),
                default => throw new \RuntimeException("Tipo de documento {$tipoDocumento} no soportado"),
            };

            $result = $service->send($greenterDoc);
        } catch (\SoapFault $e) {
            // Error de conexión SOAP (SUNAT caído, timeout) → reintentar
            $this->handleRetryableError($document, $e->getMessage());
            return;
        } catch (\Greenter\XMLSecLibs\Exception\XmlSignException $e) {
            // Certificado inválido → error permanente, no reintentar
            $this->markAsRejected($document, $tenant, 'CERT_ERROR', 'Error de certificado: ' . $e->getMessage());
            return;
        }

        if ($result['success']) {
            $document->update([
                // SUNAT Formato 1.3.4: 0=aceptado, 4000+=observaciones (aceptado con advertencias)
                'sunat_status' => ($result['accepted'] ?? true)
                    ? 'aceptado'
                    : 'rechazado',
                'sunat_code' => $result['code'] ?? null,
                'sunat_description' => $result['description'] ?? null,
                'sunat_notes' => $result['notes'] ?? null,
                'hash_cpe' => $result['hash'] ?? null,
                'sent_at' => now(),
            ]);

            if (! empty($result['xml'])) {
                $storage->storeXml($document, $tenant, $result['xml']);
            }
            if (! empty($result['cdr_zip'])) {
                $storage->storeCdr($document, $tenant, $result['cdr_zip']);
            }

            if (config('pdf.auto_generate', true)) {
                try {
                    app(PdfGeneratorService::class)->generateAndStore($document, $tenant);
                } catch (\Throwable) {
                }
            }

            event(new DocumentSent($document, $result));

            if ($tenant->webhook_url) {
                NotifyWebhookJob::dispatch($this->modelClass, $document->id, 'document.sent');
            }
        } else {
            $errorCode = $result['error_code'] ?? '';

            // Errores temporales de SUNAT → reintentar
            if ($this->isRetryableError($errorCode)) {
                $this->handleRetryableError($document, $result['error_message'] ?? 'Error temporal SUNAT');
                return;
            }

            // 3xxx = observación (documento aceptado con advertencias, NO es rechazo)
            if (str_starts_with((string) $errorCode, '3')) {
                $document->update([
                    'sunat_status' => 'aceptado',
                    'sunat_code' => substr($errorCode, 0, 20),
                    'sunat_description' => $result['error_message'] ? substr($result['error_message'], 0, 500) : null,
                    'hash_cpe' => $result['hash'] ?? null,
                    'sent_at' => now(),
                ]);

                if (! empty($result['xml'])) {
                    $storage->storeXml($document, $tenant, $result['xml']);
                }
                if (! empty($result['cdr_zip'])) {
                    $storage->storeCdr($document, $tenant, $result['cdr_zip']);
                }

                if (config('pdf.auto_generate', true)) {
                    try {
                        app(PdfGeneratorService::class)->generateAndStore($document, $tenant);
                    } catch (\Throwable) {
                    }
                }

                event(new DocumentSent($document, $result));

                if ($tenant->webhook_url) {
                    NotifyWebhookJob::dispatch($this->modelClass, $document->id, 'document.sent');
                }

                return;
            }

            // Error permanente de SUNAT → marcar rechazado
            $this->markAsRejected($document, $tenant, $errorCode, $result['error_message'] ?? null, $result['xml'] ?? null);

            event(new DocumentRejected($document, $result));
        }
    }

    private function isRetryableError(string $errorCode): bool
    {
        // SUNAT Formato 1.3.4 rangos de error:
        // 0: Aceptado (no debería llegar aquí, pero se trata como reintentable por ambigüedad de transporte)
        // 100-1999: Excepciones de servidor → algunos son temporales (100=timeout, 109=servicio caído, 500=interno)
        // 2000-3999: Errores de validación → permanentes, excepto 2800 (correlativo duplicado por concurrencia)
        // 4000+: Observaciones → aceptado con advertencias (manejado en la rama de éxito)
        $retryableCodes = ['0', '100', '109', '500', '1033', '2800'];

        return in_array($errorCode, $retryableCodes, true);
    }

    private function handleRetryableError($document, string $message): void
    {
        if ($this->attempts() >= $this->tries) {
            // Último intento → marcar como rechazado
            $document->update([
                'sunat_status' => 'rechazado',
                'sunat_code' => 'MAX_RETRIES',
                'sunat_description' => "Agotados {$this->tries} intentos: {$message}",
            ]);

            if ($document->tenant->webhook_url) {
                NotifyWebhookJob::dispatch($this->modelClass, $document->id, 'document.rejected');
            }

            return;
        }

        // Reintentar con backoff
        $delay = $this->backoff[$this->attempts() - 1] ?? 600;
        $this->release($delay);
    }

    private function markAsRejected($document, $tenant, string $code, ?string $message, ?string $xml = null): void
    {
        $document->update([
            'sunat_status' => 'rechazado',
            'sunat_code' => substr($code, 0, 20),
            'sunat_description' => $message ? substr($message, 0, 500) : null,
        ]);

        if ($xml) {
            (new DocumentStorageService())->storeXml($document, $tenant, $xml);
        }

        if ($tenant->webhook_url) {
            NotifyWebhookJob::dispatch($this->modelClass, $document->id, 'document.rejected');
        }
    }

    private function documentToArray(Model $document, string $tipoDocumento): array
    {
        $data = $document->toArray();
        $data['tipo_documento'] = $tipoDocumento;
        // Fecha con hora para generar IssueTime en XML
        $data['fecha_emision'] = $document->fecha_emision->format('Y-m-d H:i:s');
        if ($document->fecha_vencimiento) {
            $data['fecha_vencimiento'] = $document->fecha_vencimiento->format('Y-m-d');
        }
        $data['cliente'] = [
            'tipo_doc' => $document->client_tipo_doc,
            'num_doc' => $document->client_num_doc,
            'razon_social' => $document->client_razon_social,
            'direccion' => $document->client_direccion,
        ];
        // Calcular sum_otros_descuentos desde descuentos_globales cod "03" y "04"
        // para que Greenter renderice <cbc:AllowanceTotalAmount> (SUNAT 3300)
        $sumOtrosDescuentos = 0;
        if (! empty($document->descuentos_globales)) {
            foreach ($document->descuentos_globales as $desc) {
                $codTipo = $desc['cod_tipo'] ?? '02';
                if (in_array($codTipo, ['03', '04'], true)) {
                    $sumOtrosDescuentos += (float) ($desc['monto'] ?? 0);
                }
            }
        }
        if ($sumOtrosDescuentos > 0) {
            $data['sum_otros_descuentos'] = round($sumOtrosDescuentos, 2);
        }

        $data['items'] = $document->items->map(function ($item) {
            $mapped = [
                'codigo' => $item->codigo,
                'descripcion' => $item->descripcion,
                'unidad' => $item->unidad,
                'cantidad' => $item->cantidad,
                'precio_unitario' => $item->mto_precio_unitario,
                'mto_valor_unitario' => $item->mto_valor_unitario,
                'mto_valor_venta' => $item->mto_valor_venta,
                'mto_base_igv' => $item->mto_base_igv,
                'porcentaje_igv' => $item->porcentaje_igv,
                'igv' => $item->igv,
                'tip_afe_igv' => $item->tip_afe_igv,
                'total_impuestos' => $item->total_impuestos,
                'isc' => $item->isc ?? 0,
                'tip_sis_isc' => $item->tip_sis_isc ?? '01',
                'icbper' => $item->icbper ?? 0,
                'factor_icbper' => $item->factor_icbper ?? 0,
            ];
            if (! empty($item->descuentos)) {
                $mapped['descuentos'] = $item->descuentos;
            }

            return $mapped;
        })->toArray();

        return $data;
    }
}

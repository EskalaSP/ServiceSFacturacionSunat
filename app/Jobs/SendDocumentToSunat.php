<?php

namespace App\Jobs;

use App\Events\DocumentRejected;
use App\Events\DocumentSent;
use App\Models\Document;
use App\Services\Greenter\GreenterService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDocumentToSunat implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private int $documentId
    ) {}

    public function handle(): void
    {
        $document = Document::with('items')->findOrFail($this->documentId);
        $tenant = $document->tenant;

        $service = new GreenterService($tenant);
        $storage = new DocumentStorageService();

        // Reconstruir el documento Greenter según tipo
        $greenterDoc = match ($document->tipo_documento) {
            '01', '03' => $service->buildInvoice($this->documentToArray($document)),
            '07', '08' => $service->buildNote($this->documentToArray($document)),
            default => throw new \RuntimeException("Tipo de documento {$document->tipo_documento} no soportado para envío async"),
        };

        $result = $service->send($greenterDoc);

        if ($result['success']) {
            $document->update([
                'sunat_status' => ($result['accepted'] ?? true) ? 'aceptado' : 'rechazado',
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

            event(new DocumentSent($document, $result));
        } else {
            $document->update([
                'sunat_status' => 'rechazado',
                'sunat_code' => $result['error_code'] ?? null,
                'sunat_description' => $result['error_message'] ?? null,
            ]);

            if (! empty($result['xml'])) {
                $storage->storeXml($document, $tenant, $result['xml']);
            }

            event(new DocumentRejected($document, $result));
        }

        // Notificar webhook si configurado
        if ($tenant->webhook_url) {
            NotifyWebhookJob::dispatch($document->id, $result['success'] ? 'document.sent' : 'document.rejected');
        }
    }

    private function documentToArray(Document $document): array
    {
        $data = $document->toArray();
        $data['cliente'] = [
            'tipo_doc' => $document->client_tipo_doc,
            'num_doc' => $document->client_num_doc,
            'razon_social' => $document->client_razon_social,
            'direccion' => $document->client_direccion,
        ];
        $data['items'] = $document->items->map(fn ($item) => [
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
        ])->toArray();

        return $data;
    }
}

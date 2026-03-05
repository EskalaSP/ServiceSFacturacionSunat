<?php

namespace App\Jobs;

use App\Contracts\Documentable;
use App\Events\DocumentRejected;
use App\Events\DocumentSent;
use App\Services\Greenter\GreenterService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

class SendDocumentToSunat implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

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

        $greenterDoc = match ($tipoDocumento) {
            '01', '03' => $service->buildInvoice($this->documentToArray($document, $tipoDocumento)),
            '07', '08' => $service->buildNote($this->documentToArray($document, $tipoDocumento)),
            default => throw new \RuntimeException("Tipo de documento {$tipoDocumento} no soportado para envío async"),
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

        if ($tenant->webhook_url) {
            NotifyWebhookJob::dispatch($this->modelClass, $document->id, $result['success'] ? 'document.sent' : 'document.rejected');
        }
    }

    private function documentToArray(Model $document, string $tipoDocumento): array
    {
        $data = $document->toArray();
        $data['tipo_documento'] = $tipoDocumento;
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

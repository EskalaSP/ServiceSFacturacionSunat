<?php

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class NotifyWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 90];

    public function __construct(
        private int $documentId,
        private string $event
    ) {}

    public function handle(): void
    {
        $document = Document::with(['tenant', 'items'])->find($this->documentId);

        if (! $document || ! $document->tenant->webhook_url) {
            return;
        }

        Http::timeout(15)->post($document->tenant->webhook_url, [
            'event' => $this->event,
            'document' => [
                'id' => $document->id,
                'tipo_documento' => $document->tipo_documento,
                'serie' => $document->serie,
                'correlativo' => $document->correlativo,
                'numero_completo' => $document->numero_completo,
                'fecha_emision' => $document->fecha_emision->format('Y-m-d'),
                'total' => $document->mto_imp_venta,
                'sunat_status' => $document->sunat_status,
                'sunat_code' => $document->sunat_code,
                'sunat_description' => $document->sunat_description,
                'hash_cpe' => $document->hash_cpe,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

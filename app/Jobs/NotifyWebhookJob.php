<?php

namespace App\Jobs;

use App\Contracts\Documentable;
use App\Models\DispatchGuide;
use App\Models\Summary;
use App\Models\VoidedDocument;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class NotifyWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 90];

    /**
     * Modelos sin relación 'items' (items en JSON o sin items).
     */
    private const MODELS_WITHOUT_ITEMS = [
        Summary::class,
        VoidedDocument::class,
        DispatchGuide::class,
    ];

    public function __construct(
        private string $modelClass,
        private int $documentId,
        private string $event
    ) {}

    public function handle(): void
    {
        $hasItems = ! in_array($this->modelClass, self::MODELS_WITHOUT_ITEMS, true);

        $record = $hasItems
            ? $this->modelClass::with(['tenant', 'items'])->find($this->documentId)
            : $this->modelClass::with('tenant')->find($this->documentId);

        if (! $record || ! $record->tenant->webhook_url) {
            return;
        }

        $payload = match ($this->modelClass) {
            Summary::class => $this->buildSummaryPayload($record),
            VoidedDocument::class => $this->buildVoidedPayload($record),
            DispatchGuide::class => $this->buildDispatchPayload($record),
            default => $this->buildDocumentPayload($record),
        };

        Http::timeout(15)->post($record->tenant->webhook_url, [
            'event' => $this->event,
            'document' => $payload,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    private function buildDocumentPayload($document): array
    {
        $tipoDocumento = $document instanceof Documentable
            ? $document->getTipoDocumento()
            : $document->tipo_documento;

        return [
            'id' => $document->id,
            'tipo_documento' => $tipoDocumento,
            'serie' => $document->serie,
            'correlativo' => $document->correlativo,
            'numero_completo' => $document->numero_completo,
            'fecha_emision' => $document->fecha_emision->format('Y-m-d'),
            'total' => $document->mto_imp_venta,
            'sunat_status' => $document->sunat_status,
            'sunat_code' => $document->sunat_code,
            'sunat_description' => $document->sunat_description,
            'hash_cpe' => $document->hash_cpe,
        ];
    }

    private function buildDispatchPayload(DispatchGuide $guide): array
    {
        return [
            'id' => $guide->id,
            'tipo_documento' => '09',
            'serie' => $guide->serie,
            'correlativo' => $guide->correlativo,
            'numero_completo' => $guide->serie . '-' . $guide->correlativo,
            'fecha_emision' => $guide->fecha_emision->format('Y-m-d'),
            'destinatario' => $guide->destinatario_razon_social,
            'ticket' => $guide->ticket,
            'sunat_status' => $guide->sunat_status,
            'sunat_code' => $guide->sunat_code,
            'sunat_description' => $guide->sunat_description,
        ];
    }

    private function buildSummaryPayload(Summary $summary): array
    {
        return [
            'id' => $summary->id,
            'tipo_documento' => 'resumen',
            'identifier' => $summary->identifier,
            'tipo' => $summary->tipo,
            'fecha_referencia' => $summary->fecha_referencia->format('Y-m-d'),
            'fecha_envio' => $summary->fecha_envio->format('Y-m-d'),
            'total_documentos' => $summary->total_documentos,
            'ticket' => $summary->ticket,
            'sunat_status' => $summary->sunat_status,
            'sunat_code' => $summary->sunat_code,
            'sunat_description' => $summary->sunat_description,
        ];
    }

    private function buildVoidedPayload(VoidedDocument $voided): array
    {
        return [
            'id' => $voided->id,
            'tipo_documento' => 'comunicacion_baja',
            'identifier' => $voided->identifier,
            'fecha_generacion' => $voided->fecha_generacion->format('Y-m-d'),
            'fecha_comunicacion' => $voided->fecha_comunicacion->format('Y-m-d'),
            'total_documentos' => $voided->total_documentos,
            'ticket' => $voided->ticket,
            'sunat_status' => $voided->sunat_status,
            'sunat_code' => $voided->sunat_code,
            'sunat_description' => $voided->sunat_description,
        ];
    }
}

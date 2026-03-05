<?php

namespace App\Actions\Documents;

use App\Contracts\Documentable;
use App\Events\DocumentCreated;
use App\Events\DocumentRejected;
use App\Events\DocumentSent;
use App\Jobs\NotifyWebhookJob;
use App\Jobs\SendDocumentToSunat;
use App\Models\Invoice;
use App\Models\Serie;
use App\Models\Tenant;
use App\Services\ClientResolverService;
use App\Services\DocumentCalculationService;
use App\Services\Greenter\GreenterService;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreateInvoiceAction
{
    public function __construct(
        private DocumentCalculationService $calculator,
        private ClientResolverService $clientResolver,
    ) {}

    public function execute(Tenant $tenant, array $data, bool $async = false): Invoice
    {
        return DB::transaction(function () use ($tenant, $data, $async) {
            $serie = Serie::where('tenant_id', $tenant->id)
                ->where('tipo_documento', '01')
                ->where('serie', $data['serie'])
                ->where('is_active', true)
                ->with('sucursal')
                ->lockForUpdate()
                ->firstOrFail();

            $correlativo = $serie->nextCorrelativo();
            $data['correlativo'] = $correlativo;
            $data['tipo_documento'] = '01';

            $sucursal = $serie->sucursal;

            $client = $this->clientResolver->resolve($tenant, $data['cliente']);

            $calculatedItems = $this->calculator->calculateItems($data['items']);
            $totals = $this->calculator->calculateTotals($calculatedItems, $data);
            $data = array_merge($data, $totals);

            if (empty($data['leyenda'])) {
                $data['leyenda'] = $this->calculator->generateLeyenda($totals['mto_imp_venta'], $data['tipo_moneda'] ?? 'PEN');
            }

            $invoice = Invoice::create([
                'tenant_id' => $tenant->id,
                'sucursal_id' => $sucursal?->id,
                'cod_local' => $sucursal?->cod_local ?? $data['cod_local'] ?? '0000',
                'client_id' => $client->id,
                'serie' => $data['serie'],
                'correlativo' => $correlativo,
                'fecha_emision' => $data['fecha_emision'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'tipo_operacion' => $data['tipo_operacion'] ?? '0101',
                'tipo_moneda' => $data['tipo_moneda'] ?? 'PEN',
                'forma_pago' => $data['forma_pago'] ?? 'Contado',
                'client_tipo_doc' => $data['cliente']['tipo_doc'],
                'client_num_doc' => $data['cliente']['num_doc'],
                'client_razon_social' => $data['cliente']['razon_social'],
                'client_direccion' => $data['cliente']['direccion'] ?? null,
                'mto_oper_gravadas' => $totals['mto_oper_gravadas'],
                'mto_oper_exoneradas' => $totals['mto_oper_exoneradas'],
                'mto_oper_inafectas' => $totals['mto_oper_inafectas'],
                'mto_oper_exportacion' => $totals['mto_oper_exportacion'],
                'mto_oper_gratuitas' => $totals['mto_oper_gratuitas'],
                'mto_igv' => $totals['mto_igv'],
                'mto_base_ivap' => $totals['mto_base_ivap'],
                'mto_ivap' => $totals['mto_ivap'],
                'mto_isc' => $totals['mto_isc'],
                'mto_icbper' => $totals['mto_icbper'],
                'total_impuestos' => $totals['total_impuestos'],
                'valor_venta' => $totals['valor_venta'],
                'sub_total' => $totals['sub_total'],
                'mto_imp_venta' => $totals['mto_imp_venta'],
                'total_anticipos' => $data['total_anticipos'] ?? 0,
                'total_descuentos' => $data['total_descuentos'] ?? 0,
                'leyenda' => $data['leyenda'] ?? null,
                'observacion' => $data['observacion'] ?? null,
                'cuotas' => $data['cuotas'] ?? null,
                'detraccion' => $data['detraccion'] ?? null,
                'percepcion' => $data['percepcion'] ?? null,
                'anticipos' => $data['anticipos'] ?? null,
                'descuentos_globales' => $data['descuentos_globales'] ?? null,
                'guias' => $data['guias'] ?? null,
                'extras' => $data['extras'] ?? null,
                'sunat_status' => 'pendiente',
            ]);

            foreach ($calculatedItems as $item) {
                $invoice->items()->create($item);
            }

            event(new DocumentCreated($invoice));
            Cache::forget("tenant:{$tenant->id}:doc_count:" . now()->format('Y-m'));

            if ($async) {
                SendDocumentToSunat::dispatch(Invoice::class, $invoice->id);
                $invoice->update(['sunat_status' => 'enviado']);
            } else {
                $this->sendToSunat($tenant, $invoice, $data);
            }

            return $invoice->fresh(['items']);
        });
    }

    private function sendToSunat(Tenant $tenant, Invoice $invoice, array $data): void
    {
        $service = new GreenterService($tenant);
        $storage = new DocumentStorageService();

        $greenterDoc = $service->buildInvoice($data);
        $result = $service->send($greenterDoc);

        if ($result['success']) {
            $invoice->update([
                'sunat_status' => ($result['accepted'] ?? true) ? 'aceptado' : 'rechazado',
                'sunat_code' => $result['code'] ?? null,
                'sunat_description' => $result['description'] ?? null,
                'sunat_notes' => $result['notes'] ?? null,
                'hash_cpe' => $result['hash'] ?? null,
                'sent_at' => now(),
            ]);

            if (! empty($result['xml'])) {
                $storage->storeXml($invoice, $tenant, $result['xml']);
            }
            if (! empty($result['cdr_zip'])) {
                $storage->storeCdr($invoice, $tenant, $result['cdr_zip']);
            }

            if (config('pdf.auto_generate', true)) {
                try {
                    app(PdfGeneratorService::class)->generateAndStore($invoice, $tenant);
                } catch (\Throwable $e) {
                    // PDF generation failure should not block the main flow
                }
            }

            event(new DocumentSent($invoice, $result));

            if ($tenant->webhook_url) {
                NotifyWebhookJob::dispatch(Invoice::class, $invoice->id, 'document.sent');
            }
        } else {
            $invoice->update([
                'sunat_status' => 'rechazado',
                'sunat_code' => $result['error_code'] ?? null,
                'sunat_description' => $result['error_message'] ?? null,
            ]);

            if (! empty($result['xml'])) {
                $storage->storeXml($invoice, $tenant, $result['xml']);
            }

            event(new DocumentRejected($invoice, $result));
        }
    }
}

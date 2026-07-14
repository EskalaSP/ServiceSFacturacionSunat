<?php

namespace App\Actions\Documents;

use App\Actions\Payments\RegisterPaymentAction;
use App\Events\DocumentCreated;
use App\Jobs\SendDocumentToSunat;
use App\Models\Invoice;
use App\Models\Serie;
use App\Models\Tenant;
use App\Services\ClientResolverService;
use App\Services\DocumentCalculationService;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreateInvoiceAction
{
    use Concerns\ResolvesEmisionDateTime;

    public function __construct(
        private DocumentCalculationService $calculator,
        private ClientResolverService $clientResolver,
        private RegisterPaymentAction $paymentAction,
    ) {}

    public function execute(Tenant $tenant, array $data, bool $enviarAutomatico = true): Invoice
    {
        return DB::transaction(function () use ($tenant, $data, $enviarAutomatico) {
            $serie = Serie::where('tenant_id', $tenant->id)
                ->where('tipo_documento', '01')
                ->where('serie', $data['serie'])
                ->where('is_active', true)
                ->with('sucursal')
                ->lockForUpdate()
                ->firstOrFail();

            $correlativo = $serie->resolveCorrelativo(isset($data['correlativo']) ? (int) $data['correlativo'] : null);
            $data['correlativo'] = $correlativo;
            $data['tipo_documento'] = '01';

            $sucursal = $serie->sucursal;

            $client = $this->clientResolver->resolve($tenant, $data['cliente']);

            $fechaEmision = $data['fecha_emision'] ?? null;
            $calculatedItems = $this->calculator->calculateItems($data['items'], $tenant, $fechaEmision);
            $totals = $this->calculator->calculateTotals($calculatedItems, $data, $tenant, $fechaEmision);
            $data = array_merge($data, $totals);

            if (empty($data['leyenda'])) {
                $leyendaTotal = !empty($data['percepcion']['mto_total'])
                    ? (float) $data['percepcion']['mto_total']
                    : $totals['mto_imp_venta'];
                $data['leyenda'] = $this->calculator->generateLeyenda($leyendaTotal, $data['tipo_moneda'] ?? 'PEN');
            }

            $invoice = Invoice::create([
                'tenant_id' => $tenant->id,
                'sucursal_id' => $sucursal?->id,
                'cod_local' => $sucursal?->cod_local ?? $data['cod_local'] ?? '0000',
                'client_id' => $client->id,
                'serie' => $data['serie'],
                'correlativo' => $correlativo,
                'fecha_emision' => $this->resolveEmisionDateTime($data['fecha_emision']),
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
                'total_anticipos' => $data['total_anticipos'] ?? collect($data['anticipos'] ?? [])->sum('monto'),
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
            Cache::forget("tenant:{$tenant->id}:sunat_count:" . now()->format('Y-m'));
            app(PlanService::class)->incrementUsage($tenant, 'documents');

            if ($enviarAutomatico) {
                SendDocumentToSunat::dispatch(Invoice::class, $invoice->id);
                $invoice->update(['sunat_status' => 'enviado']);
            }

            if (! empty($data['pagos'])) {
                $this->paymentAction->execute($invoice, $data['pagos']);
            }

            return $invoice->fresh(['items', 'payments', 'client']);
        });
    }

}

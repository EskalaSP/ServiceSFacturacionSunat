<?php

namespace App\Actions\Documents;

use App\Models\InternalDocument;
use App\Models\Quotation;
use App\Models\Tenant;
use App\Services\ClientResolverService;
use App\Services\DocumentCalculationService;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreateQuotationAction
{
    use Concerns\ResolvesEmisionDateTime;

    public function __construct(
        private DocumentCalculationService $calculator,
        private ClientResolverService $clientResolver,
    ) {}

    public function execute(Tenant $tenant, array $data): Quotation
    {
        return DB::transaction(function () use ($tenant, $data) {
            $numero = InternalDocument::generateNumero($tenant->id, 'quotation');

            $client = $this->clientResolver->resolve($tenant, $data['cliente']);

            $fechaEmision = $data['fecha_emision'] ?? null;
            $calculatedItems = $this->calculator->calculateItems($data['items'], $tenant, $fechaEmision);
            $totals = $this->calculator->calculateTotals($calculatedItems, $data, $tenant, $fechaEmision);

            $quotation = Quotation::create([
                'tenant_id' => $tenant->id,
                'client_id' => $client->id,
                'numero' => $numero,
                'fecha_emision' => $this->resolveEmisionDateTime($data['fecha_emision']),
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'tipo_moneda' => $data['tipo_moneda'] ?? 'PEN',
                'client_tipo_doc' => $data['cliente']['tipo_doc'],
                'client_num_doc' => $data['cliente']['num_doc'],
                'client_razon_social' => $data['cliente']['razon_social'],
                'client_direccion' => $data['cliente']['direccion'] ?? null,
                'mto_oper_gravadas' => $totals['mto_oper_gravadas'],
                'mto_oper_exoneradas' => $totals['mto_oper_exoneradas'],
                'mto_oper_inafectas' => $totals['mto_oper_inafectas'],
                'mto_oper_gratuitas' => $totals['mto_oper_gratuitas'],
                'mto_igv' => $totals['mto_igv'],
                'mto_isc' => $totals['mto_isc'],
                'mto_icbper' => $totals['mto_icbper'],
                'total_impuestos' => $totals['total_impuestos'],
                'valor_venta' => $totals['valor_venta'],
                'sub_total' => $totals['sub_total'],
                'mto_imp_venta' => $totals['mto_imp_venta'],
                'total_descuentos' => $data['total_descuentos'] ?? 0,
                'observacion' => $data['observacion'] ?? null,
                'status' => 'vigente',
            ]);

            foreach ($calculatedItems as $item) {
                $quotation->items()->create($item);
            }

            Cache::forget("tenant:{$tenant->id}:internal_count:" . now()->format('Y-m'));
            app(PlanService::class)->incrementUsage($tenant, 'documents');

            return $quotation->fresh(['items', 'client']);
        });
    }
}

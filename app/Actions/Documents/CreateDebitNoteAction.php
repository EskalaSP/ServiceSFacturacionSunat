<?php

namespace App\Actions\Documents;

use App\Events\DocumentCreated;
use App\Jobs\SendDocumentToSunat;
use App\Models\DebitNote;
use App\Models\Serie;
use App\Models\Tenant;
use App\Services\ClientResolverService;
use App\Services\DocumentCalculationService;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreateDebitNoteAction
{
    use Concerns\ResolvesEmisionDateTime;

    public function __construct(
        private DocumentCalculationService $calculator,
        private ClientResolverService $clientResolver,
    ) {}

    public function execute(Tenant $tenant, array $data, bool $enviarAutomatico = true): DebitNote
    {
        return DB::transaction(function () use ($tenant, $data, $enviarAutomatico) {
            $serie = Serie::where('tenant_id', $tenant->id)
                ->where('tipo_documento', '08')
                ->where('serie', $data['serie'])
                ->where('is_active', true)
                ->with('sucursal')
                ->lockForUpdate()
                ->firstOrFail();

            $correlativo = $serie->resolveCorrelativo(isset($data['correlativo']) ? (int) $data['correlativo'] : null);
            $data['correlativo'] = $correlativo;
            $data['tipo_documento'] = '08';

            $sucursal = $serie->sucursal;

            $client = $this->clientResolver->resolve($tenant, $data['cliente']);

            $fechaEmision = $data['fecha_emision'] ?? null;
            $calculatedItems = $this->calculator->calculateItems($data['items'], $tenant, $fechaEmision);
            $totals = $this->calculator->calculateTotals($calculatedItems, $data, $tenant, $fechaEmision);
            $data = array_merge($data, $totals);

            if (empty($data['leyenda'])) {
                $data['leyenda'] = $this->calculator->generateLeyenda($totals['mto_imp_venta'], $data['tipo_moneda'] ?? 'PEN');
            }

            $debitNote = DebitNote::create([
                'tenant_id' => $tenant->id,
                'sucursal_id' => $sucursal?->id,
                'cod_local' => $sucursal?->cod_local ?? $data['cod_local'] ?? '0000',
                'client_id' => $client->id,
                'serie' => $data['serie'],
                'correlativo' => $correlativo,
                'fecha_emision' => $this->resolveEmisionDateTime($data['fecha_emision']),
                'tipo_moneda' => $data['tipo_moneda'] ?? 'PEN',
                'client_tipo_doc' => $data['cliente']['tipo_doc'],
                'client_num_doc' => $data['cliente']['num_doc'],
                'client_razon_social' => $data['cliente']['razon_social'],
                'client_direccion' => $data['cliente']['direccion'] ?? null,
                'doc_afectado_tipo' => $data['doc_afectado_tipo'],
                'doc_afectado_serie' => $data['doc_afectado_serie'],
                'doc_afectado_correlativo' => $data['doc_afectado_correlativo'],
                'cod_motivo' => $data['cod_motivo'],
                'des_motivo' => $data['des_motivo'],
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
                'total_anticipos' => $data['total_anticipos'] ?? 0,
                'total_descuentos' => $data['total_descuentos'] ?? 0,
                'leyenda' => $data['leyenda'] ?? null,
                'observacion' => $data['observacion'] ?? null,
                'guias' => $data['guias'] ?? null,
                'sunat_status' => 'pendiente',
            ]);

            foreach ($calculatedItems as $item) {
                $debitNote->items()->create($item);
            }

            event(new DocumentCreated($debitNote));
            Cache::forget("tenant:{$tenant->id}:doc_count:" . now()->format('Y-m'));
            Cache::forget("tenant:{$tenant->id}:sunat_count:" . now()->format('Y-m'));
            app(PlanService::class)->incrementUsage($tenant, 'documents');

            if ($enviarAutomatico) {
                SendDocumentToSunat::dispatch(DebitNote::class, $debitNote->id);
                $debitNote->update(['sunat_status' => 'enviado']);
            }

            return $debitNote->fresh(['items', 'client']);
        });
    }
}

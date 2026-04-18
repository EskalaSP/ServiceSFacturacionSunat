<?php

namespace App\Sire\Services\Reconciliation;

use App\Models\Tenant;
use App\Sire\Models\SireComprobante;
use App\Sire\Models\SireReconciliationLog;
use Illuminate\Support\Facades\DB;

/**
 * Analiza los comprobantes SIRE RCE de un periodo y genera un reporte de
 * reconciliación. Detecta:
 *   - Totales y agregados
 *   - Top proveedores
 *   - Distribución por tipo de comprobante
 *   - Cruce con clients / invoices / boletas (relación bidireccional B2B)
 *   - Alertas: duplicados internos, outliers, inconsistencias SUNAT
 *
 * Persiste el resumen en `sire_reconciliation_logs` para audit y trending.
 *
 * Extensible: cuando haya tabla `purchases` local, agregar método
 * compareWithPurchases($tenant, $periodo).
 */
class ReconciliationService
{
    private const TOP_PROVEEDORES = 20;

    private const UMBRAL_OUTLIER_FACTOR = 10.0; // mto_total > 10x promedio

    public function run(Tenant $tenant, string $perTributario): ReconciliationReport
    {
        $report = new ReconciliationReport(
            tenantId: $tenant->id,
            perTributario: $perTributario,
        );

        $report->totales       = $this->calcularTotales($tenant, $perTributario);
        $report->porProveedor  = $this->analizarPorProveedor($tenant, $perTributario);
        $report->porTipo       = $this->analizarPorTipo($tenant, $perTributario);
        $report->cruceLocal    = $this->cruzarConBdLocal($tenant, $perTributario);
        $report->alertas       = $this->detectarAlertas($tenant, $perTributario, $report->totales);

        $this->persistir($tenant, $report);

        $tenant->update([
            'sire_last_period_synced'     => $perTributario,
            'sire_last_reconciliation_at' => now(),
        ]);

        return $report;
    }

    // ==================================================================
    // 1. Totales
    // ==================================================================
    private function calcularTotales(Tenant $tenant, string $periodo): array
    {
        $row = SireComprobante::query()
            ->where('tenant_id', $tenant->id)
            ->where('per_tributario', $periodo)
            ->selectRaw('
                COUNT(*) as total_comprobantes,
                SUM(CASE WHEN incluido = 1 THEN 1 ELSE 0 END) as total_incluidos,
                SUM(CASE WHEN incluido = 0 THEN 1 ELSE 0 END) as total_excluidos,
                SUM(mto_bi_gravada) as suma_bi_gravada,
                SUM(mto_igv)        as suma_igv,
                SUM(mto_bi_no_gravada) as suma_bi_no_gravada,
                SUM(mto_total)      as suma_total,
                MIN(fec_emision)    as fecha_min,
                MAX(fec_emision)    as fecha_max,
                AVG(mto_total)      as promedio_monto
            ')
            ->first();

        return [
            'total_comprobantes' => (int) $row->total_comprobantes,
            'total_incluidos'    => (int) $row->total_incluidos,
            'total_excluidos'    => (int) $row->total_excluidos,
            'suma_bi_gravada'    => (float) $row->suma_bi_gravada,
            'suma_igv'           => (float) $row->suma_igv,
            'suma_bi_no_gravada' => (float) $row->suma_bi_no_gravada,
            'suma_total'         => (float) $row->suma_total,
            'promedio_monto'     => (float) $row->promedio_monto,
            'fecha_min'          => $row->fecha_min,
            'fecha_max'          => $row->fecha_max,
        ];
    }

    // ==================================================================
    // 2. Por proveedor
    // ==================================================================
    private function analizarPorProveedor(Tenant $tenant, string $periodo): array
    {
        return SireComprobante::query()
            ->where('tenant_id', $tenant->id)
            ->where('per_tributario', $periodo)
            ->selectRaw('
                num_doc_proveedor,
                tipo_doc_proveedor,
                MAX(razon_social_proveedor) as razon_social,
                COUNT(*) as cantidad_cp,
                SUM(mto_bi_gravada) as suma_bi,
                SUM(mto_igv) as suma_igv,
                SUM(mto_total) as suma_total
            ')
            ->groupBy('num_doc_proveedor', 'tipo_doc_proveedor')
            ->orderByDesc('suma_total')
            ->limit(self::TOP_PROVEEDORES)
            ->get()
            ->map(fn ($p) => [
                'num_doc'       => $p->num_doc_proveedor,
                'tipo_doc'      => $p->tipo_doc_proveedor,
                'razon_social'  => $p->razon_social,
                'cantidad_cp'   => (int) $p->cantidad_cp,
                'suma_bi'       => (float) $p->suma_bi,
                'suma_igv'      => (float) $p->suma_igv,
                'suma_total'    => (float) $p->suma_total,
            ])
            ->toArray();
    }

    // ==================================================================
    // 3. Por tipo de CP
    // ==================================================================
    private function analizarPorTipo(Tenant $tenant, string $periodo): array
    {
        $tipos = [
            '01' => 'Factura',
            '03' => 'Boleta',
            '07' => 'Nota de Crédito',
            '08' => 'Nota de Débito',
            '14' => 'Recibo Servicios Públicos',
            '00' => 'Otros',
        ];

        return SireComprobante::query()
            ->where('tenant_id', $tenant->id)
            ->where('per_tributario', $periodo)
            ->selectRaw('cod_tipo_cdp, COUNT(*) as cantidad, SUM(mto_total) as monto')
            ->groupBy('cod_tipo_cdp')
            ->orderByDesc('cantidad')
            ->get()
            ->map(fn ($r) => [
                'cod'       => $r->cod_tipo_cdp,
                'nombre'    => $tipos[$r->cod_tipo_cdp] ?? 'Desconocido',
                'cantidad'  => (int) $r->cantidad,
                'monto'     => (float) $r->monto,
            ])
            ->toArray();
    }

    // ==================================================================
    // 4. Cruce con BD local
    // ==================================================================
    private function cruzarConBdLocal(Tenant $tenant, string $periodo): array
    {
        // Proveedores únicos del RCE
        $rucsProveedores = SireComprobante::query()
            ->where('tenant_id', $tenant->id)
            ->where('per_tributario', $periodo)
            ->distinct()
            ->pluck('num_doc_proveedor')
            ->filter()
            ->values();

        if ($rucsProveedores->isEmpty()) {
            return [
                'proveedores_en_clients'     => 0,
                'proveedores_no_en_clients'  => 0,
                'relacion_bidireccional'     => [],
            ];
        }

        // 1) ¿Qué proveedores existen también como clients del tenant?
        $clientsExistentes = DB::table('clients')
            ->where('tenant_id', $tenant->id)
            ->whereIn('numero_documento', $rucsProveedores)
            ->select('numero_documento', 'razon_social', 'id')
            ->get()
            ->keyBy('numero_documento');

        // 2) Para esos, ¿les emitimos facturas/boletas en el mismo periodo?
        $year = (int) substr($periodo, 0, 4);
        $month = (int) substr($periodo, 4, 2);

        $ventasALosProveedores = DB::table('invoices')
            ->where('tenant_id', $tenant->id)
            ->whereYear('fecha_emision', $year)
            ->whereMonth('fecha_emision', $month)
            ->whereIn('client_num_doc', $clientsExistentes->keys())
            ->selectRaw('client_num_doc, COUNT(*) as cnt_facturas, SUM(mto_imp_venta) as mto_ventas')
            ->groupBy('client_num_doc')
            ->get()
            ->keyBy('client_num_doc');

        $bidireccional = [];
        foreach ($clientsExistentes as $ruc => $client) {
            $ventas = $ventasALosProveedores[$ruc] ?? null;
            $bidireccional[] = [
                'num_doc'             => $ruc,
                'razon_social'        => $client->razon_social,
                'client_id_local'     => $client->id,
                'me_emitio_en_periodo'=> true,
                'le_emiti_en_periodo' => $ventas !== null,
                'cnt_facturas_venta'  => (int) ($ventas->cnt_facturas ?? 0),
                'monto_ventas'        => (float) ($ventas->mto_ventas ?? 0),
            ];
        }

        return [
            'proveedores_totales'        => $rucsProveedores->count(),
            'proveedores_en_clients'     => $clientsExistentes->count(),
            'proveedores_no_en_clients'  => $rucsProveedores->count() - $clientsExistentes->count(),
            'relacion_bidireccional'     => $bidireccional,
        ];
    }

    // ==================================================================
    // 5. Alertas
    // ==================================================================
    private function detectarAlertas(Tenant $tenant, string $periodo, array $totales): array
    {
        return [
            'duplicados'       => $this->detectarDuplicados($tenant, $periodo),
            'outliers_monto'   => $this->detectarOutliers($tenant, $periodo, $totales['promedio_monto'] ?? 0),
            'con_inconsistencia_sunat' => $this->contarInconsistencias($tenant, $periodo),
        ];
    }

    private function detectarDuplicados(Tenant $tenant, string $periodo): array
    {
        // Duplicados: mismo proveedor + mismo tipo + misma serie + mismo número
        return SireComprobante::query()
            ->where('tenant_id', $tenant->id)
            ->where('per_tributario', $periodo)
            ->selectRaw('
                num_doc_proveedor, cod_tipo_cdp, num_serie_cdp, num_cdp,
                COUNT(*) as veces
            ')
            ->groupBy('num_doc_proveedor', 'cod_tipo_cdp', 'num_serie_cdp', 'num_cdp')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(fn ($d) => [
                'proveedor'    => $d->num_doc_proveedor,
                'cod_tipo_cdp' => $d->cod_tipo_cdp,
                'serie'        => $d->num_serie_cdp,
                'numero'       => $d->num_cdp,
                'apariciones'  => (int) $d->veces,
            ])
            ->toArray();
    }

    private function detectarOutliers(Tenant $tenant, string $periodo, float $promedio): array
    {
        if ($promedio <= 0) {
            return [];
        }

        $umbral = $promedio * self::UMBRAL_OUTLIER_FACTOR;

        return SireComprobante::query()
            ->where('tenant_id', $tenant->id)
            ->where('per_tributario', $periodo)
            ->where('mto_total', '>', $umbral)
            ->limit(20)
            ->get(['id', 'car_sunat', 'num_doc_proveedor', 'razon_social_proveedor', 'mto_total'])
            ->map(fn ($o) => [
                'id'            => $o->id,
                'car_sunat'     => $o->car_sunat,
                'proveedor'     => $o->num_doc_proveedor,
                'razon_social'  => $o->razon_social_proveedor,
                'mto_total'     => (float) $o->mto_total,
                'veces_promedio'=> round($o->mto_total / $promedio, 1),
            ])
            ->toArray();
    }

    private function contarInconsistencias(Tenant $tenant, string $periodo): int
    {
        return SireComprobante::query()
            ->where('tenant_id', $tenant->id)
            ->where('per_tributario', $periodo)
            ->whereNotNull('cod_inconsistencia')
            ->count();
    }

    // ==================================================================
    // Persistencia
    // ==================================================================
    private function persistir(Tenant $tenant, ReconciliationReport $report): void
    {
        SireReconciliationLog::create([
            'tenant_id'          => $tenant->id,
            'per_tributario'     => $report->perTributario,
            'total_sunat'        => $report->totales['total_comprobantes'] ?? 0,
            'total_local'        => 0, // sin tabla de compras aún
            'match_count'        => $report->cruceLocal['proveedores_en_clients'] ?? 0,
            'only_sunat_count'   => $report->cruceLocal['proveedores_no_en_clients'] ?? 0,
            'only_local_count'   => 0,
            'diff_amount_count'  => count($report->alertas['outliers_monto'] ?? []),
            'diff_total_monto'   => 0,
            'details'            => $report->toArray(),
            'run_at'             => now(),
        ]);
    }
}

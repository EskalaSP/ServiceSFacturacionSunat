<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Dashboard general (vista completa).
     * Parámetros opcionales:
     *   - mes=YYYY-MM        → periodo mensual (default: mes actual)
     *   - desde=YYYY-MM-DD   → override rango libre
     *   - hasta=YYYY-MM-DD   → override rango libre
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        [$startDate, $endDate, $label] = $this->resolvePeriod($request);

        $prevStart = Carbon::parse($startDate)->subMonth()->startOfMonth()->toDateString();
        $prevEnd = Carbon::parse($startDate)->subMonth()->endOfMonth()->toDateString();

        $tid = $tenant->id;

        // 1. Totales por tipo (UNION ALL)
        $summary = DB::select("
            SELECT tipo, COUNT(*) AS cnt, COALESCE(SUM(mto_imp_venta), 0) AS total FROM (
                SELECT 'invoices' AS tipo, mto_imp_venta FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT 'boletas', mto_imp_venta FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT 'credit_notes', mto_imp_venta FROM credit_notes
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT 'debit_notes', mto_imp_venta FROM debit_notes
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT 'sale_notes', mto_imp_venta FROM internal_documents
                WHERE tenant_id = ? AND type = 'sale_note' AND fecha_emision BETWEEN ? AND ? AND status != 'anulada'
            ) AS docs GROUP BY tipo
        ", array_merge(
            [$tid, $startDate, $endDate],
            [$tid, $startDate, $endDate],
            [$tid, $startDate, $endDate],
            [$tid, $startDate, $endDate],
            [$tid, $startDate, $endDate],
        ));

        $summaryMap = [];
        foreach ($summary as $row) {
            $summaryMap[$row->tipo] = ['count' => (int) $row->cnt, 'total' => round((float) $row->total, 2)];
        }
        foreach (['invoices', 'boletas', 'credit_notes', 'debit_notes', 'sale_notes'] as $type) {
            $summaryMap[$type] ??= ['count' => 0, 'total' => 0];
        }

        // 2. Mes anterior (comparación)
        $prevSummary = DB::select("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(mto_imp_venta), 0) AS total FROM (
                SELECT mto_imp_venta FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT mto_imp_venta FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
            ) AS docs
        ", [$tid, $prevStart, $prevEnd, $tid, $prevStart, $prevEnd]);

        $prevTotal = (float) ($prevSummary[0]->total ?? 0);
        $prevDocs = (int) ($prevSummary[0]->cnt ?? 0);

        $currentTotal = ($summaryMap['invoices']['total'] ?? 0) + ($summaryMap['boletas']['total'] ?? 0) + ($summaryMap['sale_notes']['total'] ?? 0);
        $currentDocs = ($summaryMap['invoices']['count'] ?? 0) + ($summaryMap['boletas']['count'] ?? 0) + ($summaryMap['sale_notes']['count'] ?? 0);

        // 3. Gráfico diario
        $dailyChart = DB::select("
            SELECT day, COALESCE(SUM(total), 0) AS ventas FROM (
                SELECT DATE(fecha_emision) AS day, mto_imp_venta AS total FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT DATE(fecha_emision), mto_imp_venta FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT DATE(fecha_emision), mto_imp_venta FROM internal_documents
                WHERE tenant_id = ? AND type = 'sale_note' AND fecha_emision BETWEEN ? AND ? AND status != 'anulada'
            ) AS sales GROUP BY day ORDER BY day
        ", [$tid, $startDate, $endDate, $tid, $startDate, $endDate, $tid, $startDate, $endDate]);

        // 4. Top 10 productos
        $topProducts = DB::select("
            SELECT descripcion AS name, ROUND(SUM(cantidad * mto_precio_unitario), 2) AS total
            FROM (
                SELECT ii.descripcion, ii.cantidad, ii.mto_precio_unitario
                FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
                WHERE i.tenant_id = ? AND i.fecha_emision BETWEEN ? AND ? AND i.deleted_at IS NULL
                UNION ALL
                SELECT bi.descripcion, bi.cantidad, bi.mto_precio_unitario
                FROM boleta_items bi JOIN boletas b ON b.id = bi.boleta_id
                WHERE b.tenant_id = ? AND b.fecha_emision BETWEEN ? AND ? AND b.deleted_at IS NULL
            ) combined GROUP BY descripcion ORDER BY total DESC LIMIT 10
        ", [$tid, $startDate, $endDate, $tid, $startDate, $endDate]);

        // 5. Top 10 clientes
        $topClients = DB::select("
            SELECT client_razon_social AS name, ROUND(SUM(mto_imp_venta), 2) AS total, COUNT(*) AS docs
            FROM (
                SELECT client_razon_social, mto_imp_venta FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT client_razon_social, mto_imp_venta FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
            ) combined GROUP BY client_razon_social ORDER BY total DESC LIMIT 10
        ", [$tid, $startDate, $endDate, $tid, $startDate, $endDate]);

        return $this->success([
            'periodo' => ['desde' => $startDate, 'hasta' => $endDate, 'label' => $label],
            'facturas' => $summaryMap['invoices'],
            'boletas' => $summaryMap['boletas'],
            'notas_credito' => $summaryMap['credit_notes'],
            'notas_debito' => $summaryMap['debit_notes'],
            'notas_venta' => $summaryMap['sale_notes'],
            'grafico' => array_map(fn ($r) => [
                'fecha' => $r->day,
                'ventas' => round((float) $r->ventas, 2),
            ], $dailyChart),
            'top_productos' => array_map(fn ($r) => [
                'nombre' => mb_substr($r->name, 0, 60),
                'total' => (float) $r->total,
            ], $topProducts),
            'top_clientes' => array_map(fn ($r) => [
                'nombre' => mb_substr($r->name, 0, 60),
                'total' => (float) $r->total,
                'documentos' => (int) $r->docs,
            ], $topClients),
            'comparacion' => [
                'ventas_actuales' => round($currentTotal, 2),
                'ventas_anteriores' => round($prevTotal, 2),
                'cambio_ventas_porcentaje' => $this->pctChange($currentTotal, $prevTotal),
                'documentos_actuales' => $currentDocs,
                'documentos_anteriores' => $prevDocs,
                'cambio_documentos_porcentaje' => $this->pctChange($currentDocs, $prevDocs),
            ],
        ]);
    }

    /**
     * KPIs principales: hoy, ayer, semana, mes, año + ticket promedio + impuestos recaudados.
     */
    public function indicadores(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        $tid = $tenant->id;

        $hoy = now()->toDateString();
        $ayer = now()->subDay()->toDateString();
        $semanaInicio = now()->startOfWeek()->toDateString();
        $mesInicio = now()->startOfMonth()->toDateString();
        $anioInicio = now()->startOfYear()->toDateString();

        $mesAnteriorInicio = now()->subMonth()->startOfMonth()->toDateString();
        $mesAnteriorFin = now()->subMonth()->endOfMonth()->toDateString();
        $anioAnteriorInicio = now()->subYear()->startOfYear()->toDateString();
        $anioAnteriorFin = now()->subYear()->endOfYear()->toDateString();

        return $this->success([
            'hoy' => $this->kpiRango($tid, $hoy, $hoy),
            'ayer' => $this->kpiRango($tid, $ayer, $ayer),
            'semana' => $this->kpiRango($tid, $semanaInicio, $hoy),
            'mes_actual' => $this->kpiRango($tid, $mesInicio, $hoy),
            'mes_anterior' => $this->kpiRango($tid, $mesAnteriorInicio, $mesAnteriorFin),
            'anio_actual' => $this->kpiRango($tid, $anioInicio, $hoy),
            'anio_anterior' => $this->kpiRango($tid, $anioAnteriorInicio, $anioAnteriorFin),
            'crecimiento' => [
                'vs_ayer' => $this->pctChange(
                    $this->kpiRango($tid, $hoy, $hoy)['ventas'],
                    $this->kpiRango($tid, $ayer, $ayer)['ventas']
                ),
                'vs_mes_anterior' => $this->pctChange(
                    $this->kpiRango($tid, $mesInicio, $hoy)['ventas'],
                    $this->kpiRango($tid, $mesAnteriorInicio, $mesAnteriorFin)['ventas']
                ),
                'vs_anio_anterior' => $this->pctChange(
                    $this->kpiRango($tid, $anioInicio, $hoy)['ventas'],
                    $this->kpiRango($tid, $anioAnteriorInicio, $anioAnteriorFin)['ventas']
                ),
            ],
        ]);
    }

    /**
     * Estado SUNAT: breakdown por estado y tipo de documento + últimos rechazos.
     */
    public function estadoSunat(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        [$startDate, $endDate] = $this->resolvePeriod($request);
        $tid = $tenant->id;

        $breakdown = DB::select("
            SELECT tipo, sunat_status AS estado, COUNT(*) AS cnt FROM (
                SELECT 'facturas' AS tipo, sunat_status FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT 'boletas', sunat_status FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT 'notas_credito', sunat_status FROM credit_notes
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT 'notas_debito', sunat_status FROM debit_notes
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
            ) docs GROUP BY tipo, sunat_status
        ", array_merge(
            [$tid, $startDate, $endDate],
            [$tid, $startDate, $endDate],
            [$tid, $startDate, $endDate],
            [$tid, $startDate, $endDate],
        ));

        $porTipo = [];
        $porEstado = ['pendiente' => 0, 'enviado' => 0, 'aceptado' => 0, 'rechazado' => 0, 'anulado' => 0];
        $total = 0;
        foreach ($breakdown as $r) {
            $porTipo[$r->tipo][$r->estado] = (int) $r->cnt;
            $porEstado[$r->estado] = ($porEstado[$r->estado] ?? 0) + (int) $r->cnt;
            $total += (int) $r->cnt;
        }

        // Observados (código 3xxx en sunat_code aunque estén aceptados)
        $observados = DB::select("
            SELECT COUNT(*) AS cnt FROM (
                SELECT sunat_code FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                    AND sunat_status = 'aceptado' AND sunat_code IS NOT NULL AND sunat_code != '' AND sunat_code != '0'
                UNION ALL
                SELECT sunat_code FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                    AND sunat_status = 'aceptado' AND sunat_code IS NOT NULL AND sunat_code != '' AND sunat_code != '0'
            ) obs
        ", [$tid, $startDate, $endDate, $tid, $startDate, $endDate]);

        // Últimos rechazos (con motivo)
        $ultimosRechazos = DB::select("
            SELECT tipo, serie, correlativo, sunat_code, sunat_description, fecha_emision FROM (
                SELECT 'Factura' AS tipo, serie, correlativo, sunat_code, sunat_description, fecha_emision FROM invoices
                WHERE tenant_id = ? AND sunat_status = 'rechazado' AND deleted_at IS NULL
                UNION ALL
                SELECT 'Boleta', serie, correlativo, sunat_code, sunat_description, fecha_emision FROM boletas
                WHERE tenant_id = ? AND sunat_status = 'rechazado' AND deleted_at IS NULL
                UNION ALL
                SELECT 'Nota Crédito', serie, correlativo, sunat_code, sunat_description, fecha_emision FROM credit_notes
                WHERE tenant_id = ? AND sunat_status = 'rechazado' AND deleted_at IS NULL
                UNION ALL
                SELECT 'Nota Débito', serie, correlativo, sunat_code, sunat_description, fecha_emision FROM debit_notes
                WHERE tenant_id = ? AND sunat_status = 'rechazado' AND deleted_at IS NULL
            ) r ORDER BY fecha_emision DESC LIMIT 10
        ", [$tid, $tid, $tid, $tid]);

        return $this->success([
            'periodo' => ['desde' => $startDate, 'hasta' => $endDate],
            'total_documentos' => $total,
            'por_estado' => $porEstado,
            'por_tipo' => $porTipo,
            'observados' => (int) ($observados[0]->cnt ?? 0),
            'tasa_aceptacion' => $total > 0
                ? round(($porEstado['aceptado'] / $total) * 100, 1)
                : 0,
            'ultimos_rechazos' => array_map(fn ($r) => [
                'tipo' => $r->tipo,
                'numero' => $r->serie . '-' . str_pad((string) $r->correlativo, 8, '0', STR_PAD_LEFT),
                'codigo_sunat' => $r->sunat_code,
                'motivo' => mb_substr((string) $r->sunat_description, 0, 200),
                'fecha' => $r->fecha_emision,
            ], $ultimosRechazos),
        ]);
    }

    /**
     * Cobranzas: aging de cuentas por cobrar (0-30, 31-60, 61-90, >90).
     * Solo considera documentos aceptados por SUNAT con forma_pago=Credito.
     */
    public function cobranzas(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        $tid = $tenant->id;
        $hoy = now()->toDateString();

        // Totales: pagado / pendiente / parcial (todos los aceptados)
        $totales = DB::select("
            SELECT payment_status, COUNT(*) AS cnt,
                   COALESCE(SUM(mto_imp_venta), 0) AS monto_total,
                   COALESCE(SUM(monto_pagado), 0) AS monto_pagado,
                   COALESCE(SUM(mto_imp_venta - monto_pagado), 0) AS saldo
            FROM (
                SELECT payment_status, mto_imp_venta, monto_pagado FROM invoices
                WHERE tenant_id = ? AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso') AND deleted_at IS NULL
                UNION ALL
                SELECT payment_status, mto_imp_venta, monto_pagado FROM boletas
                WHERE tenant_id = ? AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso') AND deleted_at IS NULL
            ) d GROUP BY payment_status
        ", [$tid, $tid]);

        $resumen = ['pagado' => [], 'parcial' => [], 'pendiente' => []];
        foreach ($totales as $r) {
            $resumen[$r->payment_status] = [
                'documentos' => (int) $r->cnt,
                'monto_total' => round((float) $r->monto_total, 2),
                'monto_pagado' => round((float) $r->monto_pagado, 2),
                'saldo' => round((float) $r->saldo, 2),
            ];
        }
        foreach (['pagado', 'parcial', 'pendiente'] as $k) {
            $resumen[$k] = $resumen[$k] ?: ['documentos' => 0, 'monto_total' => 0, 'monto_pagado' => 0, 'saldo' => 0];
        }

        // Aging de saldos pendientes — compatible con MySQL y PostgreSQL
        $driver = DB::connection()->getDriverName();
        // Días entre hoy y la fecha de vencimiento (positivo = vencido, negativo = por vencer)
        $diasExpr = $driver === 'pgsql'
            ? "(?::date - fecha_vencimiento)"
            : "DATEDIFF(?, fecha_vencimiento)";

        $aging = DB::select("
            SELECT
                SUM(CASE WHEN {$diasExpr} < 0 THEN saldo ELSE 0 END) AS por_vencer,
                SUM(CASE WHEN {$diasExpr} BETWEEN 0 AND 30 THEN saldo ELSE 0 END) AS vencido_0_30,
                SUM(CASE WHEN {$diasExpr} BETWEEN 31 AND 60 THEN saldo ELSE 0 END) AS vencido_31_60,
                SUM(CASE WHEN {$diasExpr} BETWEEN 61 AND 90 THEN saldo ELSE 0 END) AS vencido_61_90,
                SUM(CASE WHEN {$diasExpr} > 90 THEN saldo ELSE 0 END) AS vencido_mas_90,
                COUNT(*) AS documentos_con_saldo
            FROM (
                SELECT fecha_vencimiento, (mto_imp_venta - monto_pagado) AS saldo FROM invoices
                WHERE tenant_id = ? AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso') AND deleted_at IS NULL
                    AND payment_status IN ('pendiente', 'parcial') AND fecha_vencimiento IS NOT NULL
                UNION ALL
                SELECT fecha_vencimiento, (mto_imp_venta - monto_pagado) FROM boletas
                WHERE tenant_id = ? AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso') AND deleted_at IS NULL
                    AND payment_status IN ('pendiente', 'parcial') AND fecha_vencimiento IS NOT NULL
            ) d
        ", [$hoy, $hoy, $hoy, $hoy, $hoy, $tid, $tid]);

        $agingRow = $aging[0] ?? null;

        // Top 10 clientes deudores
        $topDeudores = DB::select("
            SELECT client_num_doc AS ruc, client_razon_social AS cliente,
                   COUNT(*) AS documentos,
                   ROUND(SUM(mto_imp_venta - monto_pagado), 2) AS saldo
            FROM (
                SELECT client_num_doc, client_razon_social, mto_imp_venta, monto_pagado FROM invoices
                WHERE tenant_id = ? AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso') AND deleted_at IS NULL
                    AND payment_status IN ('pendiente', 'parcial')
                UNION ALL
                SELECT client_num_doc, client_razon_social, mto_imp_venta, monto_pagado FROM boletas
                WHERE tenant_id = ? AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso') AND deleted_at IS NULL
                    AND payment_status IN ('pendiente', 'parcial')
            ) d GROUP BY client_num_doc, client_razon_social
            HAVING SUM(mto_imp_venta - monto_pagado) > 0 ORDER BY saldo DESC LIMIT 10
        ", [$tid, $tid]);

        return $this->success([
            'resumen' => $resumen,
            'aging' => [
                'por_vencer' => round((float) ($agingRow->por_vencer ?? 0), 2),
                'vencido_0_30' => round((float) ($agingRow->vencido_0_30 ?? 0), 2),
                'vencido_31_60' => round((float) ($agingRow->vencido_31_60 ?? 0), 2),
                'vencido_61_90' => round((float) ($agingRow->vencido_61_90 ?? 0), 2),
                'vencido_mas_90' => round((float) ($agingRow->vencido_mas_90 ?? 0), 2),
                'documentos_con_saldo' => (int) ($agingRow->documentos_con_saldo ?? 0),
            ],
            'top_deudores' => array_map(fn ($r) => [
                'ruc' => $r->ruc,
                'cliente' => mb_substr((string) $r->cliente, 0, 60),
                'documentos' => (int) $r->documentos,
                'saldo' => (float) $r->saldo,
            ], $topDeudores),
        ]);
    }

    /**
     * Ventas mensuales: últimos 12 meses vs mismo periodo año anterior.
     */
    public function ventasMensuales(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        $tid = $tenant->id;

        $inicio = now()->subMonths(11)->startOfMonth()->toDateString();
        $fin = now()->endOfMonth()->toDateString();
        $inicioAnterior = now()->subYear()->subMonths(11)->startOfMonth()->toDateString();
        $finAnterior = now()->subYear()->endOfMonth()->toDateString();

        $actual = $this->ventasPorMes($tid, $inicio, $fin);
        $anterior = $this->ventasPorMes($tid, $inicioAnterior, $finAnterior);

        $meses = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $key = $m->format('Y-m');
            $keyAnt = $m->copy()->subYear()->format('Y-m');
            $meses[] = [
                'mes' => $key,
                'mes_label' => $this->mesLabel($m->month) . ' ' . $m->year,
                'ventas' => round((float) ($actual[$key] ?? 0), 2),
                'ventas_anio_anterior' => round((float) ($anterior[$keyAnt] ?? 0), 2),
            ];
        }

        $totalActual = array_sum(array_map(fn ($m) => $m['ventas'], $meses));
        $totalAnterior = array_sum(array_map(fn ($m) => $m['ventas_anio_anterior'], $meses));

        return $this->success([
            'meses' => $meses,
            'total_12_meses' => round($totalActual, 2),
            'total_12_meses_anterior' => round($totalAnterior, 2),
            'crecimiento_porcentaje' => $this->pctChange($totalActual, $totalAnterior),
            'promedio_mensual' => round($totalActual / 12, 2),
        ]);
    }

    /**
     * Ranking por sucursal (cod_local).
     */
    public function porSucursal(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        [$startDate, $endDate] = $this->resolvePeriod($request);
        $tid = $tenant->id;

        $data = DB::select("
            SELECT s.cod_local, s.nombre,
                   COALESCE(SUM(CASE WHEN d.tipo = 'factura' THEN d.monto ELSE 0 END), 0) AS facturas_monto,
                   COALESCE(SUM(CASE WHEN d.tipo = 'factura' THEN 1 ELSE 0 END), 0) AS facturas_cnt,
                   COALESCE(SUM(CASE WHEN d.tipo = 'boleta' THEN d.monto ELSE 0 END), 0) AS boletas_monto,
                   COALESCE(SUM(CASE WHEN d.tipo = 'boleta' THEN 1 ELSE 0 END), 0) AS boletas_cnt,
                   COALESCE(SUM(d.monto), 0) AS total
            FROM sucursales s
            LEFT JOIN (
                SELECT 'factura' AS tipo, cod_local, mto_imp_venta AS monto FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso')
                UNION ALL
                SELECT 'boleta', cod_local, mto_imp_venta FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso')
            ) d ON d.cod_local = s.cod_local
            WHERE s.tenant_id = ?
            GROUP BY s.cod_local, s.nombre
            ORDER BY total DESC
        ", [$tid, $startDate, $endDate, $tid, $startDate, $endDate, $tid]);

        $totalGeneral = array_sum(array_map(fn ($r) => (float) $r->total, $data));

        $sucursales = array_map(fn ($r) => [
            'cod_local' => $r->cod_local,
            'nombre' => $r->nombre,
            'facturas' => ['cantidad' => (int) $r->facturas_cnt, 'monto' => round((float) $r->facturas_monto, 2)],
            'boletas' => ['cantidad' => (int) $r->boletas_cnt, 'monto' => round((float) $r->boletas_monto, 2)],
            'total' => round((float) $r->total, 2),
            'share_porcentaje' => $totalGeneral > 0 ? round(((float) $r->total / $totalGeneral) * 100, 1) : 0,
        ], $data);

        return $this->success([
            'periodo' => ['desde' => $startDate, 'hasta' => $endDate],
            'total_general' => round($totalGeneral, 2),
            'sucursales' => $sucursales,
        ]);
    }

    /**
     * Desglose por moneda (PEN / USD / otras).
     */
    public function porMoneda(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        [$startDate, $endDate] = $this->resolvePeriod($request);
        $tid = $tenant->id;

        $data = DB::select("
            SELECT tipo_moneda, COUNT(*) AS cnt, COALESCE(SUM(mto_imp_venta), 0) AS total,
                   COALESCE(SUM(mto_igv), 0) AS igv
            FROM (
                SELECT tipo_moneda, mto_imp_venta, mto_igv FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso')
                UNION ALL
                SELECT tipo_moneda, mto_imp_venta, mto_igv FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso')
            ) d GROUP BY tipo_moneda ORDER BY total DESC
        ", [$tid, $startDate, $endDate, $tid, $startDate, $endDate]);

        return $this->success([
            'periodo' => ['desde' => $startDate, 'hasta' => $endDate],
            'monedas' => array_map(fn ($r) => [
                'moneda' => $r->tipo_moneda,
                'documentos' => (int) $r->cnt,
                'total' => round((float) $r->total, 2),
                'igv' => round((float) $r->igv, 2),
            ], $data),
        ]);
    }

    /**
     * Clientes: top vendidos, nuevos del mes, recurrentes.
     */
    public function clientes(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        [$startDate, $endDate] = $this->resolvePeriod($request);
        $tid = $tenant->id;

        // Top 20 clientes por ventas
        $top = DB::select("
            SELECT client_num_doc AS ruc, client_razon_social AS nombre, client_tipo_doc AS tipo_doc,
                   COUNT(*) AS documentos,
                   ROUND(SUM(mto_imp_venta), 2) AS total
            FROM (
                SELECT client_num_doc, client_razon_social, client_tipo_doc, mto_imp_venta FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso')
                UNION ALL
                SELECT client_num_doc, client_razon_social, client_tipo_doc, mto_imp_venta FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso')
            ) d GROUP BY client_num_doc, client_razon_social, client_tipo_doc
            ORDER BY total DESC LIMIT 20
        ", [$tid, $startDate, $endDate, $tid, $startDate, $endDate]);

        // Clientes únicos en el periodo
        $unicos = DB::select("
            SELECT COUNT(DISTINCT client_num_doc) AS cnt FROM (
                SELECT client_num_doc FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT client_num_doc FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
            ) d
        ", [$tid, $startDate, $endDate, $tid, $startDate, $endDate]);

        // Clientes nuevos: registrados en el periodo
        // $startDate y $endDate ya vienen con hora completa de resolvePeriod()
        $nuevos = DB::table('clients')
            ->where('tenant_id', $tid)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $totalRegistrados = DB::table('clients')->where('tenant_id', $tid)->count();

        // Recurrentes: clientes con >1 documento en el periodo
        $recurrentes = DB::select("
            SELECT COUNT(*) AS cnt FROM (
                SELECT client_num_doc FROM (
                    SELECT client_num_doc FROM invoices
                    WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                    UNION ALL
                    SELECT client_num_doc FROM boletas
                    WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                ) d GROUP BY client_num_doc HAVING COUNT(*) > 1
            ) r
        ", [$tid, $startDate, $endDate, $tid, $startDate, $endDate]);

        return $this->success([
            'periodo' => ['desde' => $startDate, 'hasta' => $endDate],
            'total_registrados' => $totalRegistrados,
            'clientes_unicos_periodo' => (int) ($unicos[0]->cnt ?? 0),
            'clientes_nuevos' => $nuevos,
            'clientes_recurrentes' => (int) ($recurrentes[0]->cnt ?? 0),
            'top_20' => array_map(fn ($r) => [
                'ruc' => $r->ruc,
                'tipo_documento' => $r->tipo_doc,
                'nombre' => mb_substr((string) $r->nombre, 0, 80),
                'documentos' => (int) $r->documentos,
                'total' => (float) $r->total,
            ], $top),
        ]);
    }

    /**
     * Productos: top por venta, top por cantidad, distribución por tipo IGV.
     */
    public function productos(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        [$startDate, $endDate] = $this->resolvePeriod($request);
        $tid = $tenant->id;

        $topVenta = DB::select("
            SELECT descripcion, codigo,
                   ROUND(SUM(cantidad), 2) AS cantidad,
                   ROUND(SUM(cantidad * mto_precio_unitario), 2) AS total
            FROM (
                SELECT ii.descripcion, ii.codigo, ii.cantidad, ii.mto_precio_unitario
                FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
                WHERE i.tenant_id = ? AND i.fecha_emision BETWEEN ? AND ? AND i.deleted_at IS NULL
                UNION ALL
                SELECT bi.descripcion, bi.codigo, bi.cantidad, bi.mto_precio_unitario
                FROM boleta_items bi JOIN boletas b ON b.id = bi.boleta_id
                WHERE b.tenant_id = ? AND b.fecha_emision BETWEEN ? AND ? AND b.deleted_at IS NULL
            ) c GROUP BY descripcion, codigo ORDER BY total DESC LIMIT 20
        ", [$tid, $startDate, $endDate, $tid, $startDate, $endDate]);

        $topCantidad = DB::select("
            SELECT descripcion, codigo,
                   ROUND(SUM(cantidad), 2) AS cantidad,
                   ROUND(SUM(cantidad * mto_precio_unitario), 2) AS total
            FROM (
                SELECT ii.descripcion, ii.codigo, ii.cantidad, ii.mto_precio_unitario
                FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
                WHERE i.tenant_id = ? AND i.fecha_emision BETWEEN ? AND ? AND i.deleted_at IS NULL
                UNION ALL
                SELECT bi.descripcion, bi.codigo, bi.cantidad, bi.mto_precio_unitario
                FROM boleta_items bi JOIN boletas b ON b.id = bi.boleta_id
                WHERE b.tenant_id = ? AND b.fecha_emision BETWEEN ? AND ? AND b.deleted_at IS NULL
            ) c GROUP BY descripcion, codigo ORDER BY cantidad DESC LIMIT 20
        ", [$tid, $startDate, $endDate, $tid, $startDate, $endDate]);

        // Distribución por tipo de afectación IGV (10, 20, 30, etc.)
        $porTipoIgv = DB::select("
            SELECT tip_afe_igv, COUNT(*) AS items,
                   ROUND(SUM(cantidad * mto_precio_unitario), 2) AS total
            FROM (
                SELECT ii.tip_afe_igv, ii.cantidad, ii.mto_precio_unitario
                FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
                WHERE i.tenant_id = ? AND i.fecha_emision BETWEEN ? AND ? AND i.deleted_at IS NULL
                UNION ALL
                SELECT bi.tip_afe_igv, bi.cantidad, bi.mto_precio_unitario
                FROM boleta_items bi JOIN boletas b ON b.id = bi.boleta_id
                WHERE b.tenant_id = ? AND b.fecha_emision BETWEEN ? AND ? AND b.deleted_at IS NULL
            ) c GROUP BY tip_afe_igv ORDER BY total DESC
        ", [$tid, $startDate, $endDate, $tid, $startDate, $endDate]);

        return $this->success([
            'periodo' => ['desde' => $startDate, 'hasta' => $endDate],
            'top_por_venta' => array_map(fn ($r) => [
                'codigo' => $r->codigo,
                'descripcion' => mb_substr((string) $r->descripcion, 0, 80),
                'cantidad' => (float) $r->cantidad,
                'total' => (float) $r->total,
            ], $topVenta),
            'top_por_cantidad' => array_map(fn ($r) => [
                'codigo' => $r->codigo,
                'descripcion' => mb_substr((string) $r->descripcion, 0, 80),
                'cantidad' => (float) $r->cantidad,
                'total' => (float) $r->total,
            ], $topCantidad),
            'por_tipo_igv' => array_map(fn ($r) => [
                'codigo' => $r->tip_afe_igv,
                'descripcion' => $this->tipoAfectacionLabel($r->tip_afe_igv),
                'items' => (int) $r->items,
                'total' => (float) $r->total,
            ], $porTipoIgv),
        ]);
    }

    /**
     * Feed de los últimos 20 documentos emitidos (cualquier tipo).
     */
    public function documentosRecientes(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        $tid = $tenant->id;
        $limit = min((int) $request->query('limit', 20), 100);

        $docs = DB::select("
            SELECT tipo, id, serie, correlativo, fecha_emision, created_at,
                   client_num_doc, client_razon_social, tipo_moneda, mto_imp_venta, sunat_status
            FROM (
                SELECT 'factura' AS tipo, id, serie, correlativo, fecha_emision, created_at,
                       client_num_doc, client_razon_social, tipo_moneda, mto_imp_venta, sunat_status
                FROM invoices WHERE tenant_id = ? AND deleted_at IS NULL
                UNION ALL
                SELECT 'boleta', id, serie, correlativo, fecha_emision, created_at,
                       client_num_doc, client_razon_social, tipo_moneda, mto_imp_venta, sunat_status
                FROM boletas WHERE tenant_id = ? AND deleted_at IS NULL
                UNION ALL
                SELECT 'nota_credito', id, serie, correlativo, fecha_emision, created_at,
                       client_num_doc, client_razon_social, tipo_moneda, mto_imp_venta, sunat_status
                FROM credit_notes WHERE tenant_id = ? AND deleted_at IS NULL
                UNION ALL
                SELECT 'nota_debito', id, serie, correlativo, fecha_emision, created_at,
                       client_num_doc, client_razon_social, tipo_moneda, mto_imp_venta, sunat_status
                FROM debit_notes WHERE tenant_id = ? AND deleted_at IS NULL
            ) d ORDER BY created_at DESC LIMIT ?
        ", [$tid, $tid, $tid, $tid, $limit]);

        return $this->success([
            'documentos' => array_map(fn ($r) => [
                'tipo' => $r->tipo,
                'id' => (int) $r->id,
                'numero' => $r->serie . '-' . str_pad((string) $r->correlativo, 8, '0', STR_PAD_LEFT),
                'fecha_emision' => $r->fecha_emision,
                'cliente_documento' => $r->client_num_doc,
                'cliente' => mb_substr((string) $r->client_razon_social, 0, 60),
                'moneda' => $r->tipo_moneda,
                'total' => round((float) $r->mto_imp_venta, 2),
                'estado_sunat' => $r->sunat_status,
                'emitido_en' => $r->created_at,
            ], $docs),
        ]);
    }

    /**
     * Alertas del negocio: rechazos recientes, vencimientos próximos, vencidos, observados.
     */
    public function alertas(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        $tid = $tenant->id;
        $hoy = now()->toDateString();
        $en7Dias = now()->addDays(7)->toDateString();

        // Rechazados últimos 7 días
        $rechazadosRecientes = DB::select("
            SELECT COUNT(*) AS cnt FROM (
                SELECT id FROM invoices WHERE tenant_id = ? AND sunat_status = 'rechazado' AND updated_at >= ? AND deleted_at IS NULL
                UNION ALL
                SELECT id FROM boletas WHERE tenant_id = ? AND sunat_status = 'rechazado' AND updated_at >= ? AND deleted_at IS NULL
            ) r
        ", [$tid, now()->subDays(7)->toDateTimeString(), $tid, now()->subDays(7)->toDateTimeString()]);

        // Vencimientos en próximos 7 días
        $vencenPronto = DB::select("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(mto_imp_venta - monto_pagado), 0) AS monto FROM (
                SELECT mto_imp_venta, monto_pagado FROM invoices
                WHERE tenant_id = ? AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso') AND deleted_at IS NULL
                    AND payment_status IN ('pendiente', 'parcial')
                    AND fecha_vencimiento BETWEEN ? AND ?
                UNION ALL
                SELECT mto_imp_venta, monto_pagado FROM boletas
                WHERE tenant_id = ? AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso') AND deleted_at IS NULL
                    AND payment_status IN ('pendiente', 'parcial')
                    AND fecha_vencimiento BETWEEN ? AND ?
            ) v
        ", [$tid, $hoy, $en7Dias, $tid, $hoy, $en7Dias]);

        // Vencidos sin pago total
        $vencidos = DB::select("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(mto_imp_venta - monto_pagado), 0) AS monto FROM (
                SELECT mto_imp_venta, monto_pagado FROM invoices
                WHERE tenant_id = ? AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso') AND deleted_at IS NULL
                    AND payment_status IN ('pendiente', 'parcial')
                    AND fecha_vencimiento < ?
                UNION ALL
                SELECT mto_imp_venta, monto_pagado FROM boletas
                WHERE tenant_id = ? AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso') AND deleted_at IS NULL
                    AND payment_status IN ('pendiente', 'parcial')
                    AND fecha_vencimiento < ?
            ) v
        ", [$tid, $hoy, $tid, $hoy]);

        // Pendientes de envío (no enviados a SUNAT)
        $pendientesEnvio = DB::select("
            SELECT COUNT(*) AS cnt FROM (
                SELECT id FROM invoices WHERE tenant_id = ? AND sunat_status IN ('pendiente', 'enviado') AND deleted_at IS NULL
                UNION ALL
                SELECT id FROM boletas WHERE tenant_id = ? AND sunat_status IN ('pendiente', 'enviado') AND deleted_at IS NULL
                UNION ALL
                SELECT id FROM credit_notes WHERE tenant_id = ? AND sunat_status IN ('pendiente', 'enviado') AND deleted_at IS NULL
                UNION ALL
                SELECT id FROM debit_notes WHERE tenant_id = ? AND sunat_status IN ('pendiente', 'enviado') AND deleted_at IS NULL
            ) p
        ", [$tid, $tid, $tid, $tid]);

        // Series próximas a agotarse (correlativo > 90M — SUNAT permite hasta 99999999)
        $seriesPorAgotar = DB::table('series')
            ->where('tenant_id', $tid)
            ->where('correlativo', '>', 90000000)
            ->select('tipo_documento', 'serie', 'correlativo')
            ->get();

        return $this->success([
            'rechazados_7_dias' => [
                'cantidad' => (int) ($rechazadosRecientes[0]->cnt ?? 0),
            ],
            'vencen_proximos_7_dias' => [
                'cantidad' => (int) ($vencenPronto[0]->cnt ?? 0),
                'monto' => round((float) ($vencenPronto[0]->monto ?? 0), 2),
            ],
            'vencidos' => [
                'cantidad' => (int) ($vencidos[0]->cnt ?? 0),
                'monto' => round((float) ($vencidos[0]->monto ?? 0), 2),
            ],
            'pendientes_envio_sunat' => [
                'cantidad' => (int) ($pendientesEnvio[0]->cnt ?? 0),
            ],
            'series_por_agotar' => $seriesPorAgotar->toArray(),
        ]);
    }

    // ======================== Helpers privados ========================

    private function resolvePeriod(Request $request): array
    {
        if ($request->filled('desde') && $request->filled('hasta')) {
            $desde = $request->query('desde');
            $hasta = $request->query('hasta');
            return [$desde . ' 00:00:00', $hasta . ' 23:59:59', "$desde al $hasta"];
        }

        $mes = $request->query('mes', now()->format('Y-m'));
        $desde = $mes . '-01';
        $hasta = Carbon::parse($desde)->endOfMonth()->toDateString();
        return [$desde . ' 00:00:00', $hasta . ' 23:59:59', $this->mesLabel((int) substr($mes, 5, 2)) . ' ' . substr($mes, 0, 4)];
    }

    private function kpiRango(int $tid, string $desde, string $hasta): array
    {
        // Normalizar rango a datetime completo (fecha_emision es datetime)
        if (strlen($desde) === 10) $desde .= ' 00:00:00';
        if (strlen($hasta) === 10) $hasta .= ' 23:59:59';

        $row = DB::select("
            SELECT COUNT(*) AS docs,
                   COALESCE(SUM(mto_imp_venta), 0) AS ventas,
                   COALESCE(SUM(mto_igv), 0) AS igv,
                   COALESCE(SUM(mto_oper_gratuitas), 0) AS gratuitas,
                   COALESCE(SUM(mto_icbper), 0) AS icbper
            FROM (
                SELECT mto_imp_venta, mto_igv, mto_oper_gratuitas, mto_icbper FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso')
                UNION ALL
                SELECT mto_imp_venta, mto_igv, mto_oper_gratuitas, mto_icbper FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso')
            ) d
        ", [$tid, $desde, $hasta, $tid, $desde, $hasta]);

        $r = $row[0] ?? null;
        $docs = (int) ($r->docs ?? 0);
        $ventas = (float) ($r->ventas ?? 0);

        return [
            'documentos' => $docs,
            'ventas' => round($ventas, 2),
            'igv' => round((float) ($r->igv ?? 0), 2),
            'gratuitas' => round((float) ($r->gratuitas ?? 0), 2),
            'icbper' => round((float) ($r->icbper ?? 0), 2),
            'ticket_promedio' => $docs > 0 ? round($ventas / $docs, 2) : 0,
        ];
    }

    private function ventasPorMes(int $tid, string $desde, string $hasta): array
    {
        // Normalizar rango a datetime completo
        if (strlen($desde) === 10) $desde .= ' 00:00:00';
        if (strlen($hasta) === 10) $hasta .= ' 23:59:59';

        // Compatibilidad MySQL / PostgreSQL para extraer año-mes
        $driver = DB::connection()->getDriverName();
        $ymExpr = $driver === 'pgsql'
            ? "to_char(fecha_emision, 'YYYY-MM')"
            : "DATE_FORMAT(fecha_emision, '%Y-%m')";

        $rows = DB::select("
            SELECT ym, SUM(mto_imp_venta) AS total FROM (
                SELECT {$ymExpr} AS ym, mto_imp_venta FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso')
                UNION ALL
                SELECT {$ymExpr} AS ym, mto_imp_venta FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL AND sunat_status NOT IN ('anulado', 'rechazado', 'anulacion_en_proceso')
            ) d GROUP BY ym
        ", [$tid, $desde, $hasta, $tid, $desde, $hasta]);

        $out = [];
        foreach ($rows as $r) {
            $out[$r->ym] = (float) $r->total;
        }
        return $out;
    }

    private function pctChange(float $actual, float $anterior): float
    {
        if ($anterior <= 0) {
            return $actual > 0 ? 100.0 : 0.0;
        }
        return round((($actual - $anterior) / $anterior) * 100, 1);
    }

    private function mesLabel(int $m): string
    {
        return ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'][$m] ?? '';
    }

    private function tipoAfectacionLabel(?string $code): string
    {
        return match ($code) {
            '10' => 'Gravado',
            '11', '12', '13', '14', '15', '16' => 'Gravado - Retiro',
            '17' => 'Gravado - IVAP',
            '20' => 'Exonerado',
            '21' => 'Exonerado - Transferencia Gratuita',
            '30' => 'Inafecto',
            '31', '32', '33', '34', '35', '36' => 'Inafecto - Retiro',
            '40' => 'Exportación',
            default => 'Otro (' . ($code ?? '-') . ')',
        };
    }
}

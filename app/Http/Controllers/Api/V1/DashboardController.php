<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * All-in-one dashboard endpoint.
     * Returns summary, daily chart, top products, top clients in a SINGLE query batch.
     * No full document loading — pure SQL aggregation.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        $month = $request->query('month', now()->format('Y-m'));

        $startDate = $month . '-01';
        $endDate = \Carbon\Carbon::parse($startDate)->endOfMonth()->toDateString();

        $prevStart = \Carbon\Carbon::parse($startDate)->subMonth()->startOfMonth()->toDateString();
        $prevEnd = \Carbon\Carbon::parse($startDate)->subMonth()->endOfMonth()->toDateString();

        $tid = $tenant->id;

        // 1. Summary counts + totals (single query via UNION ALL)
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
            ) AS docs GROUP BY tipo
        ", array_merge(
            [$tid, $startDate, $endDate],
            [$tid, $startDate, $endDate],
            [$tid, $startDate, $endDate],
            [$tid, $startDate, $endDate],
        ));

        $summaryMap = [];
        foreach ($summary as $row) {
            $summaryMap[$row->tipo] = ['count' => (int) $row->cnt, 'total' => round((float) $row->total, 2)];
        }
        foreach (['invoices', 'boletas', 'credit_notes', 'debit_notes'] as $type) {
            $summaryMap[$type] ??= ['count' => 0, 'total' => 0];
        }

        // 2. Previous month summary (for comparison)
        $prevSummary = DB::select("
            SELECT tipo, COUNT(*) AS cnt, COALESCE(SUM(mto_imp_venta), 0) AS total FROM (
                SELECT 'invoices' AS tipo, mto_imp_venta FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT 'boletas', mto_imp_venta FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
            ) AS docs GROUP BY tipo
        ", [$tid, $prevStart, $prevEnd, $tid, $prevStart, $prevEnd]);

        $prevTotal = 0;
        $prevDocs = 0;
        foreach ($prevSummary as $row) {
            $prevTotal += (float) $row->total;
            $prevDocs += (int) $row->cnt;
        }

        $currentTotal = ($summaryMap['invoices']['total'] ?? 0) + ($summaryMap['boletas']['total'] ?? 0);
        $currentDocs = ($summaryMap['invoices']['count'] ?? 0) + ($summaryMap['boletas']['count'] ?? 0);

        // 3. Daily chart (ventas por día)
        $dailyChart = DB::select("
            SELECT day, COALESCE(SUM(total), 0) AS ventas FROM (
                SELECT DATE(fecha_emision) AS day, mto_imp_venta AS total FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT DATE(fecha_emision), mto_imp_venta FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
            ) AS sales GROUP BY day ORDER BY day
        ", [$tid, $startDate, $endDate, $tid, $startDate, $endDate]);

        // 4. Top 10 products
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

        // 5. Top 10 clients
        $topClients = DB::select("
            SELECT client_razon_social AS name, ROUND(SUM(mto_imp_venta), 2) AS total
            FROM (
                SELECT client_razon_social, mto_imp_venta FROM invoices
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
                UNION ALL
                SELECT client_razon_social, mto_imp_venta FROM boletas
                WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ? AND deleted_at IS NULL
            ) combined GROUP BY client_razon_social ORDER BY total DESC LIMIT 10
        ", [$tid, $startDate, $endDate, $tid, $startDate, $endDate]);

        return $this->success([
            'month' => $month,
            'invoices' => $summaryMap['invoices'],
            'boletas' => $summaryMap['boletas'],
            'credit_notes' => $summaryMap['credit_notes'],
            'debit_notes' => $summaryMap['debit_notes'],
            'chart' => array_map(fn ($r) => [
                'date' => $r->day,
                'ventas' => round((float) $r->ventas, 2),
            ], $dailyChart),
            'top_products' => array_map(fn ($r) => [
                'name' => mb_substr($r->name, 0, 40),
                'total' => (float) $r->total,
            ], $topProducts),
            'top_clients' => array_map(fn ($r) => [
                'name' => mb_substr($r->name, 0, 40),
                'total' => (float) $r->total,
            ], $topClients),
            'comparison' => [
                'current_sales' => round($currentTotal, 2),
                'previous_sales' => round($prevTotal, 2),
                'sales_change_percent' => $prevTotal > 0
                    ? round((($currentTotal - $prevTotal) / $prevTotal) * 100, 1)
                    : 0,
                'current_docs' => $currentDocs,
                'previous_docs' => $prevDocs,
                'docs_change_percent' => $prevDocs > 0
                    ? round((($currentDocs - $prevDocs) / $prevDocs) * 100, 1)
                    : 0,
            ],
        ]);
    }
}

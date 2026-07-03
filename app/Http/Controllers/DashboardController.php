<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\DispatchGuide;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Solo admins ven las métricas globales completas
        if (! $user?->is_admin) {
            return Inertia::render('dashboard', [
                'esAdmin' => false,
                'metricas' => null,
            ]);
        }

        return Inertia::render('dashboard', [
            'esAdmin' => true,
            'metricas' => $this->metricasGlobales(),
        ]);
    }

    private function metricasGlobales(): array
    {
        $hoy = Carbon::today('America/Lima');
        $inicioMes = $hoy->copy()->startOfMonth();
        $inicioMesAnterior = $hoy->copy()->subMonth()->startOfMonth();
        $finMesAnterior = $hoy->copy()->subMonth()->endOfMonth();

        return [
            'kpis' => $this->kpis($hoy, $inicioMes, $inicioMesAnterior, $finMesAnterior),
            'documentos_por_dia' => $this->documentosPorDia($hoy),
            'documentos_por_tipo' => $this->documentosPorTipo($inicioMes),
            'empresas_por_plan' => $this->empresasPorPlan(),
            'empresas_por_regimen' => $this->empresasPorRegimen(),
            'estado_sunat' => $this->estadoSunat($inicioMes),
            'top_empresas' => $this->topEmpresas($inicioMes),
            'empresas_por_entorno' => $this->empresasPorEntorno(),
            'periodo' => [
                'inicio_mes' => $inicioMes->format('Y-m-d'),
                'hoy' => $hoy->format('Y-m-d'),
            ],
        ];
    }

    private function kpis(Carbon $hoy, Carbon $inicioMes, Carbon $inicioMesAnt, Carbon $finMesAnt): array
    {
        $empresasTotal = Tenant::count();
        $empresasActivas = Tenant::where('is_active', true)->count();

        $docsHoy = $this->contarDocs($hoy->format('Y-m-d'), $hoy->format('Y-m-d'));
        $docsMes = $this->contarDocs($inicioMes->format('Y-m-d'), $hoy->format('Y-m-d'));
        $docsMesAnt = $this->contarDocs($inicioMesAnt->format('Y-m-d'), $finMesAnt->format('Y-m-d'));

        $ventasMes = $this->sumarVentas($inicioMes->format('Y-m-d'), $hoy->format('Y-m-d'));
        $ventasMesAnt = $this->sumarVentas($inicioMesAnt->format('Y-m-d'), $finMesAnt->format('Y-m-d'));

        $crecimientoDocs = $docsMesAnt > 0
            ? round((($docsMes - $docsMesAnt) / $docsMesAnt) * 100, 1)
            : null;

        $crecimientoVentas = $ventasMesAnt > 0
            ? round((($ventasMes - $ventasMesAnt) / $ventasMesAnt) * 100, 1)
            : null;

        return [
            'empresas_total' => $empresasTotal,
            'empresas_activas' => $empresasActivas,
            'docs_hoy' => $docsHoy,
            'docs_mes' => $docsMes,
            'docs_mes_anterior' => $docsMesAnt,
            'ventas_mes' => round($ventasMes, 2),
            'ventas_mes_anterior' => round($ventasMesAnt, 2),
            'crecimiento_docs' => $crecimientoDocs,
            'crecimiento_ventas' => $crecimientoVentas,
        ];
    }

    private function contarDocs(string $desde, string $hasta): int
    {
        return (int) (
            Invoice::whereBetween('fecha_emision', [$desde, $hasta])->count()
            + Boleta::whereBetween('fecha_emision', [$desde, $hasta])->count()
            + CreditNote::whereBetween('fecha_emision', [$desde, $hasta])->count()
            + DebitNote::whereBetween('fecha_emision', [$desde, $hasta])->count()
        );
    }

    private function sumarVentas(string $desde, string $hasta): float
    {
        return (float) (
            Invoice::whereBetween('fecha_emision', [$desde, $hasta])
                ->where('sunat_status', 'aceptado')
                ->sum('mto_imp_venta')
            + Boleta::whereBetween('fecha_emision', [$desde, $hasta])
                ->where('sunat_status', 'aceptado')
                ->sum('mto_imp_venta')
        );
    }

    private function documentosPorDia(Carbon $hoy): array
    {
        $inicio = $hoy->copy()->subDays(29);
        $data = [];

        // Genera todos los días para que no falten en el gráfico
        for ($fecha = $inicio->copy(); $fecha->lte($hoy); $fecha->addDay()) {
            $data[$fecha->format('Y-m-d')] = [
                'fecha' => $fecha->format('Y-m-d'),
                'facturas' => 0,
                'boletas' => 0,
                'notas' => 0,
            ];
        }

        $desde = $inicio->format('Y-m-d');
        $hasta = $hoy->format('Y-m-d');

        // Facturas por día
        Invoice::selectRaw('DATE(fecha_emision) as f, COUNT(*) as c')
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->groupBy('f')
            ->get()
            ->each(function ($row) use (&$data) {
                $key = Carbon::parse($row->f)->format('Y-m-d');
                if (isset($data[$key])) {
                    $data[$key]['facturas'] = (int) $row->c;
                }
            });

        // Boletas por día
        Boleta::selectRaw('DATE(fecha_emision) as f, COUNT(*) as c')
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->groupBy('f')
            ->get()
            ->each(function ($row) use (&$data) {
                $key = Carbon::parse($row->f)->format('Y-m-d');
                if (isset($data[$key])) {
                    $data[$key]['boletas'] = (int) $row->c;
                }
            });

        // Notas por día (NC + ND combinadas)
        $nc = CreditNote::selectRaw('DATE(fecha_emision) as f, COUNT(*) as c')
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->groupBy('f')
            ->pluck('c', 'f');

        $nd = DebitNote::selectRaw('DATE(fecha_emision) as f, COUNT(*) as c')
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->groupBy('f')
            ->pluck('c', 'f');

        foreach ($data as $key => &$row) {
            $row['notas'] = (int) ($nc[$key] ?? 0) + (int) ($nd[$key] ?? 0);
        }

        return array_values($data);
    }

    private function documentosPorTipo(Carbon $inicioMes): array
    {
        return [
            ['tipo' => 'Facturas', 'valor' => Invoice::where('fecha_emision', '>=', $inicioMes)->count()],
            ['tipo' => 'Boletas', 'valor' => Boleta::where('fecha_emision', '>=', $inicioMes)->count()],
            ['tipo' => 'NC', 'valor' => CreditNote::where('fecha_emision', '>=', $inicioMes)->count()],
            ['tipo' => 'ND', 'valor' => DebitNote::where('fecha_emision', '>=', $inicioMes)->count()],
            ['tipo' => 'Guías', 'valor' => DispatchGuide::where('fecha_emision', '>=', $inicioMes)->count()],
        ];
    }

    private function empresasPorPlan(): array
    {
        return Tenant::selectRaw('plan, COUNT(*) as total')
            ->groupBy('plan')
            ->get()
            ->map(fn ($r) => ['plan' => ucfirst($r->plan), 'total' => (int) $r->total])
            ->all();
    }

    private function empresasPorRegimen(): array
    {
        $labels = [
            'general' => 'General',
            'mype_restaurantes' => 'MYPE Restaurantes',
            'nrus' => 'NRUS',
        ];

        return Tenant::selectRaw('tax_regime, COUNT(*) as total')
            ->groupBy('tax_regime')
            ->get()
            ->map(fn ($r) => [
                'regimen' => $labels[$r->tax_regime] ?? $r->tax_regime ?? 'Sin definir',
                'total' => (int) $r->total,
            ])
            ->all();
    }

    private function estadoSunat(Carbon $inicioMes): array
    {
        $estados = ['aceptado', 'rechazado', 'enviado', 'pendiente'];
        $result = [];

        foreach ($estados as $estado) {
            $count = Invoice::where('sunat_status', $estado)
                ->where('fecha_emision', '>=', $inicioMes)
                ->count()
                + Boleta::where('sunat_status', $estado)
                ->where('fecha_emision', '>=', $inicioMes)
                ->count();

            $result[] = [
                'estado' => ucfirst($estado),
                'valor' => $count,
            ];
        }

        return $result;
    }

    private function topEmpresas(Carbon $inicioMes): array
    {
        $sql = "
            SELECT t.id, t.razon_social, t.ruc,
                COALESCE(SUM(mto), 0) as total_ventas,
                COALESCE(SUM(cnt), 0) as total_docs
            FROM tenants t
            LEFT JOIN (
                SELECT tenant_id, SUM(mto_imp_venta) as mto, COUNT(*) as cnt
                FROM invoices
                WHERE fecha_emision >= ? AND sunat_status = 'aceptado' AND deleted_at IS NULL
                GROUP BY tenant_id
                UNION ALL
                SELECT tenant_id, SUM(mto_imp_venta) as mto, COUNT(*) as cnt
                FROM boletas
                WHERE fecha_emision >= ? AND sunat_status = 'aceptado' AND deleted_at IS NULL
                GROUP BY tenant_id
            ) docs ON docs.tenant_id = t.id
            WHERE t.deleted_at IS NULL
            GROUP BY t.id, t.razon_social, t.ruc
            ORDER BY total_ventas DESC
            LIMIT 5
        ";

        $rows = DB::select($sql, [$inicioMes->format('Y-m-d'), $inicioMes->format('Y-m-d')]);

        return array_map(fn ($r) => [
            'ruc' => $r->ruc,
            'razon_social' => $r->razon_social,
            'total_ventas' => round((float) $r->total_ventas, 2),
            'total_docs' => (int) $r->total_docs,
        ], $rows);
    }

    private function empresasPorEntorno(): array
    {
        return Tenant::selectRaw('environment, COUNT(*) as total')
            ->groupBy('environment')
            ->get()
            ->map(fn ($r) => [
                'entorno' => $r->environment === 'production' ? 'Producción' : 'Beta',
                'total' => (int) $r->total,
            ])
            ->all();
    }
}

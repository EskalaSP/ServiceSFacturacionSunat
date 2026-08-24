<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Boleta;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\DispatchGuide;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel de inicio del cliente: KPIs y gráficos de SU empresa (empresa activa).
 * Reutiliza el mismo sistema visual del dashboard admin (Card + Recharts + tokens chart-N).
 */
class DashboardController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actual();

        if (! $tenant || empty($tenant->sol_user)) {
            return redirect()->route('sunat.configuracion')
                ->with('info', 'Configura tus credenciales SUNAT para comenzar.');
        }

        return Inertia::render('sunat/dashboard', [
            'metricas' => $this->metricas($tenant),
        ]);
    }

    private function metricas(Tenant $tenant): array
    {
        $hoy = Carbon::today('America/Lima');
        $inicioMes = $hoy->copy()->startOfMonth();
        $inicioMesAnt = $hoy->copy()->subMonth()->startOfMonth();
        $finMesAnt = $hoy->copy()->subMonth()->endOfMonth();

        return [
            'empresa' => [
                'razon_social' => $tenant->razon_social,
                'ruc' => $tenant->ruc,
                'entorno' => $tenant->environment === 'produccion' ? 'Producción' : 'Beta',
            ],
            'kpis' => $this->kpis($tenant, $hoy, $inicioMes, $inicioMesAnt, $finMesAnt),
            'documentos_por_dia' => $this->documentosPorDia($tenant, $hoy),
            'documentos_por_tipo' => $this->documentosPorTipo($tenant, $inicioMes),
            'estado_sunat' => $this->estadoSunat($tenant, $inicioMes),
            'ventas_por_mes' => $this->ventasPorMes($tenant, $hoy),
            'ultimos' => $this->ultimos($tenant),
            'periodo' => ['inicio_mes' => $inicioMes->format('Y-m-d'), 'hoy' => $hoy->format('Y-m-d')],
        ];
    }

    private function kpis(Tenant $tenant, Carbon $hoy, Carbon $inicioMes, Carbon $inicioMesAnt, Carbon $finMesAnt): array
    {
        $docsMes = $this->contarDocs($tenant, $inicioMes->format('Y-m-d'), $hoy->format('Y-m-d'));
        $docsMesAnt = $this->contarDocs($tenant, $inicioMesAnt->format('Y-m-d'), $finMesAnt->format('Y-m-d'));
        $ventasMes = $this->sumarVentas($tenant, $inicioMes->format('Y-m-d'), $hoy->format('Y-m-d'));
        $ventasMesAnt = $this->sumarVentas($tenant, $inicioMesAnt->format('Y-m-d'), $finMesAnt->format('Y-m-d'));

        $porEstado = fn (string $estado): int => (int) (
            Invoice::where('tenant_id', $tenant->id)->where('sunat_status', $estado)->where('fecha_emision', '>=', $inicioMes)->count()
            + Boleta::where('tenant_id', $tenant->id)->where('sunat_status', $estado)->where('fecha_emision', '>=', $inicioMes)->count()
        );

        return [
            'docs_hoy' => $this->contarDocs($tenant, $hoy->format('Y-m-d'), $hoy->format('Y-m-d')),
            'docs_mes' => $docsMes,
            'ventas_mes' => round($ventasMes, 2),
            'aceptados_mes' => $porEstado('aceptado'),
            'pendientes_mes' => $porEstado('pendiente') + $porEstado('enviado'),
            'rechazados_mes' => $porEstado('rechazado'),
            'clientes_total' => Client::where('tenant_id', $tenant->id)->count(),
            'crecimiento_docs' => $docsMesAnt > 0 ? round((($docsMes - $docsMesAnt) / $docsMesAnt) * 100, 1) : null,
            'crecimiento_ventas' => $ventasMesAnt > 0 ? round((($ventasMes - $ventasMesAnt) / $ventasMesAnt) * 100, 1) : null,
        ];
    }

    private function contarDocs(Tenant $tenant, string $desde, string $hasta): int
    {
        return (int) (
            Invoice::where('tenant_id', $tenant->id)->whereBetween('fecha_emision', [$desde, $hasta])->count()
            + Boleta::where('tenant_id', $tenant->id)->whereBetween('fecha_emision', [$desde, $hasta])->count()
            + CreditNote::where('tenant_id', $tenant->id)->whereBetween('fecha_emision', [$desde, $hasta])->count()
            + DebitNote::where('tenant_id', $tenant->id)->whereBetween('fecha_emision', [$desde, $hasta])->count()
        );
    }

    private function sumarVentas(Tenant $tenant, string $desde, string $hasta): float
    {
        return (float) (
            Invoice::where('tenant_id', $tenant->id)->whereBetween('fecha_emision', [$desde, $hasta])->where('sunat_status', 'aceptado')->sum('mto_imp_venta')
            + Boleta::where('tenant_id', $tenant->id)->whereBetween('fecha_emision', [$desde, $hasta])->where('sunat_status', 'aceptado')->sum('mto_imp_venta')
        );
    }

    private function documentosPorDia(Tenant $tenant, Carbon $hoy): array
    {
        $inicio = $hoy->copy()->subDays(29);
        $data = [];
        for ($f = $inicio->copy(); $f->lte($hoy); $f->addDay()) {
            $data[$f->format('Y-m-d')] = ['fecha' => $f->format('Y-m-d'), 'facturas' => 0, 'boletas' => 0, 'notas' => 0];
        }
        $desde = $inicio->format('Y-m-d');
        $hasta = $hoy->format('Y-m-d');

        $agg = fn ($model) => $model::where('tenant_id', $tenant->id)
            ->selectRaw('DATE(fecha_emision) as f, COUNT(*) as c')
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->groupBy('f')->pluck('c', 'f');

        foreach ($agg(Invoice::class) as $f => $c) {
            $k = Carbon::parse($f)->format('Y-m-d');
            if (isset($data[$k])) {
                $data[$k]['facturas'] = (int) $c;
            }
        }
        foreach ($agg(Boleta::class) as $f => $c) {
            $k = Carbon::parse($f)->format('Y-m-d');
            if (isset($data[$k])) {
                $data[$k]['boletas'] = (int) $c;
            }
        }
        $nc = $agg(CreditNote::class);
        $nd = $agg(DebitNote::class);
        foreach ($data as $k => &$row) {
            $row['notas'] = (int) ($nc[$k] ?? 0) + (int) ($nd[$k] ?? 0);
        }

        return array_values($data);
    }

    private function documentosPorTipo(Tenant $tenant, Carbon $inicioMes): array
    {
        return [
            ['tipo' => 'Facturas', 'valor' => Invoice::where('tenant_id', $tenant->id)->where('fecha_emision', '>=', $inicioMes)->count()],
            ['tipo' => 'Boletas', 'valor' => Boleta::where('tenant_id', $tenant->id)->where('fecha_emision', '>=', $inicioMes)->count()],
            ['tipo' => 'NC', 'valor' => CreditNote::where('tenant_id', $tenant->id)->where('fecha_emision', '>=', $inicioMes)->count()],
            ['tipo' => 'ND', 'valor' => DebitNote::where('tenant_id', $tenant->id)->where('fecha_emision', '>=', $inicioMes)->count()],
            ['tipo' => 'Guías', 'valor' => DispatchGuide::where('tenant_id', $tenant->id)->where('fecha_emision', '>=', $inicioMes)->count()],
        ];
    }

    private function estadoSunat(Tenant $tenant, Carbon $inicioMes): array
    {
        $estados = ['aceptado', 'pendiente', 'enviado', 'rechazado', 'anulado'];
        $result = [];
        foreach ($estados as $estado) {
            $count = Invoice::where('tenant_id', $tenant->id)->where('sunat_status', $estado)->where('fecha_emision', '>=', $inicioMes)->count()
                + Boleta::where('tenant_id', $tenant->id)->where('sunat_status', $estado)->where('fecha_emision', '>=', $inicioMes)->count();
            if ($count > 0) {
                $result[] = ['estado' => ucfirst($estado), 'valor' => $count];
            }
        }

        return $result;
    }

    private function ventasPorMes(Tenant $tenant, Carbon $hoy): array
    {
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $mes = $hoy->copy()->subMonths($i)->startOfMonth();
            $fin = $mes->copy()->endOfMonth();
            $data[] = [
                'mes' => ucfirst($mes->locale('es')->isoFormat('MMM')),
                'ventas' => round($this->sumarVentas($tenant, $mes->format('Y-m-d'), $fin->format('Y-m-d')), 2),
            ];
        }

        return $data;
    }

    private function ultimos(Tenant $tenant): array
    {
        $mapear = fn ($doc, string $tipo): array => [
            'tipo' => $tipo,
            'numero' => $doc->serie.'-'.str_pad((string) $doc->correlativo, 8, '0', STR_PAD_LEFT),
            'cliente' => $doc->client_razon_social,
            'total' => (float) $doc->mto_imp_venta,
            'moneda' => $doc->tipo_moneda ?? 'PEN',
            'estado' => $doc->sunat_status ?? 'pendiente',
            'fecha' => optional($doc->fecha_emision)->format('Y-m-d') ?? (string) $doc->fecha_emision,
        ];

        $docs = collect();
        foreach ([[Invoice::class, 'Factura'], [Boleta::class, 'Boleta'], [CreditNote::class, 'N. Crédito'], [DebitNote::class, 'N. Débito']] as [$model, $tipo]) {
            $rows = $model::where('tenant_id', $tenant->id)->orderByDesc('id')->limit(8)->get();
            $docs = $docs->concat($rows->map(fn ($d) => $mapear($d, $tipo)));
        }

        return $docs->sortByDesc('fecha')->take(8)->values()->all();
    }
}

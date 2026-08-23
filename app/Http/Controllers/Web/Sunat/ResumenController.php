<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Boleta;
use App\Services\Documents\SummaryService;
use App\Services\Tenancy\EmpresaActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Resumen diario de boletas desde el panel: envía a SUNAT las boletas pendientes
 * de una fecha. Reutiliza SummaryService (misma lógica que la API).
 */
class ResumenController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();

        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        $pendientes = Boleta::where('tenant_id', $tenant->id)
            ->where('sunat_status', 'pendiente')
            ->get(['id', 'fecha_emision', 'mto_imp_venta']);

        $fechas = $pendientes
            ->groupBy(fn ($b) => optional($b->fecha_emision)->format('Y-m-d') ?? (string) $b->fecha_emision)
            ->map(fn ($grupo, $fecha) => [
                'fecha' => $fecha,
                'cantidad' => $grupo->count(),
                'total' => (float) $grupo->sum('mto_imp_venta'),
            ])
            ->sortByDesc('fecha')
            ->values();

        return Inertia::render('sunat/resumenes/nueva', [
            'fechas' => $fechas,
        ]);
    }

    public function store(Request $request, SummaryService $service): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('emitir', [$tenant, 'resumen']);

        $data = $request->validate([
            'fecha_resumen' => 'required|date',
        ]);

        $resultado = $service->crear($tenant, $data['fecha_resumen'], null, true);

        if (! $resultado['ok']) {
            return back()->with('error', $resultado['error'] ?? implode(' ', $resultado['errores'] ?? []));
        }

        return redirect()->route('sunat.historial')
            ->with('success', 'Resumen diario enviado a SUNAT: '.$resultado['meta']['identifier']);
    }
}

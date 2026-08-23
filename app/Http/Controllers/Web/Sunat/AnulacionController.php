<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Services\Documents\VoidedService;
use App\Services\Tenancy\EmpresaActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comunicación de baja (RA) desde el panel: anular una factura o nota ya aceptada
 * por SUNAT (dentro de los 7 días). Las boletas se anulan por resumen diario, no aquí.
 */
class AnulacionController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();

        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        $limite = now()->subDays(7)->startOfDay();

        $mapear = fn ($doc, string $tipo, string $label): array => [
            'tipo_documento' => $tipo,
            'tipo_label' => $label,
            'serie' => $doc->serie,
            'correlativo' => (string) $doc->correlativo,
            'numero' => $doc->serie.'-'.str_pad((string) $doc->correlativo, 8, '0', STR_PAD_LEFT),
            'cliente' => $doc->client_razon_social,
            'total' => (float) $doc->mto_imp_venta,
            'moneda' => $doc->tipo_moneda ?? 'PEN',
            'fecha_emision' => optional($doc->fecha_emision)->format('Y-m-d') ?? (string) $doc->fecha_emision,
        ];

        $anulables = collect();
        foreach ([['01', Invoice::class, 'Factura'], ['07', CreditNote::class, 'Nota de crédito'], ['08', DebitNote::class, 'Nota de débito']] as [$tipo, $model, $label]) {
            $docs = $model::where('tenant_id', $tenant->id)
                ->where('sunat_status', 'aceptado')
                ->where('fecha_emision', '>=', $limite)
                ->orderByDesc('id')
                ->limit(100)
                ->get();

            $anulables = $anulables->concat($docs->map(fn ($d) => $mapear($d, $tipo, $label)));
        }

        return Inertia::render('sunat/anulaciones/nueva', [
            'documentos' => $anulables->sortByDesc('fecha_emision')->values(),
        ]);
    }

    public function store(Request $request, VoidedService $service): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('emitir', [$tenant, 'anulacion']);

        $data = $request->validate([
            'tipo_documento' => 'required|in:01,07,08',
            'serie' => 'required|string|size:4',
            'correlativo' => 'required|string',
            'motivo' => 'required|string|max:255',
            'fecha_generacion' => 'required|date',
        ]);

        $detalle = [
            'tipo_documento' => $data['tipo_documento'],
            'serie' => $data['serie'],
            'correlativo' => $data['correlativo'],
            'motivo' => $data['motivo'],
        ];

        $resultado = $service->crear($tenant, $data['fecha_generacion'], null, [$detalle], true);

        if (! $resultado['ok']) {
            return back()->with('error', 'No se puede anular: '.implode(' ', $resultado['errores']));
        }

        return redirect()->route('sunat.historial')
            ->with('success', 'Comunicación de baja enviada a SUNAT: '.$resultado['voided']->identifier);
    }
}

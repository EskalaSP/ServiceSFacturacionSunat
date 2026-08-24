<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Perception;
use App\Models\Retention;
use App\Services\Documents\VoidedService;
use App\Services\Tenancy\EmpresaActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reversión (RR): da de baja retenciones (20) o percepciones (40) aceptadas.
 * Reutiliza VoidedService::crearReversion.
 */
class ReversionController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();

        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        $retenciones = Retention::where('tenant_id', $tenant->id)
            ->where('sunat_status', 'aceptado')
            ->orderByDesc('id')->limit(100)->get()
            ->map(fn ($r) => [
                'tipo_documento' => '20',
                'tipo_label' => 'Retención',
                'serie' => $r->serie,
                'correlativo' => (string) $r->correlativo,
                'numero' => $r->numero_completo,
                'contraparte' => $r->proveedor_razon_social,
                'fecha_emision' => optional($r->fecha_emision)->format('Y-m-d') ?? (string) $r->fecha_emision,
            ]);

        $percepciones = Perception::where('tenant_id', $tenant->id)
            ->where('sunat_status', 'aceptado')
            ->orderByDesc('id')->limit(100)->get()
            ->map(fn ($p) => [
                'tipo_documento' => '40',
                'tipo_label' => 'Percepción',
                'serie' => $p->serie,
                'correlativo' => (string) $p->correlativo,
                'numero' => $p->numero_completo,
                'contraparte' => $p->cliente_razon_social,
                'fecha_emision' => optional($p->fecha_emision)->format('Y-m-d') ?? (string) $p->fecha_emision,
            ]);

        return Inertia::render('sunat/reversiones/nueva', [
            'documentos' => $retenciones->concat($percepciones)->sortByDesc('fecha_emision')->values(),
        ]);
    }

    public function store(Request $request, VoidedService $service): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('emitir', [$tenant, 'reversion']);

        $data = $request->validate([
            'tipo_documento' => 'required|in:20,40',
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

        $resultado = $service->crearReversion($tenant, $data['fecha_generacion'], null, [$detalle], true);

        if (! $resultado['ok']) {
            return back()->with('error', 'No se puede revertir: '.implode(' ', $resultado['errores']));
        }

        return redirect()->route('sunat.historial')
            ->with('success', 'Reversión enviada a SUNAT: '.$resultado['meta']['identifier']);
    }
}

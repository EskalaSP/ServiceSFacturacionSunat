<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Actions\Documents\CreatePerceptionAction;
use App\Http\Controllers\Controller;
use App\Models\Serie;
use App\Services\Tenancy\EmpresaActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Comprobante de Percepción (40). Reutiliza CreatePerceptionAction. */
class PercepcionController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();

        if (! $tenant || ! $tenant->sol_user) {
            return redirect()->route('sunat.configuracion')
                ->with('warning', 'Configura tus credenciales SUNAT primero.');
        }

        $series = Serie::where('tenant_id', $tenant->id)
            ->where('tipo_documento', '40')
            ->where('is_active', true)
            ->get(['id', 'serie', 'tipo_documento', 'correlativo']);

        return Inertia::render('sunat/percepciones/nueva', [
            'tenant' => ['ruc' => $tenant->ruc, 'razon_social' => $tenant->razon_social, 'environment' => $tenant->environment ?? 'beta'],
            'series' => $series,
        ]);
    }

    public function store(Request $request, CreatePerceptionAction $action): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('emitir', [$tenant, 'percepcion']);

        $enviar = $request->boolean('enviar_automatico', true);

        try {
            $per = $action->execute($tenant, $request->all(), $enviar);
            $numero = $per->numero_completo ?? '';

            return redirect()->route('sunat.percepciones.create')
                ->with('success', $enviar
                    ? "Percepción {$numero} emitida y enviada a SUNAT."
                    : "Percepción {$numero} guardada como borrador.")
                ->with('emitido', ['tipo' => '40', 'id' => $per->id, 'numero' => $numero, 'formato' => $request->input('pdf_format', 'a4')]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error al emitir percepción: '.$e->getMessage());
        }
    }
}

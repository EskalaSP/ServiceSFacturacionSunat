<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Actions\Documents\CreateRetentionAction;
use App\Http\Controllers\Controller;
use App\Models\Serie;
use App\Services\Tenancy\EmpresaActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Comprobante de Retención (20). Reutiliza CreateRetentionAction. */
class RetencionController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();

        if (! $tenant || ! $tenant->sol_user) {
            return redirect()->route('sunat.configuracion')
                ->with('warning', 'Configura tus credenciales SUNAT primero.');
        }

        $series = Serie::where('tenant_id', $tenant->id)
            ->where('tipo_documento', '20')
            ->where('is_active', true)
            ->get(['id', 'serie', 'tipo_documento', 'correlativo']);

        return Inertia::render('sunat/retenciones/nueva', [
            'tenant' => ['ruc' => $tenant->ruc, 'razon_social' => $tenant->razon_social, 'environment' => $tenant->environment ?? 'beta'],
            'series' => $series,
        ]);
    }

    public function store(Request $request, CreateRetentionAction $action): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('emitir', [$tenant, 'retencion']);

        $enviar = $request->boolean('enviar_automatico', true);

        try {
            $ret = $action->execute($tenant, $request->all(), $enviar);
            $numero = $ret->numero_completo ?? '';

            return redirect()->route('sunat.retenciones.create')
                ->with('success', $enviar
                    ? "Retención {$numero} emitida y enviada a SUNAT."
                    : "Retención {$numero} guardada como borrador.")
                ->with('emitido', ['tipo' => '20', 'id' => $ret->id, 'numero' => $numero, 'formato' => $request->input('pdf_format', 'a4')]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error al emitir retención: '.$e->getMessage());
        }
    }
}

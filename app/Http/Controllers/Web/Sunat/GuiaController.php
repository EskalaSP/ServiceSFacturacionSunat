<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Actions\Documents\CreateDispatchGuideAction;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Serie;
use App\Services\Tenancy\EmpresaActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guía de remisión electrónica desde el panel:
 *   - Remitente (09): la empresa traslada sus bienes.
 *   - Transportista (31): la empresa transportista traslada bienes de un tercero.
 * Reutiliza CreateDispatchGuideAction (la misma de la API).
 */
class GuiaController extends Controller
{
    public function index(\App\Services\Documents\DocumentoListingService $listing): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();
        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        return Inertia::render('sunat/documentos/index', [
            'titulo' => 'Guías de remisión',
            'subtitulo' => 'Guías de remisión emitidas',
            'nuevo' => ['href' => '/sunat/guias/nueva', 'label' => 'Nueva guía'],
            'documentos' => $listing->listar($tenant, 'guias'),
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();

        if (! $tenant || ! $tenant->sol_user) {
            return redirect()->route('sunat.configuracion')
                ->with('warning', 'Configura tus credenciales SUNAT primero.');
        }

        $series = fn (string $tipo) => Serie::where('tenant_id', $tenant->id)
            ->where('tipo_documento', $tipo)
            ->where('is_active', true)
            ->get(['id', 'serie', 'tipo_documento', 'correlativo']);

        $clientes = Client::where('tenant_id', $tenant->id)
            ->orderBy('razon_social')
            ->limit(50)
            ->get(['id', 'tipo_documento', 'numero_documento', 'razon_social', 'direccion', 'email']);

        return Inertia::render('sunat/guias/nueva', [
            'tenant' => [
                'ruc' => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
                'environment' => $tenant->environment ?? 'beta',
            ],
            'series_remitente' => $series('09'),
            'series_transportista' => $series('31'),
            'clientes' => $clientes,
        ]);
    }

    public function store(Request $request, CreateDispatchGuideAction $action): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();

        $tipo = $request->input('tipo_documento', '09');
        $ability = $tipo === '31' ? 'guia_transportista' : 'guia_remitente';
        Gate::authorize('emitir', [$tenant, $ability]);

        $enviar = $request->boolean('enviar_automatico', true);

        try {
            $guide = $action->execute($tenant, $request->all(), $enviar);
            $numero = $guide->serie.'-'.str_pad((string) $guide->correlativo, 8, '0', STR_PAD_LEFT);

            $msg = $enviar
                ? "Guía de remisión {$numero} emitida y enviada a SUNAT."
                : "Guía de remisión {$numero} guardada como borrador.";

            return redirect()->route('sunat.guias.create')
                ->with('success', $msg)
                ->with('emitido', ['tipo' => $tipo, 'id' => $guide->id, 'numero' => $numero, 'formato' => $request->input('pdf_format', 'a4')]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error al emitir guía: '.$e->getMessage());
        }
    }
}

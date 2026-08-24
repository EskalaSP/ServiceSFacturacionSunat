<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Actions\Documents\CreateBoletaAction;
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
 * Boleta de venta (03) con su propia entrada en el panel. Reutiliza el formulario
 * de factura en modo "solo boleta" y la misma CreateBoletaAction que la API.
 */
class BoletaController extends Controller
{
    public function index(\App\Services\Documents\DocumentoListingService $listing): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();
        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        return Inertia::render('sunat/documentos/index', [
            'titulo' => 'Boletas',
            'subtitulo' => 'Comprobantes tipo boleta emitidos',
            'nuevo' => ['href' => '/sunat/boletas/nueva', 'label' => 'Nueva boleta'],
            'documentos' => $listing->listar($tenant, 'boletas'),
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();

        if (! $tenant || ! $tenant->sol_user) {
            return redirect()->route('sunat.configuracion')
                ->with('warning', 'Configura tus credenciales SUNAT primero.');
        }

        $seriesBoleta = Serie::where('tenant_id', $tenant->id)
            ->where('tipo_documento', '03')
            ->where('is_active', true)
            ->get(['id', 'serie', 'tipo_documento', 'correlativo']);

        $clientes = Client::where('tenant_id', $tenant->id)
            ->orderBy('razon_social')
            ->limit(50)
            ->get(['id', 'tipo_documento', 'numero_documento', 'razon_social', 'direccion', 'email']);

        return Inertia::render('sunat/facturas/nueva', [
            'tenant' => [
                'ruc' => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
                'direccion' => $tenant->direccion ?? '',
                'environment' => $tenant->environment ?? 'beta',
            ],
            'series_factura' => [],
            'series_boleta' => $seriesBoleta,
            'clientes' => $clientes,
            'tipo_inicial' => 'boleta',
            'lock_tipo' => true,
            'post_url' => route('sunat.boletas.store'),
            'cotizacion' => null,
        ]);
    }

    public function store(Request $request, CreateBoletaAction $createBoleta): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('emitir', [$tenant, 'boleta']);

        $enviar = $request->boolean('enviar_automatico', true);

        try {
            $doc = $createBoleta->execute($tenant, $request->all(), false, $enviar);
            $numero = $doc->serie.'-'.str_pad((string) $doc->correlativo, 8, '0', STR_PAD_LEFT);

            $msg = $enviar
                ? "Boleta {$numero} emitida y enviada a SUNAT."
                : "Boleta {$numero} registrada. Quedó pendiente de envío por Resumen Diario.";

            return redirect()->route('sunat.boletas.create')
                ->with('success', $msg)
                ->with('emitido', ['tipo' => '03', 'id' => $doc->id, 'numero' => $numero, 'formato' => $request->input('pdf_format', 'a4')]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error al emitir: '.$e->getMessage());
        }
    }
}

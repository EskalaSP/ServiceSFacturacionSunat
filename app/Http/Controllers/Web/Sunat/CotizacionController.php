<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Actions\Documents\CreateQuotationAction;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CotizacionController extends Controller
{
    public function index(): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenants()->first();

        if (! $tenant || ! $tenant->sol_user) {
            return redirect()->route('sunat.configuracion')
                ->with('warning', 'Configura tus credenciales SUNAT primero.');
        }

        $cotizaciones = Quotation::where('tenant_id', $tenant->id)
            ->when(request('q'), fn ($q, $search) =>
                $q->where('client_razon_social', 'ilike', "%{$search}%")
                  ->orWhere('numero', 'ilike', "%{$search}%")
            )
            ->when(request('status') && request('status') !== 'todos', fn ($q) =>
                $q->where('status', request('status'))
            )
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($cot) => [
                'id'                => $cot->id,
                'numero'            => $cot->numero,
                'fecha_emision'     => $cot->fecha_emision?->format('Y-m-d'),
                'fecha_vencimiento' => $cot->fecha_vencimiento?->format('Y-m-d'),
                'cliente'           => $cot->client_razon_social,
                'total'             => (float) $cot->mto_imp_venta,
                'moneda'            => $cot->tipo_moneda,
                'status'            => $cot->status,
                'observacion'       => $cot->observacion,
            ]);

        return Inertia::render('sunat/cotizaciones/index', [
            'cotizaciones' => $cotizaciones,
            'filtros'      => ['q' => request('q', ''), 'status' => request('status', 'todos')],
            'tenant'       => [
                'ruc'          => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
                'environment'  => $tenant->environment ?? 'beta',
            ],
        ]);
    }

    public function create(): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenants()->first();

        if (! $tenant || ! $tenant->sol_user) {
            return redirect()->route('sunat.configuracion')
                ->with('warning', 'Configura tus credenciales SUNAT primero.');
        }

        $clientes = Client::where('tenant_id', $tenant->id)
            ->orderBy('razon_social')
            ->limit(50)
            ->get(['id', 'tipo_documento', 'numero_documento', 'razon_social', 'direccion', 'email']);

        return Inertia::render('sunat/cotizaciones/nueva', [
            'tenant'   => [
                'ruc'          => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
                'environment'  => $tenant->environment ?? 'beta',
            ],
            'clientes' => $clientes,
        ]);
    }

    public function store(Request $request, CreateQuotationAction $action): \Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenants()->firstOrFail();

        try {
            $action->execute($tenant, $request->all());
            return redirect()->route('sunat.cotizaciones')
                ->with('success', 'Cotización creada correctamente.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function updateEstado(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenants()->firstOrFail();
        $cot    = Quotation::where('tenant_id', $tenant->id)->findOrFail($id);

        $request->validate(['status' => 'required|in:vigente,aceptada,rechazada,vencida']);
        $cot->update(['status' => $request->input('status')]);

        return back()->with('success', 'Estado actualizado.');
    }

    public function convertir(int $id): \Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenants()->firstOrFail();
        $cot    = Quotation::where('tenant_id', $tenant->id)->findOrFail($id);

        $cot->update(['status' => 'aceptada']);

        return redirect()->route('sunat.facturas.create', ['cotizacion_id' => $id]);
    }

    public function destroy(int $id): \Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenants()->firstOrFail();
        Quotation::where('tenant_id', $tenant->id)->findOrFail($id)->delete();

        return back()->with('success', 'Cotización eliminada.');
    }
}

<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Actions\Documents\CreateSaleNoteAction;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Tenancy\EmpresaActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Nota de venta (documento interno, sin SUNAT). Reutiliza CreateSaleNoteAction. */
class NotaVentaController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();
        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        $notas = \App\Models\SaleNote::forTenant($tenant->id)
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'numero' => $n->numero,
                'cliente' => $n->client_razon_social,
                'fecha' => optional($n->fecha_emision)->format('Y-m-d') ?? (string) $n->fecha_emision,
                'total' => (float) $n->mto_imp_venta,
                'moneda' => $n->tipo_moneda ?? 'PEN',
                'estado' => $n->status ?? 'emitida',
                'tiene_pdf' => ! empty($n->pdf_path),
            ])
            ->all();

        return Inertia::render('sunat/nota-venta/index', [
            'notas' => $notas,
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();

        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        $clientes = Client::where('tenant_id', $tenant->id)
            ->orderBy('razon_social')
            ->limit(50)
            ->get(['id', 'tipo_documento', 'numero_documento', 'razon_social', 'direccion', 'email']);

        return Inertia::render('sunat/nota-venta/nueva', [
            'tenant' => ['ruc' => $tenant->ruc, 'razon_social' => $tenant->razon_social, 'environment' => $tenant->environment ?? 'beta'],
            'clientes' => $clientes,
        ]);
    }

    public function store(Request $request, CreateSaleNoteAction $action): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('emitir', [$tenant, 'nota_venta']);

        try {
            $doc = $action->execute($tenant, $request->all());

            return redirect()->route('sunat.historial')
                ->with('success', 'Nota de venta '.($doc->numero ?? '').' creada.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error al crear nota de venta: '.$e->getMessage());
        }
    }
}

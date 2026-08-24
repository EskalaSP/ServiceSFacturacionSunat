<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Services\SunatCpeConsultaService;
use App\Services\Tenancy\EmpresaActiva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Consulta el estado de un comprobante en SUNAT (Consulta Integrada CPE). */
class ConsultaCpeController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();
        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        return Inertia::render('sunat/consulta-cpe/index', [
            'ruc_emisor' => $tenant->ruc,
        ]);
    }

    public function consultar(Request $request, SunatCpeConsultaService $service): JsonResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('consultar-cpe', $tenant);

        $validated = $request->validate([
            'ruc_emisor' => 'nullable|string|size:11',
            'tipo_doc' => 'required|string|in:01,03,04,07,08,R1,R7',
            'serie' => 'required|string|max:4',
            'correlativo' => 'required|integer|min:1',
            'fecha_emision' => ['required', 'string', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'monto' => 'required|numeric|min:0',
        ]);

        $validated['ruc_emisor'] = $validated['ruc_emisor'] ?? $tenant->ruc;

        try {
            $result = $service->consultar($tenant, $validated);

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}

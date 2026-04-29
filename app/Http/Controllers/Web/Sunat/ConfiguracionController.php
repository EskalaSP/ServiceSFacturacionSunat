<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Serie;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguracionController extends Controller
{
    public function edit(): Response
    {
        $tenant = auth()->user()->tenants()->first();

        $series = $tenant
            ? Serie::where('tenant_id', $tenant->id)->where('is_active', true)->get(['id', 'serie', 'tipo_documento', 'correlativo'])
            : collect();

        return Inertia::render('sunat/configuracion', [
            'tenant' => $tenant ? [
                'ruc'           => $tenant->ruc,
                'razon_social'  => $tenant->razon_social,
                'sol_user'      => $tenant->sol_user ?? '',
                'environment'   => $tenant->environment ?? 'beta',
                'serie_factura' => $series->firstWhere('tipo_documento', '01')?->serie ?? 'F001',
                'serie_boleta'  => $series->firstWhere('tipo_documento', '03')?->serie ?? 'B001',
            ] : null,
        ]);
    }

    public function update(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'sol_user'      => 'required|string|max:20',
            'sol_pass'      => 'required|string|max:50',
            'environment'   => 'required|in:beta,produccion',
            'serie_factura' => ['required', 'string', 'size:4', 'regex:/^F\d{3}$/'],
            'serie_boleta'  => ['required', 'string', 'size:4', 'regex:/^B\d{3}$/'],
        ]);

        $tenant = auth()->user()->tenants()->firstOrFail();

        $tenant->update([
            'sol_user'    => $data['sol_user'],
            'sol_pass'    => $data['sol_pass'],
            'environment' => $data['environment'],
        ]);

        Serie::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tipo_documento' => '01', 'serie' => $data['serie_factura']],
            ['correlativo' => 0, 'is_active' => true]
        );

        Serie::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tipo_documento' => '03', 'serie' => $data['serie_boleta']],
            ['correlativo' => 0, 'is_active' => true]
        );

        return redirect()->route('sunat.dashboard')
            ->with('success', 'Configuración SUNAT guardada correctamente.');
    }

    public function probarConexion(): \Illuminate\Http\JsonResponse
    {
        $tenant = auth()->user()->tenants()->firstOrFail();

        if (! $tenant->sol_user || ! $tenant->sol_pass) {
            return response()->json(['ok' => false, 'mensaje' => 'Credenciales SOL no configuradas.']);
        }

        return response()->json([
            'ok'      => true,
            'mensaje' => 'Credenciales guardadas correctamente.',
            'ambiente' => $tenant->environment === 'produccion' ? 'Producción SUNAT' : 'Beta / Homologación SUNAT',
            'ruc'     => $tenant->ruc,
            'usuario' => $tenant->sol_user,
        ]);
    }
}

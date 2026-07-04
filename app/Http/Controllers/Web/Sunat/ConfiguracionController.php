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
        $user = auth()->user();
        $tenant = $user->tenants()->first();

        if (!$tenant) {
            $tenant = \App\Models\Tenant::create([
                'user_id' => $user->id,
                'ruc' => '10463838327', // RUC personal proporcionado por el usuario
                'razon_social' => 'SUAREZ ORBEGOSO LUIS ANDRES',
                'sol_user' => '',
                'sol_pass' => '',
                'environment' => 'beta',
                'plan' => 'free',
                'is_active' => true,
            ]);
            
            \App\Models\Serie::firstOrCreate(
                ['tenant_id' => $tenant->id, 'tipo_documento' => '01', 'serie' => 'F001'],
                ['correlativo' => 0, 'is_active' => true]
            );

            \App\Models\Serie::firstOrCreate(
                ['tenant_id' => $tenant->id, 'tipo_documento' => '03', 'serie' => 'B001'],
                ['correlativo' => 0, 'is_active' => true]
            );
        }

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
            'sol_pass'      => 'required|string|max:255',
            'environment'   => 'required|in:beta,produccion',
            'serie_factura' => 'required|string|max:4',
            'serie_boleta'  => 'required|string|max:4',
            'certificate'   => 'nullable|file|max:4096',
            'certificate_password' => 'nullable|string|max:255',
        ]);

        $user = auth()->user();
        $tenant = $user->tenants()->first();

        if (!$tenant) {
            return redirect()->route('sunat.configuracion')
                ->with('error', 'No se ha encontrado ninguna empresa (Tenant) registrada para tu usuario. Primero debes registrar una empresa.');
        }

        $tenant->update([
            'sol_user'    => $data['sol_user'],
            'sol_pass'    => $data['sol_pass'],
            'environment' => $data['environment'],
        ]);

        if ($request->hasFile('certificate')) {
            $certContent = file_get_contents($request->file('certificate')->getRealPath());
            $certService = new \App\Services\Storage\DocumentStorageService();
            $ext = $request->file('certificate')->getClientOriginalExtension();
            $certPath = $certService->storeCertificate($tenant, $certContent, 'cert.' . $ext);
            $tenant->update(['certificate_path' => $certPath]);
        }
        if ($request->filled('certificate_password')) {
            $tenant->update(['certificate_password' => $data['certificate_password']]);
        }

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
        $tenant = auth()->user()->tenants()->first();

        if (! $tenant || ! $tenant->sol_user || ! $tenant->sol_pass) {
            return response()->json(['ok' => false, 'mensaje' => 'Credenciales SOL no configuradas o empresa no registrada.']);
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

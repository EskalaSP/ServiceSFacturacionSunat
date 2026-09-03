<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Serie;
use App\Models\TenantMembership;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguracionController extends Controller
{
    public function edit(): Response
    {
        $user = auth()->user();
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actual();
        $membresia = $user && $tenant ? $user->membershipFor($tenant) : null;
        $esSimple = $membresia?->role === TenantMembership::ROLE_SIMPLE;

        if (! $tenant) {
            $tenant = \App\Models\Tenant::create([
                'user_id' => $user->id,
                'ruc' => '10463838327',
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
            'esSimple' => $esSimple,
            'tenant' => $tenant ? [
                'ruc' => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
                'nombre_comercial' => $tenant->nombre_comercial ?? '',
                'direccion' => $tenant->direccion ?? '',
                'ubigeo' => $tenant->ubigeo ?? '',
                'departamento' => $tenant->departamento ?? '',
                'provincia' => $tenant->provincia ?? '',
                'distrito' => $tenant->distrito ?? '',
                'telefonos' => $tenant->telefonos ?? [],
                'emails' => $tenant->emails ?? [],
                'cuentas_bancarias' => $tenant->cuentas_bancarias ?? [],
                'billeteras_digitales' => $tenant->billeteras_digitales ?? [],
                'mensaje_agradecimiento' => $tenant->mensaje_agradecimiento ?? '',
                'mensaje_promocional' => $tenant->mensaje_promocional ?? '',
                'logo_url' => $tenant->logo_path
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($tenant->logo_path).'?v='.($tenant->updated_at?->timestamp ?? time())
                    : null,
                'sol_user' => $tenant->sol_user ?? '',
                'environment' => $tenant->environment ?? 'beta',
                'serie_factura' => $series->firstWhere('tipo_documento', '01')?->serie ?? 'F001',
                'serie_boleta' => $series->firstWhere('tipo_documento', '03')?->serie ?? 'B001',
                'consulta_token_set' => ! empty($tenant->consulta_token),
            ] : null,
        ]);
    }

    public function update(Request $request): \Illuminate\Http\RedirectResponse
    {
        foreach (['telefonos', 'emails', 'cuentas_bancarias', 'billeteras_digitales'] as $campo) {
            if (is_string($request->input($campo))) {
                $decoded = json_decode((string) $request->input($campo), true);
                $request->merge([$campo => is_array($decoded) ? $decoded : []]);
            }
        }

        $user = auth()->user();
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actual();
        $membresia = $user && $tenant ? $user->membershipFor($tenant) : null;
        $esSimple = $membresia?->role === TenantMembership::ROLE_SIMPLE;

        $data = $request->validate([
            'sol_user' => ($esSimple ? 'nullable' : 'required').'|string|max:20',
            'sol_pass' => ($esSimple ? 'nullable' : 'required').'|string|max:255',
            'environment' => ($esSimple ? 'nullable' : 'required').'|in:beta,produccion',
            'serie_factura' => ($esSimple ? 'nullable' : 'required').'|string|max:4',
            'serie_boleta' => ($esSimple ? 'nullable' : 'required').'|string|max:4',
            'certificate' => 'nullable|file|max:4096',
            'certificate_password' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
            'razon_social' => 'nullable|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:500',
            'ubigeo' => 'nullable|string|size:6',
            'departamento' => 'nullable|string|max:100',
            'provincia' => 'nullable|string|max:100',
            'distrito' => 'nullable|string|max:100',
            'mensaje_agradecimiento' => 'nullable|string|max:500',
            'mensaje_promocional' => 'nullable|string|max:500',
            'telefonos' => 'nullable|array|max:5',
            'telefonos.*' => 'nullable|string|max:30',
            'emails' => 'nullable|array|max:5',
            'emails.*' => 'nullable|email|max:100',
            'cuentas_bancarias' => 'nullable|array|max:5',
            'cuentas_bancarias.*.banco' => 'required_with:cuentas_bancarias.*.numero|string|max:50',
            'cuentas_bancarias.*.tipo' => 'nullable|string|max:30',
            'cuentas_bancarias.*.numero' => 'required_with:cuentas_bancarias.*.banco|string|max:30',
            'cuentas_bancarias.*.cci' => 'nullable|string|max:30',
            'cuentas_bancarias.*.moneda' => 'nullable|in:PEN,USD',
            'cuentas_bancarias.*.titular' => 'nullable|string|max:100',
            'billeteras_digitales' => 'nullable|array|max:5',
            'billeteras_digitales.*.tipo' => 'required_with:billeteras_digitales.*.numero|string|max:30',
            'billeteras_digitales.*.numero' => 'required_with:billeteras_digitales.*.tipo|string|max:20',
            'billeteras_digitales.*.titular' => 'nullable|string|max:100',
        ]);

        if (! $tenant) {
            return redirect()->route('sunat.configuracion')
                ->with('error', 'No se ha encontrado ninguna empresa (Tenant) registrada para tu usuario. Primero debes registrar una empresa.');
        }

        \Illuminate\Support\Facades\Gate::authorize('editar-empresa', $tenant);

        $telefonos = array_values(array_filter($data['telefonos'] ?? [], fn ($t) => filled($t)));
        $emails = array_values(array_filter($data['emails'] ?? [], fn ($e) => filled($e)));
        $cuentas = array_values(array_filter($data['cuentas_bancarias'] ?? [], fn ($c) => filled($c['banco'] ?? null) && filled($c['numero'] ?? null)));
        $billeteras = array_values(array_filter($data['billeteras_digitales'] ?? [], fn ($b) => filled($b['tipo'] ?? null) && filled($b['numero'] ?? null)));

        $tenant->update([
            'sol_user' => $esSimple ? $tenant->sol_user : $data['sol_user'],
            'sol_pass' => $esSimple ? $tenant->sol_pass : $data['sol_pass'],
            'environment' => $esSimple ? ($tenant->environment ?? 'beta') : $data['environment'],
            'razon_social' => $data['razon_social'] ?: $tenant->razon_social,
            'nombre_comercial' => $data['nombre_comercial'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'ubigeo' => $data['ubigeo'] ?? null,
            'departamento' => $data['departamento'] ?? null,
            'provincia' => $data['provincia'] ?? null,
            'distrito' => $data['distrito'] ?? null,
            'telefonos' => $telefonos,
            'emails' => $emails,
            'cuentas_bancarias' => $cuentas,
            'billeteras_digitales' => $billeteras,
            'mensaje_agradecimiento' => $data['mensaje_agradecimiento'] ?? null,
            'mensaje_promocional' => $data['mensaje_promocional'] ?? null,
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store("tenants/{$tenant->id}/logos", 'public');
            $tenant->update(['logo_path' => $path]);
        }

        if ($request->hasFile('certificate')) {
            $archivo = $request->file('certificate');
            $certService = new \App\Services\CertificateService;

            try {
                $pemContent = $certService->convertToPem(
                    file_get_contents($archivo->getRealPath()),
                    strtolower($archivo->getClientOriginalExtension()),
                    $request->input('certificate_password'),
                );
            } catch (\RuntimeException $e) {
                return back()->withErrors(['certificate' => $e->getMessage()])->withInput();
            }

            $certPath = (new \App\Services\Storage\DocumentStorageService)
                ->storeCertificate($tenant, $pemContent, 'cert.pem');

            $tenant->update(['certificate_path' => $certPath]);
        }

        if ($request->filled('certificate_password')) {
            $tenant->update(['certificate_password' => $data['certificate_password']]);
        }

        if (! $esSimple && ! empty($data['serie_factura'])) {
            Serie::firstOrCreate(
                ['tenant_id' => $tenant->id, 'tipo_documento' => '01', 'serie' => $data['serie_factura']],
                ['correlativo' => 0, 'is_active' => true]
            );
        }

        if (! $esSimple && ! empty($data['serie_boleta'])) {
            Serie::firstOrCreate(
                ['tenant_id' => $tenant->id, 'tipo_documento' => '03', 'serie' => $data['serie_boleta']],
                ['correlativo' => 0, 'is_active' => true]
            );
        }

        return redirect()->route('sunat.dashboard')
            ->with('success', 'Configuración SUNAT guardada correctamente.');
    }

    public function updateConsultaToken(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'consulta_token' => 'nullable|string|max:1000',
        ]);

        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actual();

        if (! $tenant) {
            return back()->with('error', 'No hay empresa activa.');
        }

        \Illuminate\Support\Facades\Gate::authorize('editar-empresa', $tenant);

        $token = trim((string) ($data['consulta_token'] ?? ''));
        $tenant->update(['consulta_token' => $token !== '' ? $token : null]);

        return back()->with('success', $token !== ''
            ? 'Token de consulta guardado correctamente.'
            : 'Token de consulta eliminado.');
    }

    public function probarConexion(): \Illuminate\Http\JsonResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actual();

        if (! $tenant || ! $tenant->sol_user || ! $tenant->sol_pass) {
            return response()->json(['ok' => false, 'mensaje' => 'Credenciales SOL no configuradas o empresa no registrada.']);
        }

        \Illuminate\Support\Facades\Gate::authorize('editar-empresa', $tenant);

        return response()->json([
            'ok' => true,
            'mensaje' => 'Credenciales guardadas correctamente.',
            'ambiente' => $tenant->environment === 'produccion' ? 'Producción SUNAT' : 'Beta / Homologación SUNAT',
            'ruc' => $tenant->ruc,
            'usuario' => $tenant->sol_user,
        ]);
    }
}

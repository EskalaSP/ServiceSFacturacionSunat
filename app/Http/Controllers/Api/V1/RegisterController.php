<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Tenant;
use App\Services\CertificateService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    use ApiResponse;

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'ruc' => 'required|string|size:11|unique:tenants,ruc',
            'razon_social' => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'direccion' => 'required|string|max:500',
            'ubigeo' => 'required|string|size:6',
            'departamento' => 'nullable|string|max:100',
            'provincia' => 'nullable|string|max:100',
            'distrito' => 'nullable|string|max:100',
            'sol_user' => 'required|string|max:20',
            'sol_pass' => 'required|string|max:50',
            'entorno' => 'sometimes|string|in:beta,production',
            'plan' => 'sometimes|string|in:free,pro,business',
            // Régimen tributario. Por defecto 'general' (18%).
            //  - general: 18% IGV
            //  - mype_restaurantes: tasa reducida según schedule (Ley 31556)
            //  - nrus: Nuevo Régimen Único Simplificado (solo boletas, IGV=0, cuota mensual fija)
            'tax_regime' => 'sometimes|string|in:general,mype_restaurantes,nrus',
            // Opcional: forzar una tasa de IGV específica (tiene precedencia sobre el régimen).
            'igv_rate_override' => 'nullable|numeric|between:0,30',
            // Solo para NRUS: categoría 1 (cuota S/20) o 2 (cuota S/50). Ignorado para otros regímenes.
            'nrus_categoria' => 'nullable|integer|in:1,2',
            'client_id' => 'nullable|string|max:100',
            'client_secret' => 'nullable|string|max:255',
            'certificado' => 'required|file|max:100',
            'contrasena_certificado' => 'nullable|string|max:100',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $certFile = $request->file('certificado');
        $extension = strtolower($certFile->getClientOriginalExtension());

        if (! in_array($extension, ['pfx', 'p12', 'pem', 'cer', 'crt'])) {
            return $this->error('Formato de certificado no soportado. Use .pfx, .p12, .pem, .cer o .crt', 422);
        }

        if (in_array($extension, ['pfx', 'p12']) && ! $request->filled('contrasena_certificado')) {
            return $this->error('El campo contrasena_certificado es obligatorio para archivos .pfx/.p12', 422);
        }

        $certService = new CertificateService();
        try {
            $pemContent = $certService->convertToPem(
                file_get_contents($certFile->getRealPath()),
                $extension,
                $request->input('contrasena_certificado')
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $plans = config('facturacion.plans');
        $plan = $request->input('plan', 'free');

        $tenant = Tenant::create([
            'ruc' => $request->input('ruc'),
            'razon_social' => $request->input('razon_social'),
            'nombre_comercial' => $request->input('nombre_comercial'),
            'direccion' => $request->input('direccion'),
            'ubigeo' => $request->input('ubigeo'),
            'departamento' => $request->input('departamento'),
            'provincia' => $request->input('provincia'),
            'distrito' => $request->input('distrito'),
            'sol_user' => $request->input('sol_user'),
            'sol_pass' => $request->input('sol_pass'),
            'client_id' => $request->input('client_id'),
            'client_secret' => $request->input('client_secret'),
            'certificate_password' => $request->input('contrasena_certificado'),
            'environment' => $request->input('entorno', 'beta'),
            'plan' => $plan,
            'tax_regime' => $request->input('tax_regime', 'general'),
            'igv_rate_override' => $request->input('igv_rate_override'),
            'nrus_categoria' => $request->input('tax_regime') === 'nrus' ? $request->input('nrus_categoria') : null,
            'max_documents_month' => $plans[$plan]['max_documents'] ?? 20,
            'is_active' => true,
        ]);

        $storage = new DocumentStorageService();
        $certPath = $storage->storeCertificate($tenant, $pemContent);

        $updateData = ['certificate_path' => $certPath];

        if ($request->hasFile('logo')) {
            $updateData['logo_path'] = $request->file('logo')->store(
                "logos/{$tenant->ruc}",
                'public'
            );
        }

        $tenant->update($updateData);

        return $this->created([
            'tenant_id' => $tenant->id,
            'ruc' => $tenant->ruc,
            'razon_social' => $tenant->razon_social,
            'entorno' => $tenant->environment,
            'plan' => $tenant->plan,
            'tax_regime' => $tenant->tax_regime,
            'igv_rate_override' => $tenant->igv_rate_override,
            'nrus_categoria' => $tenant->nrus_categoria,
            'api_key' => $tenant->api_key,
            'api_secret' => $tenant->api_secret,
            'importante' => 'Guarde sus credenciales. El api_secret NO se puede recuperar.',
        ]);
    }
}

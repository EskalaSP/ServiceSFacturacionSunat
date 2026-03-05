<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Sucursal;
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
            'sol_user' => 'required|string|max:20',
            'sol_pass' => 'required|string|max:50',
            'environment' => 'sometimes|string|in:beta,production',
            'plan' => 'sometimes|string|in:free,pro,business',
            'client_id' => 'nullable|string|max:100',
            'client_secret' => 'nullable|string|max:255',
            'certificate' => 'required|file|max:100',
            'certificate_password' => 'nullable|string|max:100',
        ]);

        // Validar extensión del certificado
        $certFile = $request->file('certificate');
        $extension = strtolower($certFile->getClientOriginalExtension());

        if (! in_array($extension, ['pfx', 'p12', 'pem', 'cer', 'crt'])) {
            return $this->error('Formato de certificado no soportado. Use .pfx, .p12, .pem, .cer o .crt', 422);
        }

        // Si es PFX/P12, el password es obligatorio
        if (in_array($extension, ['pfx', 'p12']) && ! $request->filled('certificate_password')) {
            return $this->error('El campo certificate_password es obligatorio para archivos .pfx/.p12', 422);
        }

        // Convertir a PEM
        $certService = new CertificateService();
        try {
            $pemContent = $certService->convertToPem(
                file_get_contents($certFile->getRealPath()),
                $extension,
                $request->input('certificate_password')
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
            'sol_user' => $request->input('sol_user'),
            'sol_pass' => $request->input('sol_pass'),
            'client_id' => $request->input('client_id'),
            'client_secret' => $request->input('client_secret'),
            'certificate_password' => $request->input('certificate_password'),
            'environment' => $request->input('environment', 'beta'),
            'plan' => $plan,
            'max_documents_month' => $plans[$plan]['max_documents'] ?? 20,
            'is_active' => true,
        ]);

        // Guardar certificado convertido a PEM
        $storage = new DocumentStorageService();
        $certPath = $storage->storeCertificate($tenant, $pemContent);
        $tenant->update(['certificate_path' => $certPath]);

        // Crear sucursal principal automáticamente
        $sucursal = Sucursal::create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Principal',
            'cod_local' => '0000',
            'direccion' => $request->input('direccion'),
            'ubigeo' => $request->input('ubigeo'),
            'is_principal' => true,
            'is_active' => true,
        ]);

        return $this->created([
            'tenant_id' => $tenant->id,
            'ruc' => $tenant->ruc,
            'razon_social' => $tenant->razon_social,
            'environment' => $tenant->environment,
            'plan' => $tenant->plan,
            'api_key' => $tenant->api_key,
            'api_secret' => $tenant->api_secret,
            'sucursal_principal' => [
                'id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
                'cod_local' => $sucursal->cod_local,
            ],
            'importante' => 'Guarde sus credenciales. El api_secret NO se puede recuperar.',
        ]);
    }
}

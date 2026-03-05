<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TenantResource;
use App\Http\Traits\ApiResponse;
use App\Services\CertificateService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TenantController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        return $this->success(new TenantResource($tenant));
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'razon_social' => 'sometimes|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:500',
            'ubigeo' => 'nullable|string|size:6',
            'webhook_url' => 'nullable|url|max:500',
            'client_id' => 'nullable|string|max:100',
            'client_secret' => 'nullable|string|max:255',
        ]);

        $tenant = $request->get('tenant');
        $tenant->update($request->only([
            'razon_social',
            'nombre_comercial',
            'direccion',
            'ubigeo',
            'webhook_url',
            'client_id',
            'client_secret',
        ]));

        Cache::forget("tenant:key:{$tenant->api_key}");

        return $this->success(new TenantResource($tenant->fresh()), 'Tenant actualizado.');
    }

    public function uploadCertificate(Request $request): JsonResponse
    {
        $request->validate([
            'certificate' => 'required|file|max:100',
            'certificate_password' => 'nullable|string|max:100',
        ]);

        $tenant = $request->get('tenant');

        $certFile = $request->file('certificate');
        $extension = strtolower($certFile->getClientOriginalExtension());

        if (! in_array($extension, ['pfx', 'p12', 'pem', 'cer', 'crt'])) {
            return $this->error('Formato no soportado. Use .pfx, .p12, .pem, .cer o .crt', 422);
        }

        if (in_array($extension, ['pfx', 'p12']) && ! $request->filled('certificate_password')) {
            return $this->error('El campo certificate_password es obligatorio para archivos .pfx/.p12', 422);
        }

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

        $storage = new DocumentStorageService();
        $certPath = $storage->storeCertificate($tenant, $pemContent);
        $tenant->update([
            'certificate_path' => $certPath,
            'certificate_password' => $request->input('certificate_password'),
        ]);

        Cache::forget("tenant:key:{$tenant->api_key}");

        return $this->success(null, 'Certificado actualizado y convertido a PEM.');
    }
}

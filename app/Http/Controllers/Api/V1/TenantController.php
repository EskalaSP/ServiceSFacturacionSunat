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
            'departamento' => 'nullable|string|max:100',
            'provincia' => 'nullable|string|max:100',
            'distrito' => 'nullable|string|max:100',
            'sol_user' => 'nullable|string|max:50',
            'sol_pass' => 'nullable|string|max:50',
            'client_id' => 'nullable|string|max:100',
            'client_secret' => 'nullable|string|max:255',
            'entorno' => 'nullable|string|in:beta,production',
            'url_webhook' => 'nullable|url|max:500',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'telefonos' => 'nullable|array|max:5',
            'telefonos.*' => 'string|max:20',
            'emails' => 'nullable|array|max:5',
            'emails.*' => 'email|max:100',
            'cuentas_bancarias' => 'nullable|array|max:5',
            'cuentas_bancarias.*.banco' => 'required|string|max:50',
            'cuentas_bancarias.*.tipo_cuenta' => 'required|string|max:30',
            'cuentas_bancarias.*.moneda' => 'required|string|in:PEN,USD',
            'cuentas_bancarias.*.numero' => 'required|string|max:30',
            'cuentas_bancarias.*.cci' => 'nullable|string|max:25',
            'cuentas_bancarias.*.titular' => 'nullable|string|max:100',
            'billeteras_digitales' => 'nullable|array|max:5',
            'billeteras_digitales.*.tipo' => 'required|string|in:yape,plin,tunki,otro',
            'billeteras_digitales.*.numero' => 'required|string|max:20',
            'billeteras_digitales.*.titular' => 'nullable|string|max:100',
            'mensaje_agradecimiento' => 'nullable|string|max:500',
            'mensaje_promocional' => 'nullable|string|max:500',
        ]);

        $tenant = $request->get('tenant');

        $data = array_filter([
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
            'environment' => $request->input('entorno'),
            'webhook_url' => $request->input('url_webhook'),
            'telefonos' => $request->input('telefonos'),
            'emails' => $request->input('emails'),
            'cuentas_bancarias' => $request->input('cuentas_bancarias'),
            'billeteras_digitales' => $request->input('billeteras_digitales'),
            'mensaje_agradecimiento' => $request->input('mensaje_agradecimiento'),
            'mensaje_promocional' => $request->input('mensaje_promocional'),
        ], fn ($v) => $v !== null);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->storeAs(
                "logos/{$tenant->ruc}",
                'logo.' . $request->file('logo')->getClientOriginalExtension(),
                'public'
            );
        }

        $tenant->update($data);

        Cache::forget("tenant:key:{$tenant->api_key}");

        return $this->success(new TenantResource($tenant->fresh()), 'Empresa actualizada.');
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $tenant = $request->get('tenant');

        $logoPath = $request->file('logo')->storeAs(
            "logos/{$tenant->ruc}",
            'logo.' . $request->file('logo')->getClientOriginalExtension(),
            'public'
        );

        $tenant->update(['logo_path' => $logoPath]);

        Cache::forget("tenant:key:{$tenant->api_key}");

        return $this->success([
            'logo_path' => $logoPath,
        ], 'Logo actualizado.');
    }

    public function uploadCertificate(Request $request): JsonResponse
    {
        $request->validate([
            'certificado' => 'required|file|max:100',
            'contrasena_certificado' => 'nullable|string|max:100',
        ]);

        $tenant = $request->get('tenant');

        $certFile = $request->file('certificado');
        $extension = strtolower($certFile->getClientOriginalExtension());

        if (! in_array($extension, ['pfx', 'p12', 'pem', 'cer', 'crt'])) {
            return $this->error('Formato no soportado. Use .pfx, .p12, .pem, .cer o .crt', 422);
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

        $storage = new DocumentStorageService();
        $certPath = $storage->storeCertificate($tenant, $pemContent);
        $tenant->update([
            'certificate_path' => $certPath,
            'certificate_password' => $request->input('contrasena_certificado'),
        ]);

        Cache::forget("tenant:key:{$tenant->api_key}");

        return $this->success(null, 'Certificado actualizado y convertido a PEM.');
    }
}

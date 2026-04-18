<?php

namespace App\Http\Controllers\Api\V1\Sire;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Sire\Exceptions\SireException;
use App\Sire\Services\Auth\SireAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SireActivacionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SireAuthService $auth,
    ) {}

    /**
     * POST /v1/sire/activar
     *
     * Verifica las credenciales SIRE del tenant contra SUNAT (solicitando un token real)
     * y marca sire_enabled = true si pasan la prueba.
     *
     * Este es el único lugar donde el tenant "se presenta" a SIRE. El resto de endpoints
     * asume que sire_enabled = true.
     */
    public function activar(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        try {
            $token = $this->auth->getToken($tenant);

            $tenant->update(['sire_enabled' => true]);

            return $this->success([
                'sire_enabled' => true,
                'ruc'          => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
                'mensaje'      => 'SIRE activado. Ya puedes consumir los endpoints /v1/sire/*.',
                'token_preview'=> substr($token, 0, 20) . '...',
            ]);
        } catch (SireException $e) {
            return $this->error($e->getMessage(), $e->httpStatus, [
                'sunat_code' => $e->sunatCode,
            ]);
        }
    }

    /**
     * POST /v1/sire/desactivar
     */
    public function desactivar(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $tenant->update(['sire_enabled' => false]);
        $this->auth->invalidate($tenant);

        return $this->success([
            'sire_enabled' => false,
            'mensaje'      => 'SIRE desactivado. El token cacheado fue invalidado.',
        ]);
    }
}

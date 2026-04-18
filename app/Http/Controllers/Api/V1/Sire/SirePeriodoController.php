<?php

namespace App\Http\Controllers\Api\V1\Sire;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Sire\Enums\CodLibro;
use App\Sire\Exceptions\SireException;
use App\Sire\Services\Periodos\PeriodoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SirePeriodoController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PeriodoService $periodoService,
    ) {}

    /**
     * GET /v1/sire/periodos?libro=rce
     *
     * Lista los años y periodos del contribuyente habilitados en el padrón SUNAT.
     * Envuelve el servicio 5.33 del manual v22.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'libro' => 'sometimes|string|in:rce,rvie',
        ]);

        $libro = match ($validated['libro'] ?? 'rce') {
            'rvie'  => CodLibro::RVIE,
            default => CodLibro::RCE,
        };

        $tenant = $request->get('tenant');

        try {
            $data = $this->periodoService->listar($tenant, $libro);

            return $this->success([
                'libro'      => $libro->value,
                'libro_desc' => $libro->label(),
                'ejercicios' => $data,
            ]);
        } catch (SireException $e) {
            return $this->error($e->getMessage(), $e->httpStatus, [
                'sunat_code' => $e->sunatCode,
            ]);
        }
    }
}

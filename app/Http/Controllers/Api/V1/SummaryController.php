<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateSummaryAction;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Services\Greenter\GreenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    use ApiResponse;

    public function store(Request $request, CreateSummaryAction $action): JsonResponse
    {
        $request->validate([
            'correlativo' => 'required|string',
            'fecha_generacion' => 'required|date',
            'fecha_resumen' => 'required|date',
            'detalles' => 'required|array|min:1',
            'detalles.*.tipo_documento' => 'required|string|in:03,07,08',
            'detalles.*.serie_nro' => 'required|string',
            'detalles.*.estado' => 'required|string|in:1,3',
            'detalles.*.total' => 'required|numeric',
        ]);

        $tenant = $request->get('tenant');

        try {
            $result = $action->execute($tenant, $request->all());

            if ($result['success']) {
                return $this->created($result);
            }

            return $this->error($result['error_message'] ?? 'Error al enviar resumen', 422);
        } catch (\Throwable $e) {
            return $this->error('Error al crear resumen: ' . $e->getMessage(), 500);
        }
    }

    public function checkStatus(Request $request, string $ticket): JsonResponse
    {
        $tenant = $request->get('tenant');

        try {
            $service = new GreenterService($tenant);
            $result = $service->getStatus($ticket);

            return $this->success($result);
        } catch (\Throwable $e) {
            return $this->error('Error al consultar ticket: ' . $e->getMessage(), 500);
        }
    }
}

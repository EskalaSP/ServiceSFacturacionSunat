<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateVoidedAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreVoidedRequest;
use App\Http\Traits\ApiResponse;
use App\Services\Greenter\GreenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoidedController extends Controller
{
    use ApiResponse;

    public function store(StoreVoidedRequest $request, CreateVoidedAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');

        try {
            $result = $action->execute($tenant, $request->validated());

            if ($result['success']) {
                return $this->created($result);
            }

            return $this->error($result['error_message'] ?? 'Error al enviar comunicación de baja', 422);
        } catch (\Throwable $e) {
            return $this->error('Error: ' . $e->getMessage(), 500);
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

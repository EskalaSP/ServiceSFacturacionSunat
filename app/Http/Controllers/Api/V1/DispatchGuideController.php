<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateDispatchGuideAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDispatchGuideRequest;
use App\Http\Resources\Api\V1\DispatchGuideResource;
use App\Http\Traits\ApiResponse;
use App\Models\DispatchGuide;
use App\Services\Greenter\GreenterService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispatchGuideController extends Controller
{
    use ApiResponse;

    public function store(StoreDispatchGuideRequest $request, CreateDispatchGuideAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');

        try {
            $guide = $action->execute($tenant, $request->validated());

            return $this->created(new DispatchGuideResource($guide), 'Guía de remisión creada y enviada a SUNAT.');
        } catch (\Throwable $e) {
            return $this->error('Error al crear guía: '.$e->getMessage(), 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $guides = DispatchGuide::forTenant($tenant->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->success([
            'data' => DispatchGuideResource::collection($guides),
            'pagination' => [
                'current_page' => $guides->currentPage(),
                'last_page' => $guides->lastPage(),
                'per_page' => $guides->perPage(),
                'total' => $guides->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $guide = DispatchGuide::forTenant($tenant->id)->findOrFail($id);

        return $this->success(new DispatchGuideResource($guide));
    }

    public function checkStatus(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $guide = DispatchGuide::forTenant($tenant->id)->findOrFail($id);

        if (! $guide->ticket) {
            return $this->error('No hay ticket disponible para consultar', 404);
        }

        if (in_array($guide->sunat_status, ['aceptado', 'rechazado'])) {
            return $this->success(new DispatchGuideResource($guide), 'Estado ya resuelto.');
        }

        try {
            $service = new GreenterService($tenant);
            $storage = new DocumentStorageService();
            $api = $service->createApi();
            $result = $api->getStatus($guide->ticket);

            if ($result->isSuccess()) {
                $cdr = $result->getCdrResponse();
                $guide->update([
                    'sunat_status' => $cdr->isAccepted() ? 'aceptado' : 'rechazado',
                    'sunat_code' => $cdr->getCode(),
                    'sunat_description' => $cdr->getDescription(),
                    'sent_at' => now(),
                ]);

                // Guardar CDR en disco
                $cdrZip = $result->getCdrZip();
                if ($cdrZip) {
                    $storage->storeCdr($guide, $tenant, $cdrZip);
                }
            }

            return $this->success(new DispatchGuideResource($guide->fresh()));
        } catch (\Throwable $e) {
            return $this->error('Error al consultar estado: '.$e->getMessage(), 500);
        }
    }
}

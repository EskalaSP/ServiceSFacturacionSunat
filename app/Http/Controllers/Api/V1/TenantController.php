<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TenantResource;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ]);

        $tenant = $request->get('tenant');
        $tenant->update($request->only([
            'razon_social',
            'nombre_comercial',
            'direccion',
            'ubigeo',
            'webhook_url',
        ]));

        return $this->success(new TenantResource($tenant->fresh()), 'Tenant actualizado.');
    }
}

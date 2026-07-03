<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClientRequest;
use App\Http\Resources\Api\V1\ClientResource;
use App\Http\Traits\ApiResponse;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = Client::forTenant($tenant->id)->orderBy('razon_social');

        if ($request->has('buscar')) {
            $search = $request->input('buscar');
            $query->where(function ($q) use ($search) {
                $q->where('razon_social', 'like', "%{$search}%")
                    ->orWhere('numero_documento', 'like', "%{$search}%");
            });
        }

        $clients = $query->paginate($request->integer('por_pagina', 15));

        return $this->success([
            'datos' => ClientResource::collection($clients),
            'paginacion' => [
                'pagina_actual' => $clients->currentPage(),
                'ultima_pagina' => $clients->lastPage(),
                'por_pagina' => $clients->perPage(),
                'total' => $clients->total(),
            ],
        ]);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        $data = $request->validated();

        $client = Client::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'tipo_documento' => $data['tipo_documento'],
                'numero_documento' => $data['numero_documento'],
            ],
            collect($data)->only(['razon_social', 'nombre_comercial', 'direccion', 'email', 'telefono', 'ubigeo'])->toArray()
        );

        return $this->created(new ClientResource($client));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $client = Client::forTenant($tenant->id)->findOrFail($id);

        return $this->success(new ClientResource($client));
    }

    public function update(StoreClientRequest $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $client = Client::forTenant($tenant->id)->findOrFail($id);
        $client->update(
            collect($request->validated())
                ->only(['razon_social', 'nombre_comercial', 'direccion', 'email', 'telefono', 'ubigeo'])
                ->toArray()
        );

        return $this->success(new ClientResource($client), 'Cliente actualizado.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $client = Client::forTenant($tenant->id)->findOrFail($id);
        $client->delete();

        return $this->success(null, 'Cliente eliminado.');
    }
}

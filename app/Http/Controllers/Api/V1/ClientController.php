<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('razon_social', 'like', "%{$search}%")
                    ->orWhere('numero_documento', 'like', "%{$search}%");
            });
        }

        $clients = $query->paginate($request->integer('per_page', 15));

        return $this->success([
            'data' => ClientResource::collection($clients),
            'pagination' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tipo_documento' => 'required|string|in:0,1,4,6,7,A',
            'numero_documento' => 'required|string|max:15',
            'razon_social' => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:500',
            'email' => 'nullable|email',
            'telefono' => 'nullable|string|max:20',
        ]);

        $tenant = $request->get('tenant');

        $client = Client::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'tipo_documento' => $request->input('tipo_documento'),
                'numero_documento' => $request->input('numero_documento'),
            ],
            $request->only(['razon_social', 'nombre_comercial', 'direccion', 'email', 'telefono'])
        );

        return $this->created(new ClientResource($client));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $client = Client::forTenant($tenant->id)->findOrFail($id);

        return $this->success(new ClientResource($client));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'razon_social' => 'sometimes|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:500',
            'email' => 'nullable|email',
            'telefono' => 'nullable|string|max:20',
        ]);

        $tenant = $request->get('tenant');
        $client = Client::forTenant($tenant->id)->findOrFail($id);
        $client->update($request->only(['razon_social', 'nombre_comercial', 'direccion', 'email', 'telefono']));

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

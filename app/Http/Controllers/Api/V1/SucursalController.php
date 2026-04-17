<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Sucursal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $sucursales = Sucursal::forTenant($tenant->id)
            ->withCount('series')
            ->orderByDesc('is_principal')
            ->orderBy('nombre')
            ->get();

        return $this->success($sucursales->map(fn ($s) => $this->format($s)));
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $request->validate([
            'nombre' => 'required|string|max:100',
            'cod_local' => 'required|string|size:4|regex:/^[0-9]{4}$/',
            'direccion' => 'required|string|max:500',
            'ubigeo' => 'required|string|size:6',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'es_principal' => 'sometimes|boolean',
        ], [
            'cod_local.regex' => 'El código de local debe ser 4 dígitos (ej: 0000, 0001).',
        ]);

        $exists = Sucursal::where('tenant_id', $tenant->id)
            ->where('cod_local', $request->input('cod_local'))
            ->exists();

        if ($exists) {
            return $this->error("El código de local {$request->input('cod_local')} ya existe.", 409);
        }

        if ($request->boolean('es_principal')) {
            Sucursal::where('tenant_id', $tenant->id)->update(['is_principal' => false]);
        }

        $isFirst = Sucursal::where('tenant_id', $tenant->id)->count() === 0;

        $sucursal = Sucursal::create([
            'tenant_id' => $tenant->id,
            'nombre' => $request->input('nombre'),
            'cod_local' => $request->input('cod_local'),
            'direccion' => $request->input('direccion'),
            'ubigeo' => $request->input('ubigeo'),
            'telefono' => $request->input('telefono'),
            'email' => $request->input('email'),
            'is_principal' => $request->boolean('es_principal') || $isFirst,
            'is_active' => true,
        ]);

        return $this->created($this->format($sucursal));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $sucursal = Sucursal::forTenant($tenant->id)->withCount('series')->findOrFail($id);

        return $this->success($this->format($sucursal));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $sucursal = Sucursal::forTenant($tenant->id)->findOrFail($id);

        $request->validate([
            'nombre' => 'sometimes|string|max:100',
            'direccion' => 'sometimes|string|max:500',
            'ubigeo' => 'sometimes|string|size:6',
            'telefono' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:255',
            'es_principal' => 'sometimes|boolean',
            'activo' => 'sometimes|boolean',
        ]);

        if ($request->boolean('es_principal')) {
            Sucursal::where('tenant_id', $tenant->id)
                ->where('id', '!=', $sucursal->id)
                ->update(['is_principal' => false]);
        }

        $data = $request->only(['nombre', 'direccion', 'ubigeo', 'telefono', 'email']);

        if ($request->has('es_principal')) {
            $data['is_principal'] = $request->boolean('es_principal');
        }

        if ($request->has('activo')) {
            $data['is_active'] = $request->boolean('activo');
        }

        $sucursal->update($data);

        return $this->success($this->format($sucursal), 'Sucursal actualizada.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $sucursal = Sucursal::forTenant($tenant->id)->withCount('series')->findOrFail($id);

        if ($sucursal->series_count > 0) {
            return $this->error("No se puede eliminar: tiene {$sucursal->series_count} serie(s) asociada(s). Reasigne las series primero.", 409);
        }

        if ($sucursal->is_principal) {
            return $this->error('No se puede eliminar la sucursal principal. Asigne otra como principal primero.', 409);
        }

        $sucursal->delete();

        return $this->success(null, 'Sucursal eliminada.');
    }

    private function format(Sucursal $sucursal): array
    {
        return [
            'id' => $sucursal->id,
            'nombre' => $sucursal->nombre,
            'cod_local' => $sucursal->cod_local,
            'direccion' => $sucursal->direccion,
            'ubigeo' => $sucursal->ubigeo,
            'telefono' => $sucursal->telefono,
            'email' => $sucursal->email,
            'es_principal' => $sucursal->is_principal,
            'activo' => $sucursal->is_active,
            'total_series' => $sucursal->series_count ?? 0,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SucursalController extends Controller
{
    public function index(Tenant $tenant): Response
    {
        return Inertia::render('admin/sucursales/index', [
            'tenant' => $this->tenantMini($tenant),
            'sucursales' => $tenant->sucursales()
                ->orderByDesc('is_principal')
                ->orderBy('nombre')
                ->get()
                ->map(fn (Sucursal $s) => [
                    'id' => $s->id,
                    'nombre' => $s->nombre,
                    'cod_local' => $s->cod_local,
                    'direccion' => $s->direccion,
                    'ubigeo' => $s->ubigeo,
                    'telefono' => $s->telefono,
                    'email' => $s->email,
                    'is_principal' => (bool) $s->is_principal,
                    'is_active' => (bool) $s->is_active,
                ])->all(),
        ]);
    }

    public function create(Tenant $tenant): Response
    {
        return Inertia::render('admin/sucursales/form', [
            'tenant' => $this->tenantMini($tenant),
            'sucursal' => [
                'id' => null,
                'nombre' => '',
                'cod_local' => '0000',
                'direccion' => '',
                'ubigeo' => '',
                'telefono' => '',
                'email' => '',
                'is_principal' => false,
                'is_active' => true,
            ],
            'modo' => 'crear',
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $this->validar($request, $tenant);

        // Solo puede haber una principal
        if (! empty($data['is_principal'])) {
            $tenant->sucursales()->update(['is_principal' => false]);
        }

        $tenant->sucursales()->create($data);

        return redirect()->route('admin.sucursales.index', $tenant)
            ->with('success', 'Sucursal creada.');
    }

    public function edit(Tenant $tenant, Sucursal $sucursal): Response
    {
        abort_unless($sucursal->tenant_id === $tenant->id, 404);

        return Inertia::render('admin/sucursales/form', [
            'tenant' => $this->tenantMini($tenant),
            'sucursal' => [
                'id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
                'cod_local' => $sucursal->cod_local,
                'direccion' => $sucursal->direccion ?? '',
                'ubigeo' => $sucursal->ubigeo ?? '',
                'telefono' => $sucursal->telefono ?? '',
                'email' => $sucursal->email ?? '',
                'is_principal' => (bool) $sucursal->is_principal,
                'is_active' => (bool) $sucursal->is_active,
            ],
            'modo' => 'editar',
        ]);
    }

    private function tenantMini(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'ruc' => $tenant->ruc,
            'razon_social' => $tenant->razon_social,
        ];
    }

    public function update(Request $request, Tenant $tenant, Sucursal $sucursal): RedirectResponse
    {
        abort_unless($sucursal->tenant_id === $tenant->id, 404);

        $data = $this->validar($request, $tenant, $sucursal->id);

        if (! empty($data['is_principal'])) {
            $tenant->sucursales()->where('id', '!=', $sucursal->id)->update(['is_principal' => false]);
        }

        $sucursal->update($data);

        return redirect()->route('admin.sucursales.index', $tenant)
            ->with('success', 'Sucursal actualizada.');
    }

    public function destroy(Tenant $tenant, Sucursal $sucursal): RedirectResponse
    {
        abort_unless($sucursal->tenant_id === $tenant->id, 404);
        $sucursal->delete();

        return redirect()->route('admin.sucursales.index', $tenant)
            ->with('success', 'Sucursal eliminada.');
    }

    private function validar(Request $request, Tenant $tenant, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:100',
            'cod_local' => 'required|string|size:4',
            'direccion' => 'nullable|string|max:500',
            'ubigeo' => 'nullable|string|size:6',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'is_principal' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_principal'] = $request->boolean('is_principal');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}

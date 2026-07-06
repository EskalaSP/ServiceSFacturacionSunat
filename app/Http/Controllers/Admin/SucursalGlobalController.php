<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Listado global de sucursales (todas las empresas).
 * Se usa desde el sidebar cuando el admin no tiene una empresa seleccionada.
 * Las acciones (crear/editar/borrar) siguen apuntando al controlador scoped
 * por tenant en admin/empresas/{tenant}/sucursales/*.
 */
class SucursalGlobalController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Sucursal::query()
            ->with('tenant:id,ruc,razon_social')
            ->when($request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', $request->input('tenant_id')))
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $b = $request->input('buscar');
                $q->where(fn ($qq) => $qq->where('nombre', 'like', "%{$b}%")
                    ->orWhere('cod_local', 'like', "%{$b}%")
                    ->orWhereHas('tenant', fn ($tq) => $tq->where('ruc', 'like', "%{$b}%")
                        ->orWhere('razon_social', 'like', "%{$b}%")));
            })
            ->orderByDesc('is_principal')
            ->orderBy('tenant_id')
            ->orderBy('nombre');

        return Inertia::render('admin/sucursales/todas', [
            'sucursales' => $query->paginate(20)->withQueryString()->through(fn (Sucursal $s) => [
                'id' => $s->id,
                'nombre' => $s->nombre,
                'cod_local' => $s->cod_local,
                'direccion' => $s->direccion,
                'is_principal' => (bool) $s->is_principal,
                'is_active' => (bool) $s->is_active,
                'tenant' => [
                    'id' => $s->tenant->id,
                    'ruc' => $s->tenant->ruc,
                    'razon_social' => $s->tenant->razon_social,
                ],
            ]),
            'empresas' => Tenant::orderBy('razon_social')->get(['id', 'ruc', 'razon_social'])
                ->map(fn (Tenant $t) => ['id' => $t->id, 'ruc' => $t->ruc, 'razon_social' => $t->razon_social])
                ->all(),
            'filtros' => [
                'buscar' => $request->input('buscar', ''),
                'tenant_id' => $request->input('tenant_id', ''),
            ],
        ]);
    }
}

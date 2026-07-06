<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Serie;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Listado global de series (todas las empresas).
 * Se usa desde el sidebar; para crear/editar redirige al controlador scoped
 * por tenant en admin/empresas/{tenant}/series/*.
 */
class SerieGlobalController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Serie::query()
            ->with(['tenant:id,ruc,razon_social', 'sucursal:id,nombre'])
            ->when($request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', $request->input('tenant_id')))
            ->when($request->filled('tipo_documento'), fn ($q) => $q->where('tipo_documento', $request->input('tipo_documento')))
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $b = $request->input('buscar');
                $q->where(fn ($qq) => $qq->where('serie', 'like', "%{$b}%")
                    ->orWhereHas('tenant', fn ($tq) => $tq->where('ruc', 'like', "%{$b}%")
                        ->orWhere('razon_social', 'like', "%{$b}%")));
            })
            ->orderBy('tenant_id')
            ->orderBy('tipo_documento')
            ->orderBy('serie');

        return Inertia::render('admin/series/todas', [
            'series' => $query->paginate(20)->withQueryString()->through(fn (Serie $s) => [
                'id' => $s->id,
                'tipo_documento' => $s->tipo_documento,
                'tipo_nombre' => Serie::TIPOS_NOMBRE[$s->tipo_documento] ?? $s->tipo_documento,
                'serie' => $s->serie,
                'correlativo' => (int) $s->correlativo,
                'sucursal_nombre' => $s->sucursal?->nombre,
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
            'tipos' => Serie::TIPOS_NOMBRE,
            'filtros' => [
                'buscar' => $request->input('buscar', ''),
                'tenant_id' => $request->input('tenant_id', ''),
                'tipo_documento' => $request->input('tipo_documento', ''),
            ],
        ]);
    }
}

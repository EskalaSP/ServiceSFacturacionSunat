<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Serie;
use App\Models\Sucursal;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SerieController extends Controller
{
    public function index(Tenant $tenant): Response
    {
        return Inertia::render('admin/series/index', [
            'tenant' => $this->tenantMini($tenant),
            'series' => $tenant->series()
                ->with('sucursal')
                ->orderBy('tipo_documento')
                ->orderBy('serie')
                ->get()
                ->map(fn (Serie $s) => [
                    'id' => $s->id,
                    'tipo_documento' => $s->tipo_documento,
                    'tipo_nombre' => Serie::TIPOS_NOMBRE[$s->tipo_documento] ?? $s->tipo_documento,
                    'serie' => $s->serie,
                    'correlativo' => (int) $s->correlativo,
                    'sucursal_nombre' => $s->sucursal?->nombre,
                    'is_active' => (bool) $s->is_active,
                ])->all(),
        ]);
    }

    public function create(Tenant $tenant): Response
    {
        return Inertia::render('admin/series/form', [
            'tenant' => $this->tenantMini($tenant),
            'serie' => [
                'id' => null,
                'tipo_documento' => '01',
                'serie' => '',
                'correlativo' => 0,
                'sucursal_id' => null,
                'is_active' => true,
            ],
            'sucursales' => $this->sucursalesOpciones($tenant),
            'prefijos' => Serie::PREFIJOS,
            'modo' => 'crear',
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $this->validar($request, $tenant);

        $tenant->series()->create($data);

        return redirect()->route('admin.series.index', $tenant)
            ->with('success', 'Serie creada.');
    }

    public function edit(Tenant $tenant, Serie $serie): Response
    {
        abort_unless($serie->tenant_id === $tenant->id, 404);

        return Inertia::render('admin/series/form', [
            'tenant' => $this->tenantMini($tenant),
            'serie' => [
                'id' => $serie->id,
                'tipo_documento' => $serie->tipo_documento,
                'serie' => $serie->serie,
                'correlativo' => (int) $serie->correlativo,
                'sucursal_id' => $serie->sucursal_id,
                'is_active' => (bool) $serie->is_active,
            ],
            'sucursales' => $this->sucursalesOpciones($tenant),
            'prefijos' => Serie::PREFIJOS,
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

    private function sucursalesOpciones(Tenant $tenant): array
    {
        return $tenant->sucursales()
            ->where('is_active', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn (Sucursal $s) => [
                'id' => $s->id,
                'nombre' => $s->nombre,
                'cod_local' => $s->cod_local,
            ])->all();
    }

    public function update(Request $request, Tenant $tenant, Serie $serie): RedirectResponse
    {
        abort_unless($serie->tenant_id === $tenant->id, 404);

        $data = $this->validar($request, $tenant, $serie->id);
        $serie->update($data);

        return redirect()->route('admin.series.index', $tenant)
            ->with('success', 'Serie actualizada.');
    }

    public function destroy(Tenant $tenant, Serie $serie): RedirectResponse
    {
        abort_unless($serie->tenant_id === $tenant->id, 404);
        $serie->delete();

        return redirect()->route('admin.series.index', $tenant)
            ->with('success', 'Serie eliminada.');
    }

    public function toggle(Tenant $tenant, Serie $serie): RedirectResponse
    {
        abort_unless($serie->tenant_id === $tenant->id, 404);
        $serie->update(['is_active' => ! $serie->is_active]);

        return back()->with('success', 'Estado de la serie actualizado.');
    }

    private function validar(Request $request, Tenant $tenant, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'tipo_documento' => 'required|string|in:' . implode(',', array_keys(Serie::TIPOS_NOMBRE)),
            'serie' => 'required|string|size:4|regex:/^[A-Z][A-Z0-9]{3}$/',
            'correlativo' => 'nullable|integer|min:0',
            'sucursal_id' => 'nullable|exists:sucursales,id',
            'is_active' => 'nullable|boolean',
        ]);

        // Validar prefijo por tipo
        $tipo = $data['tipo_documento'];
        $prefijos = Serie::PREFIJOS[$tipo] ?? [];
        if (! in_array(substr($data['serie'], 0, 1), $prefijos, true)) {
            abort(back()->withErrors([
                'serie' => "El prefijo de la serie no es válido para {$tipo}. Debe empezar con: " . implode(' o ', $prefijos),
            ])->withInput()->send(), 302);
        }

        // Unicidad tipo+serie por tenant
        $duplicado = $tenant->series()
            ->where('tipo_documento', $tipo)
            ->where('serie', $data['serie'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($duplicado) {
            abort(back()->withErrors([
                'serie' => "Ya existe una serie {$data['serie']} para el tipo {$tipo} en esta empresa.",
            ])->withInput()->send(), 302);
        }

        $data['correlativo'] = $data['correlativo'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}

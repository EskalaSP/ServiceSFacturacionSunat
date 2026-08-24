<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Serie;
use App\Models\Sucursal;
use App\Models\Tenant;
use App\Services\Tenancy\EmpresaActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Series y correlativos de la EMPRESA ACTIVA (para el dueño). */
class SerieController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();
        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }
        Gate::authorize('gestionar-series', $tenant);

        return Inertia::render('sunat/series/index', [
            'series' => $tenant->series()
                ->with('sucursal')
                ->orderBy('tipo_documento')->orderBy('serie')->get()
                ->map(fn (Serie $s) => [
                    'id' => $s->id,
                    'tipo_documento' => $s->tipo_documento,
                    'tipo_nombre' => Serie::TIPOS_NOMBRE[$s->tipo_documento] ?? $s->tipo_documento,
                    'serie' => $s->serie,
                    'proximo' => (int) $s->correlativo + 1,
                    'sucursal_id' => $s->sucursal_id,
                    'sucursal_nombre' => $s->sucursal?->nombre,
                    'is_active' => (bool) $s->is_active,
                ])->all(),
            'sucursales' => $this->sucursalesOpciones($tenant),
            'tipos' => Serie::TIPOS_NOMBRE,
            'prefijos' => Serie::PREFIJOS,
        ]);
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

    public function store(Request $request): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('gestionar-series', $tenant);

        $data = $this->validar($request, $tenant);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $tenant->series()->create($data);

        return back()->with('success', 'Serie creada.');
    }

    public function update(Request $request, Serie $serie): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('gestionar-series', $tenant);
        abort_unless($serie->tenant_id === $tenant->id, 404);

        $data = $this->validar($request, $tenant, $serie->id);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $serie->update($data);

        return back()->with('success', 'Serie actualizada.');
    }

    /**
     * Valida y normaliza los datos de una serie. Devuelve el array listo para
     * create/update, o un RedirectResponse con errores si algo no cuadra.
     *
     * @return array<string, mixed>|RedirectResponse
     */
    private function validar(Request $request, Tenant $tenant, ?int $ignoreId = null): array|RedirectResponse
    {
        $data = $request->validate([
            'tipo_documento' => 'required|string|in:'.implode(',', array_keys(Serie::TIPOS_NOMBRE)),
            'serie' => 'required|string|size:4|regex:/^[A-Z][A-Z0-9]{3}$/',
            'correlativo' => 'nullable|integer|min:1',
            'sucursal_id' => 'nullable|integer|exists:sucursales,id',
            'is_active' => 'nullable|boolean',
        ]);

        $tipo = $data['tipo_documento'];

        // La sucursal (si se indica) debe pertenecer a esta empresa.
        if (! empty($data['sucursal_id']) && ! $tenant->sucursales()->whereKey($data['sucursal_id'])->exists()) {
            return back()->withErrors(['sucursal_id' => 'La sucursal seleccionada no pertenece a tu empresa.'])->withInput();
        }

        $prefijos = Serie::PREFIJOS[$tipo] ?? [];
        if ($prefijos && ! in_array(substr($data['serie'], 0, 1), $prefijos, true)) {
            return back()->withErrors(['serie' => "El prefijo no es válido para {$tipo}. Debe empezar con: ".implode(' o ', $prefijos)])->withInput();
        }

        $duplicado = $tenant->series()
            ->where('tipo_documento', $tipo)
            ->where('serie', $data['serie'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        if ($duplicado) {
            return back()->withErrors(['serie' => "Ya existe la serie {$data['serie']} para el tipo {$tipo}."])->withInput();
        }

        // El campo llega como PRÓXIMO número (el primero a emitir); se guarda como
        // "último usado" = próximo − 1, para que el correlativo entregue ese número.
        return [
            'tipo_documento' => $tipo,
            'serie' => $data['serie'],
            'correlativo' => max(1, (int) ($data['correlativo'] ?? 1)) - 1,
            'sucursal_id' => $data['sucursal_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    public function toggle(Serie $serie): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('gestionar-series', $tenant);
        abort_unless($serie->tenant_id === $tenant->id, 404);

        $serie->update(['is_active' => ! $serie->is_active]);

        return back()->with('success', 'Estado de la serie actualizado.');
    }

    public function destroy(Serie $serie): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('gestionar-series', $tenant);
        abort_unless($serie->tenant_id === $tenant->id, 404);

        $serie->delete();

        return back()->with('success', 'Serie eliminada.');
    }
}

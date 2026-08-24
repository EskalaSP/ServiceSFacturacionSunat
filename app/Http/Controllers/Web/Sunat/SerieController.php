<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Serie;
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
                ->orderBy('tipo_documento')->orderBy('serie')->get()
                ->map(fn (Serie $s) => [
                    'id' => $s->id,
                    'tipo_documento' => $s->tipo_documento,
                    'tipo_nombre' => Serie::TIPOS_NOMBRE[$s->tipo_documento] ?? $s->tipo_documento,
                    'serie' => $s->serie,
                    'proximo' => (int) $s->correlativo + 1,
                    'is_active' => (bool) $s->is_active,
                ])->all(),
            'tipos' => Serie::TIPOS_NOMBRE,
            'prefijos' => Serie::PREFIJOS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('gestionar-series', $tenant);

        $data = $request->validate([
            'tipo_documento' => 'required|string|in:'.implode(',', array_keys(Serie::TIPOS_NOMBRE)),
            'serie' => 'required|string|size:4|regex:/^[A-Z][A-Z0-9]{3}$/',
            'correlativo' => 'nullable|integer|min:1',
        ]);

        $tipo = $data['tipo_documento'];
        $prefijos = Serie::PREFIJOS[$tipo] ?? [];
        if ($prefijos && ! in_array(substr($data['serie'], 0, 1), $prefijos, true)) {
            return back()->withErrors(['serie' => "El prefijo no es válido para {$tipo}. Debe empezar con: ".implode(' o ', $prefijos)])->withInput();
        }

        $duplicado = $tenant->series()->where('tipo_documento', $tipo)->where('serie', $data['serie'])->exists();
        if ($duplicado) {
            return back()->withErrors(['serie' => "Ya existe la serie {$data['serie']} para el tipo {$tipo}."])->withInput();
        }

        $tenant->series()->create([
            'tipo_documento' => $tipo,
            'serie' => $data['serie'],
            'correlativo' => max(1, (int) ($data['correlativo'] ?? 1)) - 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Serie creada.');
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

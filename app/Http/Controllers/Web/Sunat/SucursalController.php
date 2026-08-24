<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use App\Services\Tenancy\EmpresaActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Sucursales / establecimientos anexos de la EMPRESA ACTIVA (para el dueño). */
class SucursalController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();
        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }
        Gate::authorize('gestionar-sucursales', $tenant);

        return Inertia::render('sunat/sucursales/index', [
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

    public function store(Request $request): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('gestionar-sucursales', $tenant);

        $data = $this->validar($request);

        if (! empty($data['is_principal'])) {
            $tenant->sucursales()->update(['is_principal' => false]);
        }

        $tenant->sucursales()->create($data);

        return back()->with('success', 'Sucursal creada.');
    }

    public function update(Request $request, Sucursal $sucursal): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('gestionar-sucursales', $tenant);
        abort_unless($sucursal->tenant_id === $tenant->id, 404);

        $data = $this->validar($request);

        if (! empty($data['is_principal'])) {
            $tenant->sucursales()->where('id', '!=', $sucursal->id)->update(['is_principal' => false]);
        }

        $sucursal->update($data);

        return back()->with('success', 'Sucursal actualizada.');
    }

    public function toggle(Sucursal $sucursal): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('gestionar-sucursales', $tenant);
        abort_unless($sucursal->tenant_id === $tenant->id, 404);

        $sucursal->update(['is_active' => ! $sucursal->is_active]);

        return back()->with('success', 'Estado de la sucursal actualizado.');
    }

    public function destroy(Sucursal $sucursal): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('gestionar-sucursales', $tenant);
        abort_unless($sucursal->tenant_id === $tenant->id, 404);

        // La sucursal principal no se elimina; primero designa otra como principal.
        if ($sucursal->is_principal) {
            return back()->with('error', 'No puedes eliminar la sucursal principal. Marca otra como principal primero.');
        }

        $sucursal->delete();

        return back()->with('success', 'Sucursal eliminada.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:100',
            'cod_local' => 'required|string|size:4',
            'direccion' => 'required|string|max:500',
            'ubigeo' => 'required|string|size:6',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'is_principal' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ], [
            'nombre.required' => 'El nombre de la sucursal es obligatorio.',
            'cod_local.required' => 'El código de local es obligatorio.',
            'cod_local.size' => 'El código de local debe tener 4 dígitos (ej: 0000).',
            'direccion.required' => 'La dirección es obligatoria.',
            'ubigeo.required' => 'El ubigeo es obligatorio.',
            'ubigeo.size' => 'El ubigeo debe tener 6 dígitos (ej: 150101).',
            'email.email' => 'El correo no tiene un formato válido.',
        ]);

        $data['is_principal'] = $request->boolean('is_principal');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}

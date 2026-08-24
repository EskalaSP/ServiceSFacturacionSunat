<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\EmpresaActiva;
use App\Support\Rbac\Ability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mi equipo": el dueño (o el super admin) gestiona los cajeros de la EMPRESA ACTIVA.
 * Acotado siempre a esa empresa; nunca puede tocar otras ni escalar a un cajero a dueño.
 */
class EquipoController extends Controller
{
    public function __construct(private readonly EmpresaActiva $empresaActiva) {}

    public function index(): Response
    {
        $tenant = $this->empresaActiva->actualOFallar();
        Gate::authorize('gestionar-equipo', $tenant);

        $cajeros = $tenant->miembros()
            ->wherePivot('role', TenantMembership::ROLE_CAJERO)
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_active' => (bool) $u->pivot->is_active,
                'abilities' => $u->pivot->abilitiesArray(),
            ])
            ->values();

        return Inertia::render('sunat/equipo/index', [
            'cajeros' => $cajeros,
            'catalogo' => [
                'tipos' => Ability::TIPOS,
                'acciones' => Ability::ACCIONES_LABELS,
                'modulos' => Ability::MODULOS,
                'asignables' => Ability::asignablesACajero(),
                'preset' => Ability::presetCajero(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->empresaActiva->actualOFallar();
        Gate::authorize('gestionar-equipo', $tenant);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'abilities' => ['array'],
            'abilities.*' => ['string', Rule::in(Ability::asignablesACajero())],
        ]);

        $user = User::firstOrNew(['email' => $data['email']]);

        // No secuestrar cuentas del panel interno (admin/soporte) como cajeros.
        if ($user->exists && $user->hasPanelAccess()) {
            return back()->with('error', 'Ese correo pertenece a un usuario del panel. Usa otro.');
        }

        $esNuevo = ! $user->exists;
        $passwordPlano = null;

        if ($esNuevo) {
            // El dueño puede fijar la contraseña; si la deja vacía, se genera una.
            $passwordPlano = filled($data['password'] ?? null) ? $data['password'] : Str::password(12);
            $user->name = $data['name'];
            $user->password = Hash::make($passwordPlano);
            $user->role = null; // usuario "cliente": sin acceso al panel admin
            $user->is_active = true;
            $user->email_verified_at = now();
            $user->save();
        }

        $tenant->miembros()->syncWithoutDetaching([
            $user->id => [
                'role' => TenantMembership::ROLE_CAJERO,
                'abilities' => json_encode(array_values($data['abilities'] ?? [])),
                'is_active' => true,
            ],
        ]);

        return back()->with([
            'success' => $esNuevo ? 'Cajero creado.' : 'Usuario agregado a tu equipo.',
            'nuevoCajero' => $esNuevo ? ['email' => $user->email, 'password' => $passwordPlano] : null,
        ]);
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $tenant = $this->empresaActiva->actualOFallar();
        Gate::authorize('gestionar-equipo', $tenant);
        $this->asegurarCajeroDe($tenant, $usuario);

        $data = $request->validate([
            'abilities' => ['array'],
            'abilities.*' => ['string', Rule::in(Ability::asignablesACajero())],
            'is_active' => ['boolean'],
        ]);

        $tenant->miembros()->updateExistingPivot($usuario->id, [
            'abilities' => json_encode(array_values($data['abilities'] ?? [])),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Permisos actualizados.');
    }

    public function toggle(User $usuario): RedirectResponse
    {
        $tenant = $this->empresaActiva->actualOFallar();
        Gate::authorize('gestionar-equipo', $tenant);
        $membresia = $this->asegurarCajeroDe($tenant, $usuario);

        $tenant->miembros()->updateExistingPivot($usuario->id, [
            'is_active' => ! $membresia->is_active,
        ]);

        return back()->with('success', 'Estado actualizado.');
    }

    public function destroy(User $usuario): RedirectResponse
    {
        $tenant = $this->empresaActiva->actualOFallar();
        Gate::authorize('gestionar-equipo', $tenant);
        $this->asegurarCajeroDe($tenant, $usuario);

        $tenant->miembros()->detach($usuario->id);

        // Si el usuario queda huérfano (sin empresas ni acceso al panel), se elimina.
        if (! $usuario->hasPanelAccess()
            && $usuario->empresas()->count() === 0
            && $usuario->tenants()->count() === 0) {
            $usuario->delete();
        }

        return back()->with('success', 'Cajero removido.');
    }

    /** Garantiza que $usuario es un cajero de $tenant (403 en caso contrario). */
    private function asegurarCajeroDe(Tenant $tenant, User $usuario): TenantMembership
    {
        $membresia = $usuario->membershipFor($tenant);

        abort_unless(
            $membresia && $membresia->role === TenantMembership::ROLE_CAJERO,
            403,
            'Ese usuario no es un cajero de esta empresa.'
        );

        return $membresia;
    }
}

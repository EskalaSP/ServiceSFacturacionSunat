<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Resuelve la "empresa activa" del usuario autenticado en el panel.
 *
 * Es el único punto de verdad: reemplaza los `auth()->user()->tenants()->first()`
 * dispersos. Guarda la selección en sesión y valida siempre la pertenencia
 * (o el bypass de super admin).
 */
class EmpresaActiva
{
    public const SESSION_KEY = 'empresa_activa_id';

    /** Empresa activa actual (de sesión si es válida; si no, la primera disponible). */
    public function actual(): ?Tenant
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        $id = session(self::SESSION_KEY);
        if ($id) {
            $tenant = Tenant::find($id);
            if ($tenant && $this->puedeAcceder($user, $tenant)) {
                return $tenant;
            }
        }

        $tenant = $this->disponibles($user)->first();
        if ($tenant) {
            session([self::SESSION_KEY => $tenant->getKey()]);
        }

        return $tenant;
    }

    /** Fija la empresa activa (valida pertenencia; aborta 403 si no corresponde). */
    public function set(Tenant $tenant): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user && $this->puedeAcceder($user, $tenant), 403, 'No tienes acceso a esta empresa.');

        session([self::SESSION_KEY => $tenant->getKey()]);
    }

    /**
     * Empresas sobre las que el usuario puede operar.
     * Super admin: todas. Resto: sus membresías activas.
     *
     * @return Collection<int,Tenant>
     */
    public function disponibles(?User $user = null): Collection
    {
        $user ??= Auth::user();
        if (! $user) {
            return collect();
        }

        if ($user->isSuperAdmin()) {
            return Tenant::query()->orderBy('razon_social')->get();
        }

        return $user->empresas()
            ->wherePivot('is_active', true)
            ->orderBy('razon_social')
            ->get();
    }

    public function puedeAcceder(User $user, Tenant $tenant): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $membresia = $user->membershipFor($tenant);

        return (bool) ($membresia && $membresia->is_active);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\EmpresaActiva;
use App\Models\TenantMembership;
use App\Support\Rbac\Ability;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * La plantilla raíz que se carga en la primera visita a la página.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determina la versión actual de los assets.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define las props que se comparten por defecto.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $empresaActiva = app(EmpresaActiva::class);
        $tenant = $user ? $empresaActiva->actual() : null;

        // Rol y permisos del usuario en la empresa activa (para gating de menú/botones).
        $rol = null;
        $abilities = [];
        if ($user && $tenant) {
            if ($user->isSuperAdmin()) {
                $rol = 'super_admin';
                $abilities = Ability::todas();
            } else {
                $membresia = $user->membershipFor($tenant);
                $rol = $membresia?->role;
                $abilities = match ($membresia?->role) {
                    TenantMembership::ROLE_COMPLETO => Ability::todas(),
                    TenantMembership::ROLE_SIMPLE   => Ability::presetSimple(),
                    TenantMembership::ROLE_CAJERO   => $membresia?->abilitiesArray() ?? [],
                    default => [],
                };
            }
        }

        $disponibles = $user
            ? $empresaActiva->disponibles()->take(100)->map(fn ($t) => [
                'id' => $t->id,
                'ruc' => $t->ruc,
                'razon_social' => $t->razon_social,
            ])->values()
            : collect();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'ruc' => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
                'environment' => $tenant->environment ?? 'beta',
                'sol_configurado' => ! empty($tenant->sol_user),
            ] : null,
            'empresa' => [
                'rol' => $rol,
                'esSuperAdmin' => $user?->isSuperAdmin() ?? false,
                'disponibles' => $disponibles,
                'can' => $abilities,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'warning' => $request->session()->get('warning'),
                'empresa_creada' => $request->session()->get('empresa_creada'),
                'nuevoCajero' => $request->session()->get('nuevoCajero'),
                'emitido' => $request->session()->get('emitido'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}

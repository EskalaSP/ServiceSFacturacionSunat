<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\EmpresaActiva;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garantiza que haya una empresa activa a la que el usuario pertenece (o es super admin),
 * y la deja disponible en la request como `empresa`. Segunda barrera contra fugas
 * cross-empresa: no hay Global Scope de tenant, así que cada request de panel se ancla aquí.
 */
class EnsureTenantMember
{
    public function __construct(private readonly EmpresaActiva $empresaActiva) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            abort(401);
        }

        $empresa = $this->empresaActiva->actual();

        if (! $empresa) {
            abort(403, 'No tienes una empresa asignada. Contacta al administrador.');
        }

        $request->attributes->set('empresa', $empresa);

        return $next($request);
    }
}

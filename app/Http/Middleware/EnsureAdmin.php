<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Acceso al panel: cualquiera de los roles (super_admin, admin, soporte,
        // lectura), siempre que la cuenta esté activa. is_admin se mantiene como
        // compatibilidad para cuentas antiguas sin rol.
        if (! $user->hasPanelAccess() && ! $user->is_admin) {
            abort(403, 'Requiere permisos de administrador.');
        }

        if (! $user->is_active) {
            abort(403, 'Tu cuenta está desactivada. Contacta a un administrador.');
        }

        return $next($request);
    }
}

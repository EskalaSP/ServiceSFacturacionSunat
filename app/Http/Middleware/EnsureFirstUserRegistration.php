<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Regula el acceso a las rutas de autenticación cuando la BD todavía no
 * tiene usuarios:
 *
 *  · Sin usuarios: /login redirige a /register para forzar la creación
 *    del primer super administrador (no tiene sentido loguearse si no
 *    hay ninguna cuenta).
 *  · Con usuarios: /register (GET y POST) responde 404 — el registro
 *    queda desactivado y solo el super admin puede crear más cuentas.
 */
class EnsureFirstUserRegistration
{
    public function handle(Request $request, Closure $next): Response
    {
        $hayUsuarios = User::query()->exists();

        if ($request->is('register') && $hayUsuarios) {
            abort(404);
        }

        if ($request->is('login') && ! $hayUsuarios) {
            return redirect('/register');
        }

        return $next($request);
    }
}

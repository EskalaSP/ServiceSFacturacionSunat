<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hace efectivos los roles del panel en las acciones que modifican datos:
 *
 *   - super_admin: todo (crear, editar, eliminar).
 *   - admin:       crear y editar; NO eliminar.
 *   - soporte:     solo acciones de comprobantes (reenviar/anular).
 *   - lectura:     nada — solo lectura.
 *
 * Las peticiones GET/HEAD/OPTIONS (lectura) siempre pasan; este middleware solo
 * evalúa métodos que escriben (POST/PUT/PATCH/DELETE).
 */
class EnsurePanelWrite
{
    /** Rutas que soporte SÍ puede ejecutar (por nombre, sufijo). */
    private const SOPORTE_ALLOW = [
        'comprobantes.reenviar',
        'comprobantes.anular',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Solo nos interesan los métodos que modifican datos.
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $user = $request->user();
        $routeName = (string) $request->route()?->getName();

        // Super admin: sin restricciones.
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Admin: puede escribir, pero no eliminar.
        if ($user->canWrite()) {
            if ($request->isMethod('DELETE')) {
                abort(403, 'Solo un super administrador puede eliminar.');
            }

            return $next($request);
        }

        // Soporte: solo reenviar/anular comprobantes.
        if ($user->canResend()) {
            foreach (self::SOPORTE_ALLOW as $sufijo) {
                if (str_ends_with($routeName, $sufijo)) {
                    return $next($request);
                }
            }
        }

        // Lectura (o soporte fuera de su allowlist): bloqueado.
        abort(403, 'Tu rol es de solo lectura para esta acción.');
    }
}

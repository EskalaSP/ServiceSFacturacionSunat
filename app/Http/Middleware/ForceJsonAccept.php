<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fuerza el header Accept: application/json en todas las rutas /api/*.
 *
 * Sin esto, si un cliente no manda "Accept: application/json" y hay un error
 * de validación (422), 404 o redirect, Laravel devuelve HTML en vez de JSON
 * con los detalles del error.
 */
class ForceJsonAccept
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}

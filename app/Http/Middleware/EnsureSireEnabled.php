<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea endpoints SIRE si el tenant no ha activado SIRE.
 *
 * La activación se hace con POST /v1/sire/activar, que verifica las credenciales
 * contra SUNAT y marca sire_enabled = true.
 *
 * Este middleware es complementario a `plan:feature:sire` (gating por plan).
 * Si se usan ambos, el orden recomendado en rutas es: `plan:feature:sire` → `sire.enabled`.
 */
class EnsureSireEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->get('tenant');

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant no resuelto.',
            ], 401);
        }

        if (! $tenant->sire_enabled) {
            return response()->json([
                'success'    => false,
                'message'    => 'SIRE no está activado para este tenant. Llama primero a POST /v1/sire/activar con credenciales válidas.',
                'error_code' => 'sire_not_enabled',
            ], 403);
        }

        return $next($request);
    }
}

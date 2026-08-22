<?php

namespace App\Http\Middleware;

use App\Services\License\LicenseClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate de licencia. Antes de emitir a SUNAT en PRODUCCIÓN, esta instalación
 * debe tener una licencia válida contra el servidor del proveedor.
 *
 * El entorno 'beta' (pruebas) nunca se bloquea, para poder probar sin licencia.
 * Toda la lógica de validación vive en LicenseClient; aquí solo se traduce la
 * decisión a una respuesta HTTP.
 */
class CheckLicense
{
    public function __construct(
        private LicenseClient $license,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Beta: siempre permitido (pruebas).
        $tenant = $request->get('tenant');
        if ($tenant && $tenant->environment === 'beta') {
            return $next($request);
        }

        $check = $this->license->check();

        if ($check->allowed) {
            // Si opera en gracia offline, lo avisamos por cabecera sin bloquear.
            if ($check->offlineGrace) {
                $response = $next($request);
                $response->headers->set('X-License-Warning', $check->message);

                return $response;
            }

            return $next($request);
        }

        return response()->json([
            'estado' => 'error',
            'mensaje' => $check->message,
            'codigo_error' => $check->code,
        ], 403);
    }
}

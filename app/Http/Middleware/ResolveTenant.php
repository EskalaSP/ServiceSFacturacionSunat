<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Api-Key') ?: $request->query('api_key');
        $apiSecret = $request->header('X-Api-Secret') ?: $request->query('api_secret');

        if (! $apiKey || ! $apiSecret) {
            return response()->json([
                'estado' => 'error',
                'mensaje' => 'Las cabeceras X-Api-Key y X-Api-Secret son requeridas.',
            ], 401);
        }

        $tenant = Cache::remember("tenant:key:{$apiKey}", 600, function () use ($apiKey) {
            return Tenant::where('api_key', $apiKey)->first();
        });

        if (! $tenant || ! hash_equals($tenant->api_secret, $apiSecret)) {
            return response()->json([
                'estado' => 'error',
                'mensaje' => 'Credenciales de API inválidas.',
            ], 401);
        }

        if (! $tenant->is_active) {
            return response()->json([
                'estado' => 'error',
                'mensaje' => 'Empresa desactivada. Contacte al administrador.',
            ], 403);
        }

        $request->merge(['tenant' => $tenant]);

        return $next($request);
    }
}

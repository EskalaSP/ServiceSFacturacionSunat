<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDocumentLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->get('tenant');

        if ($tenant && $tenant->hasReachedDocumentLimit()) {
            return response()->json([
                'success' => false,
                'message' => "Límite de documentos alcanzado ({$tenant->max_documents_month}/mes). Actualice su plan.",
                'plan_actual' => $tenant->plan,
                'documentos_este_mes' => $tenant->documentsThisMonth(),
                'limite' => $tenant->max_documents_month,
            ], 429);
        }

        return $next($request);
    }
}

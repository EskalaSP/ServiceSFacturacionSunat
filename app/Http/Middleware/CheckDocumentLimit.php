<?php

namespace App\Http\Middleware;

use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckDocumentLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->get('tenant');

        if ($tenant) {
            $cacheKey = "tenant:{$tenant->id}:doc_count:".now()->format('Y-m');
            $count = Cache::remember($cacheKey, 300, function () use ($tenant) {
                $month = now()->month;
                $year = now()->year;

                return Invoice::where('tenant_id', $tenant->id)->whereMonth('created_at', $month)->whereYear('created_at', $year)->count()
                    + Boleta::where('tenant_id', $tenant->id)->whereMonth('created_at', $month)->whereYear('created_at', $year)->count()
                    + CreditNote::where('tenant_id', $tenant->id)->whereMonth('created_at', $month)->whereYear('created_at', $year)->count()
                    + DebitNote::where('tenant_id', $tenant->id)->whereMonth('created_at', $month)->whereYear('created_at', $year)->count();
            });

            if ($count >= $tenant->max_documents_month) {
                return response()->json([
                    'success' => false,
                    'message' => "Límite de documentos alcanzado ({$tenant->max_documents_month}/mes). Actualice su plan.",
                    'plan_actual' => $tenant->plan,
                    'documentos_este_mes' => $count,
                    'limite' => $tenant->max_documents_month,
                ], 429);
            }
        }

        return $next($request);
    }
}

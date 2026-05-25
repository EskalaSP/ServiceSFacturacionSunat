<?php

namespace App\Http\Middleware;

use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\DispatchGuide;
use App\Models\InternalDocument;
use App\Models\Invoice;
use App\Services\Plan\PlanService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckDocumentLimit
{
    public function __construct(
        private PlanService $planService,
    ) {}

    public function handle(Request $request, Closure $next, string $category = 'sunat'): Response
    {
        $tenant = $request->get('tenant');

        if (! $tenant) {
            return $next($request);
        }

        // Modo beta: omitir límites de documentos para pruebas
        if ($tenant->environment === 'beta') {
            return $next($request);
        }

        $plan = $this->planService->getActivePlan($tenant);

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        if ($category === 'sunat') {
            $maxSunat = $plan->getLimit('documents_month', 30);

            // -1 = ilimitado
            if ($maxSunat === -1) {
                return $next($request);
            }

            $sunatKey = "tenant:{$tenant->id}:sunat_count:" . now()->format('Y-m');
            $sunatCount = Cache::remember($sunatKey, 300, function () use ($tenant, $monthStart, $monthEnd) {
                $bind = [$tenant->id, $monthStart . ' 00:00:00', $monthEnd . ' 23:59:59'];
                $bindings = array_merge($bind, $bind, $bind, $bind, $bind);

                return (int) \DB::selectOne("
                    SELECT COALESCE(SUM(cnt), 0) AS total FROM (
                        SELECT COUNT(*) AS cnt FROM invoices WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
                        UNION ALL
                        SELECT COUNT(*) FROM boletas WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
                        UNION ALL
                        SELECT COUNT(*) FROM credit_notes WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
                        UNION ALL
                        SELECT COUNT(*) FROM debit_notes WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
                        UNION ALL
                        SELECT COUNT(*) FROM dispatch_guides WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
                    ) AS counts
                ", $bindings)->total;
            });

            if ($sunatCount >= $maxSunat) {
                return $this->limitResponse($maxSunat, $sunatCount, $plan, 'documentos SUNAT', 'documents_month');
            }
        }

        if ($category === 'internal') {
            $maxInternal = $plan->getLimit('internal_documents_month', 20);

            if ($maxInternal === -1) {
                return $next($request);
            }

            $internalKey = "tenant:{$tenant->id}:internal_count:" . now()->format('Y-m');
            $internalCount = Cache::remember($internalKey, 300, function () use ($tenant, $monthStart, $monthEnd) {
                return InternalDocument::where('tenant_id', $tenant->id)
                    ->whereBetween('created_at', [$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59'])
                    ->count();
            });

            if ($internalCount >= $maxInternal) {
                return $this->limitResponse($maxInternal, $internalCount, $plan, 'documentos internos', 'internal_documents_month');
            }
        }

        return $next($request);
    }

    private function limitResponse(int $limit, int $count, $plan, string $label, string $limitKey): Response
    {
        $nextPlan = null;
        $message = "Has alcanzado el limite de {$label} ({$count}/{$limit} por mes).";

        if ($plan->slug === 'free') {
            $message .= ' Actualiza a Pro por S/49/mes para más documentos.';
            $nextPlan = ['slug' => 'pro', 'price' => 49, 'currency' => 'PEN', 'trial_days' => 14];
        } elseif ($plan->slug === 'pro') {
            $message .= ' Actualiza a Business por S/99/mes para documentos ilimitados.';
            $nextPlan = ['slug' => 'business', 'price' => 99, 'currency' => 'PEN', 'trial_days' => 14];
        }

        return response()->json([
            'estado' => 'error',
            'mensaje' => $message,
            'codigo_error' => 'limite_alcanzado',
            'plan_actual' => $plan->slug,
            'recurso' => $limitKey,
            'actual' => $count,
            'limite' => $limit,
            'mejora_plan' => $nextPlan,
        ], 429);
    }
}

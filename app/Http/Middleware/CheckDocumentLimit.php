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

        $plan = $this->planService->getActivePlan($tenant);

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        if ($category === 'sunat') {
            $maxSunat = $plan->getLimit('documents_month', 30);

            // -1 = unlimited
            if ($maxSunat === -1) {
                return $next($request);
            }

            $sunatKey = "tenant:{$tenant->id}:sunat_count:" . now()->format('Y-m');
            $sunatCount = Cache::remember($sunatKey, 300, function () use ($tenant, $monthStart, $monthEnd) {
                $where = fn ($q) => $q->where('tenant_id', $tenant->id)
                    ->whereBetween('created_at', [$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);

                return Invoice::where($where)->count()
                    + Boleta::where($where)->count()
                    + CreditNote::where($where)->count()
                    + DebitNote::where($where)->count()
                    + DispatchGuide::where($where)->count();
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
            $message .= ' Actualiza a Pro por S/29/mes para más documentos.';
            $nextPlan = ['slug' => 'pro', 'price' => 29, 'currency' => 'PEN', 'trial_days' => 14];
        } elseif ($plan->slug === 'pro') {
            $message .= ' Actualiza a Business por S/79/mes para documentos ilimitados.';
            $nextPlan = ['slug' => 'business', 'price' => 79, 'currency' => 'PEN', 'trial_days' => 14];
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'usage_limit_reached',
            'plan_actual' => $plan->slug,
            'limit_key' => $limitKey,
            'current' => $count,
            'limit' => $limit,
            'upgrade' => $nextPlan,
        ], 429);
    }
}

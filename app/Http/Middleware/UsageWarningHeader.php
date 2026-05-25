<?php

namespace App\Http\Middleware;

use App\Services\Plan\PlanService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UsageWarningHeader
{
    public function __construct(
        private PlanService $planService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $tenant = $request->get('tenant');

        if (! $tenant) {
            return $response;
        }

        $plan = $this->planService->getActivePlan($tenant);

        // Uso de documentos
        $docLimit = $plan->getLimit('documents_month', 30);
        if ($docLimit !== -1) {
            $current = $tenant->documents_this_month ?? 0;
            $response->headers->set('X-Usage-Documents', "{$current}/{$docLimit}");
            $pct = $docLimit > 0 ? ($current / $docLimit) * 100 : 0;
            if ($pct >= 80) {
                $response->headers->set('X-Usage-Warning', 'approaching_limit');
                $response->headers->set('X-Usage-Remaining', (string) max(0, $docLimit - $current));
            }
        }

        // Uso de mensajes de IA
        $aiLimit = $plan->getLimit('ai_messages_month', 10);
        if ($aiLimit !== -1) {
            $aiCurrent = $tenant->ai_messages_this_month ?? 0;
            $response->headers->set('X-Usage-AI', "{$aiCurrent}/{$aiLimit}");
        }

        $response->headers->set('X-Plan', $plan->slug);

        return $response;
    }
}

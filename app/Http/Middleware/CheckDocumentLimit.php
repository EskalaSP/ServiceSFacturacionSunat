<?php

namespace App\Http\Middleware;

use App\Services\Plan\EmissionDecision;
use App\Services\Plan\EmissionPolicyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate de emisión. Toda la lógica de "¿puede emitir?" vive en
 * EmissionPolicyService (prioridad global → beta → empresa → plan);
 * este middleware sólo traduce la decisión a una respuesta HTTP.
 */
class CheckDocumentLimit
{
    public function __construct(
        private EmissionPolicyService $policy,
    ) {}

    public function handle(Request $request, Closure $next, string $category = 'sunat'): Response
    {
        $tenant = $request->get('tenant');

        if (! $tenant) {
            return $next($request);
        }

        $decision = $this->policy->evaluate($tenant, $category);

        if ($decision->allowed) {
            return $next($request);
        }

        return $this->denegar($decision);
    }

    private function denegar(EmissionDecision $decision): Response
    {
        $label = $decision->category === 'internal' ? 'documentos internos' : 'documentos SUNAT';
        $limitKey = $decision->category === 'internal' ? 'internal_documents_month' : 'documents_month';
        $plan = $decision->plan;

        // Suscripción vencida → 403, sin upsell de cupo.
        if ($decision->code === EmissionDecision::CODE_SUBSCRIPTION_EXPIRED) {
            return response()->json([
                'estado' => 'error',
                'mensaje' => 'Tu suscripción venció. Renueva el plan para seguir emitiendo comprobantes.',
                'codigo_error' => EmissionDecision::CODE_SUBSCRIPTION_EXPIRED,
                'plan_actual' => $plan?->slug,
                'recurso' => $limitKey,
            ], 403);
        }

        // Cupo mensual alcanzado → 429, con sugerencia de mejora.
        $count = (int) $decision->used;
        $limit = (int) $decision->limit;
        $message = "Has alcanzado el limite de {$label} ({$count}/{$limit} por mes).";
        $nextPlan = null;

        if ($plan?->slug === 'free') {
            $message .= ' Actualiza a Pro por S/49/mes para más documentos.';
            $nextPlan = ['slug' => 'pro', 'price' => 49, 'currency' => 'PEN', 'trial_days' => 14];
        } elseif ($plan?->slug === 'pro') {
            $message .= ' Actualiza a Business por S/99/mes para documentos ilimitados.';
            $nextPlan = ['slug' => 'business', 'price' => 99, 'currency' => 'PEN', 'trial_days' => 14];
        }

        return response()->json([
            'estado' => 'error',
            'mensaje' => $message,
            'codigo_error' => EmissionDecision::CODE_LIMIT_REACHED,
            'plan_actual' => $plan?->slug,
            'recurso' => $limitKey,
            'actual' => $count,
            'limite' => $limit,
            'mejora_plan' => $nextPlan,
        ], 429);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Subscription\CancelSubscriptionAction;
use App\Actions\Subscription\ChangePlanAction;
use App\Actions\Subscription\CreateSubscriptionAction;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Plan;
use App\Services\Plan\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    use ApiResponse;

    public function plans(): JsonResponse
    {
        $plans = Plan::active()->get()->map(fn (Plan $plan) => [
            'slug' => $plan->slug,
            'nombre' => $plan->name,
            'precio_mensual' => $plan->price_monthly,
            'precio_anual' => $plan->price_yearly,
            'limites' => $plan->limits,
            'caracteristicas' => $plan->features,
        ]);

        return $this->success($plans);
    }

    public function show(Request $request, PlanService $planService): JsonResponse
    {
        $tenant = $request->get('tenant');
        $subscription = $tenant->activeSubscription?->load('plan');

        return $this->success([
            'suscripcion' => $subscription ? [
                'id' => $subscription->id,
                'plan' => [
                    'slug' => $subscription->plan->slug,
                    'nombre' => $subscription->plan->name,
                ],
                'estado' => $subscription->status,
                'ciclo_facturacion' => $subscription->billing_cycle,
                'fin_prueba' => $subscription->trial_ends_at?->toIso8601String(),
                'fin_periodo_actual' => $subscription->current_period_end?->toIso8601String(),
                'cancelado_en' => $subscription->cancelled_at?->toIso8601String(),
                'ultimos_cuatro_tarjeta' => $subscription->card_last_four,
                'marca_tarjeta' => $subscription->card_brand,
            ] : null,
            'uso' => $planService->getUsageReport($tenant),
        ]);
    }

    public function store(Request $request, CreateSubscriptionAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');

        $request->validate([
            'plan_slug' => 'required|string|exists:plans,slug',
            'ciclo_facturacion' => 'sometimes|string|in:monthly,yearly',
            'token' => 'sometimes|string',
            'prueba' => 'sometimes|boolean',
        ]);

        try {
            $data = [
                'plan_slug' => $request->input('plan_slug'),
                'billing_cycle' => $request->input('ciclo_facturacion'),
                'token' => $request->input('token'),
            ];

            if ($request->boolean('prueba')) {
                $data['trial_days'] = 14;
            }

            $subscription = $action->execute($tenant, array_filter($data));

            return $this->created([
                'id_suscripcion' => $subscription->id,
                'plan' => $subscription->plan->slug,
                'estado' => $subscription->status,
                'fin_periodo_actual' => $subscription->current_period_end?->toIso8601String(),
            ], 'Suscripción creada exitosamente.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function changePlan(Request $request, ChangePlanAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');

        $request->validate([
            'plan_slug' => 'required|string|exists:plans,slug',
            'ciclo_facturacion' => 'sometimes|string|in:monthly,yearly',
            'token' => 'sometimes|string',
        ]);

        try {
            $subscription = $action->execute($tenant, [
                'plan_slug' => $request->input('plan_slug'),
                'billing_cycle' => $request->input('ciclo_facturacion'),
                'token' => $request->input('token'),
            ]);

            return $this->success([
                'id_suscripcion' => $subscription->id,
                'plan' => $subscription->plan->slug,
                'estado' => $subscription->status,
            ], 'Plan actualizado exitosamente.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function cancel(Request $request, CancelSubscriptionAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');

        try {
            $subscription = $action->execute($tenant);

            return $this->success([
                'estado' => $subscription->status,
                'cancelado_en' => $subscription->cancelled_at?->toIso8601String(),
            ], 'Suscripción cancelada. Acceso disponible hasta el final del periodo.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->error('No hay suscripción activa para cancelar.', 404);
        }
    }

    public function payments(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $payments = $tenant->subscriptions()
            ->with('payments')
            ->get()
            ->pluck('payments')
            ->flatten()
            ->sortByDesc('created_at')
            ->values()
            ->map(fn ($payment) => [
                'id' => $payment->id,
                'monto' => $payment->amount,
                'moneda' => $payment->currency,
                'estado' => $payment->status,
                'pagado_en' => $payment->paid_at?->toIso8601String(),
                'inicio_periodo' => $payment->period_start?->toDateString(),
                'fin_periodo' => $payment->period_end?->toDateString(),
            ]);

        return $this->success($payments);
    }

    public function usage(Request $request, PlanService $planService): JsonResponse
    {
        $tenant = $request->get('tenant');

        return $this->success($planService->getUsageReport($tenant));
    }
}

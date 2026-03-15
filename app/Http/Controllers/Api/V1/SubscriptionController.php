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

    /**
     * List available plans (public).
     */
    public function plans(): JsonResponse
    {
        $plans = Plan::active()->get()->map(fn (Plan $plan) => [
            'slug' => $plan->slug,
            'name' => $plan->name,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'limits' => $plan->limits,
            'features' => $plan->features,
        ]);

        return $this->success($plans);
    }

    /**
     * Get current subscription and usage for tenant.
     */
    public function show(Request $request, PlanService $planService): JsonResponse
    {
        $tenant = $request->get('tenant');
        $subscription = $tenant->activeSubscription?->load('plan');

        return $this->success([
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'plan' => [
                    'slug' => $subscription->plan->slug,
                    'name' => $subscription->plan->name,
                ],
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
                'card_last_four' => $subscription->card_last_four,
                'card_brand' => $subscription->card_brand,
            ] : null,
            'usage' => $planService->getUsageReport($tenant),
        ]);
    }

    /**
     * Create or upgrade subscription.
     */
    public function store(Request $request, CreateSubscriptionAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');

        $request->validate([
            'plan_slug' => 'required|string|exists:plans,slug',
            'billing_cycle' => 'sometimes|string|in:monthly,yearly',
            'token' => 'sometimes|string', // Culqi token
        ]);

        try {
            $subscription = $action->execute($tenant, $request->only([
                'plan_slug', 'billing_cycle', 'token',
            ]));

            return $this->created([
                'subscription_id' => $subscription->id,
                'plan' => $subscription->plan->slug,
                'status' => $subscription->status,
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            ], 'Suscripción creada exitosamente.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Change plan (upgrade/downgrade).
     */
    public function changePlan(Request $request, ChangePlanAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');

        $request->validate([
            'plan_slug' => 'required|string|exists:plans,slug',
            'billing_cycle' => 'sometimes|string|in:monthly,yearly',
            'token' => 'sometimes|string',
        ]);

        try {
            $subscription = $action->execute($tenant, $request->only([
                'plan_slug', 'billing_cycle', 'token',
            ]));

            return $this->success([
                'subscription_id' => $subscription->id,
                'plan' => $subscription->plan->slug,
                'status' => $subscription->status,
            ], 'Plan actualizado exitosamente.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Cancel subscription.
     */
    public function cancel(Request $request, CancelSubscriptionAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');

        try {
            $subscription = $action->execute($tenant);

            return $this->success([
                'status' => $subscription->status,
                'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
            ], 'Suscripción cancelada. Acceso disponible hasta el final del periodo.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->error('No hay suscripción activa para cancelar.', 404);
        }
    }

    /**
     * Payment history.
     */
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
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'period_start' => $payment->period_start?->toDateString(),
                'period_end' => $payment->period_end?->toDateString(),
            ]);

        return $this->success($payments);
    }

    /**
     * Usage report.
     */
    public function usage(Request $request, PlanService $planService): JsonResponse
    {
        $tenant = $request->get('tenant');

        return $this->success($planService->getUsageReport($tenant));
    }
}

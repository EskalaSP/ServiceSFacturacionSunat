<?php

namespace App\Actions\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\DB;

class ChangePlanAction
{
    public function __construct(
        private PlanService $planService,
        private CreateSubscriptionAction $createAction,
    ) {}

    /**
     * Cambia a un plan distinto.
     * Las mejoras de plan tienen efecto inmediato; las reducciones al final del período.
     */
    public function execute(Tenant $tenant, array $data): Subscription
    {
        $newPlan = Plan::where('slug', $data['plan_slug'])->where('is_active', true)->firstOrFail();
        $currentSubscription = $tenant->subscriptions()->whereIn('status', ['active', 'trialing'])->latest()->first();
        $currentPlan = $currentSubscription?->plan;

        // Si no hay suscripción activa o es una mejora de plan, crear nueva suscripción de inmediato
        if (! $currentSubscription || ($currentPlan && $newPlan->sort_order > $currentPlan->sort_order)) {
            return $this->createAction->execute($tenant, $data);
        }

        // Reducción de plan: programar cambio al final del período actual (mantener plan actual activo)
        return DB::transaction(function () use ($tenant, $currentSubscription, $newPlan) {
            // Crear suscripción pendiente que inicia al final del período actual
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $newPlan->id,
                'status' => 'pending',
                'billing_cycle' => $currentSubscription->billing_cycle,
                'current_period_start' => $currentSubscription->current_period_end,
                'current_period_end' => $currentSubscription->billing_cycle === 'yearly'
                    ? $currentSubscription->current_period_end->addYear()
                    : $currentSubscription->current_period_end->addMonth(),
            ]);

            // Mantener el plan actual activo hasta que termine el período — ProcessRecurringPayments
            // activará la suscripción pendiente y actualizará tenant.plan

            $this->planService->clearCache($tenant);

            return $subscription->load('plan');
        });
    }
}

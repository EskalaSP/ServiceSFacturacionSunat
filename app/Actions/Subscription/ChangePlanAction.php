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
     * Change to a different plan.
     * Upgrades take effect immediately; downgrades at end of period.
     */
    public function execute(Tenant $tenant, array $data): Subscription
    {
        $newPlan = Plan::where('slug', $data['plan_slug'])->where('is_active', true)->firstOrFail();
        $currentSubscription = $tenant->subscriptions()->whereIn('status', ['active', 'trialing'])->latest()->first();
        $currentPlan = $currentSubscription?->plan;

        // If no active subscription or upgrading, create new subscription immediately
        if (! $currentSubscription || ($currentPlan && $newPlan->sort_order > $currentPlan->sort_order)) {
            return $this->createAction->execute($tenant, $data);
        }

        // Downgrade: schedule change at end of current period (keep current plan active)
        return DB::transaction(function () use ($tenant, $currentSubscription, $newPlan) {
            // Create pending subscription starting at end of current period
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

            // Keep current plan active until period ends — ProcessRecurringPayments
            // will activate the pending subscription and update tenant.plan

            $this->planService->clearCache($tenant);

            return $subscription->load('plan');
        });
    }
}

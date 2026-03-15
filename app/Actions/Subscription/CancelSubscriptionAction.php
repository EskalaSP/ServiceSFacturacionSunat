<?php

namespace App\Actions\Subscription;

use App\Events\SubscriptionCancelled;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Plan\PlanService;

class CancelSubscriptionAction
{
    public function __construct(
        private PlanService $planService,
    ) {}

    /**
     * Cancel a subscription at end of current period.
     */
    public function execute(Tenant $tenant): Subscription
    {
        $subscription = $tenant->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->latest()
            ->firstOrFail();

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        // If trial or period already ended, downgrade now. Otherwise keep access until period end.
        $periodEnd = $subscription->current_period_end ?? now();
        if (now()->gte($periodEnd) || $subscription->isTrialing()) {
            $freePlan = Plan::where('slug', 'free')->first();
            $tenant->update([
                'plan' => 'free',
                'max_documents_month' => $freePlan?->getLimit('documents_month', 30) ?? 30,
            ]);
        }
        // Otherwise: ProcessRecurringPayments job will downgrade when period expires

        $this->planService->clearCache($tenant);

        event(new SubscriptionCancelled($subscription));

        return $subscription->fresh('plan');
    }
}

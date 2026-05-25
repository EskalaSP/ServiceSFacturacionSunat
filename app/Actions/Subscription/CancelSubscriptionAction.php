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
     * Cancela una suscripción al final del período actual.
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

        // Si la prueba o el período ya terminó, degradar ahora. De lo contrario, mantener acceso hasta el fin del período.
        $periodEnd = $subscription->current_period_end ?? now();
        if (now()->gte($periodEnd) || $subscription->isTrialing()) {
            $freePlan = Plan::where('slug', 'free')->first();
            $tenant->update([
                'plan' => 'free',
                'max_documents_month' => $freePlan?->getLimit('documents_month', 30) ?? 30,
            ]);
        }
        // De lo contrario: el job ProcessRecurringPayments degradará cuando expire el período

        $this->planService->clearCache($tenant);

        event(new SubscriptionCancelled($subscription));

        return $subscription->fresh('plan');
    }
}

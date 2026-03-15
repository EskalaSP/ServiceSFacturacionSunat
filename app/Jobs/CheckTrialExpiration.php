<?php

namespace App\Jobs;

use App\Events\TrialExpiring;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Plan\PlanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckTrialExpiration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PlanService $planService): void
    {
        // Notify trials expiring in 3 days
        Subscription::with(['tenant', 'plan'])
            ->trialExpiring(3)
            ->get()
            ->each(function (Subscription $subscription) {
                event(new TrialExpiring($subscription));
            });

        // Expire overdue trials
        $expired = Subscription::with('tenant')
            ->where('status', 'trialing')
            ->where('trial_ends_at', '<', now())
            ->get();

        Log::info("Expiring {$expired->count()} trials");

        $freePlan = Plan::where('slug', 'free')->first();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);

            $subscription->tenant->update([
                'plan' => 'free',
                'max_documents_month' => $freePlan?->getLimit('documents_month', 30) ?? 30,
            ]);

            $planService->clearCache($subscription->tenant);
        }
    }
}

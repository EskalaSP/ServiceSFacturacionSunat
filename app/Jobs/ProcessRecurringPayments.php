<?php

namespace App\Jobs;

use App\Actions\Subscription\ProcessRecurringPaymentAction;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRecurringPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ProcessRecurringPaymentAction $action): void
    {
        $subscriptions = Subscription::with(['tenant', 'plan'])
            ->dueForRenewal()
            ->get();

        Log::info("Processing {$subscriptions->count()} recurring payments");

        foreach ($subscriptions as $subscription) {
            try {
                $action->execute($subscription);
            } catch (\Exception $e) {
                Log::error("Failed to process recurring payment for subscription {$subscription->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

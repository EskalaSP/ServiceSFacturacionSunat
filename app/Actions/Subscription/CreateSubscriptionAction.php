<?php

namespace App\Actions\Subscription;

use App\Events\SubscriptionCreated;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateSubscriptionAction
{
    public function __construct(
        private PlanService $planService,
    ) {}

    /**
     * Create a new subscription for a tenant.
     *
     * @param array{
     *   plan_slug: string,
     *   billing_cycle?: string,
     *   token?: string,
     *   trial_days?: int,
     * } $data
     */
    public function execute(Tenant $tenant, array $data): Subscription
    {
        $plan = Plan::where('slug', $data['plan_slug'])->where('is_active', true)->firstOrFail();
        $billingCycle = $data['billing_cycle'] ?? 'monthly';
        $trialDays = $data['trial_days'] ?? 0;
        $token = $data['token'] ?? null;

        return DB::transaction(function () use ($tenant, $plan, $billingCycle, $trialDays, $token) {
            // Cancel any existing active subscription
            $tenant->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            $subscription = new Subscription();
            $subscription->tenant_id = $tenant->id;
            $subscription->plan_id = $plan->id;
            $subscription->billing_cycle = $billingCycle;

            if ($trialDays > 0 || ($plan->slug !== 'free' && ! $token)) {
                // Start trial
                $days = $trialDays > 0 ? $trialDays : 14;
                $subscription->status = 'trialing';
                $subscription->trial_ends_at = now()->addDays($days);
                $subscription->current_period_start = now();
                $subscription->current_period_end = now()->addDays($days);
            } elseif ($plan->price_monthly > 0 && $token) {
                // Paid subscription with payment
                $chargeResult = $this->chargeWithCulqi($tenant, $plan, $billingCycle, $token);

                $subscription->status = 'active';
                $subscription->current_period_start = now();
                $subscription->current_period_end = $billingCycle === 'yearly'
                    ? now()->addYear()
                    : now()->addMonth();
                $subscription->gateway_customer_id = $chargeResult['customer_id'] ?? null;
                $subscription->gateway_card_id = $chargeResult['card_id'] ?? null;
                $subscription->card_last_four = $chargeResult['card_last_four'] ?? null;
                $subscription->card_brand = $chargeResult['card_brand'] ?? null;
            } else {
                // Free plan
                $subscription->status = 'active';
                $subscription->current_period_start = now();
                $subscription->current_period_end = now()->addYear();
            }

            $subscription->save();

            // Record payment if charged
            if (isset($chargeResult) && ($chargeResult['charge_id'] ?? null)) {
                SubscriptionPayment::create([
                    'subscription_id' => $subscription->id,
                    'amount' => $chargeResult['amount'],
                    'currency' => 'PEN',
                    'status' => 'completed',
                    'gateway_charge_id' => $chargeResult['charge_id'],
                    'gateway_response' => $chargeResult['response'] ?? [],
                    'paid_at' => now(),
                    'period_start' => $subscription->current_period_start->toDateString(),
                    'period_end' => $subscription->current_period_end->toDateString(),
                ]);
            }

            // Update tenant plan
            $tenant->update([
                'plan' => $plan->slug,
                'max_documents_month' => $plan->getLimit('documents_month', 30),
            ]);

            $this->planService->clearCache($tenant);

            event(new SubscriptionCreated($subscription));

            return $subscription->load('plan');
        });
    }

    private function chargeWithCulqi(Tenant $tenant, Plan $plan, string $billingCycle, string $token): array
    {
        $amount = $billingCycle === 'yearly'
            ? (int) ($plan->price_yearly * 100)
            : (int) ($plan->price_monthly * 100);

        try {
            $culqi = new \Culqi\Culqi(['api_key' => config('services.culqi.secret_key')]);

            $charge = $culqi->Charges->create([
                'amount' => $amount,
                'currency_code' => 'PEN',
                'email' => $tenant->user?->email ?? "{$tenant->ruc}@facturacion.pe",
                'source_id' => $token,
                'description' => "Suscripción {$plan->name} - {$tenant->razon_social}",
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                    'plan' => $plan->slug,
                    'billing_cycle' => $billingCycle,
                ],
            ]);

            return [
                'charge_id' => $charge->id ?? null,
                'customer_id' => $charge->source->iin->issuer->name ?? null,
                'card_id' => null,
                'card_last_four' => $charge->source->last_four ?? null,
                'card_brand' => $charge->source->iin->card_brand ?? null,
                'amount' => $amount / 100,
                'response' => (array) $charge,
            ];
        } catch (\Exception $e) {
            Log::error('Culqi charge failed', [
                'tenant_id' => $tenant->id,
                'plan' => $plan->slug,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Error al procesar el pago: ' . $e->getMessage());
        }
    }
}

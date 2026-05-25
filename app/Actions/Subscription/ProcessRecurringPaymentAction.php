<?php

namespace App\Actions\Subscription;

use App\Events\PaymentFailed;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\Log;

class ProcessRecurringPaymentAction
{
    public function __construct(
        private PlanService $planService,
    ) {}

    /**
     * Procesa un pago recurrente para una suscripción próxima a renovarse.
     */
    public function execute(Subscription $subscription): bool
    {
        $tenant = $subscription->tenant;
        $plan = $subscription->plan;

        $amount = $subscription->billing_cycle === 'yearly'
            ? (int) ($plan->price_yearly * 100)
            : (int) ($plan->price_monthly * 100);

        // Los planes gratuitos no requieren pago
        if ($amount <= 0) {
            $this->renewPeriod($subscription);
            return true;
        }

        // Si no hay tarjeta registrada, marcar como past_due
        if (! $subscription->gateway_card_id && ! $subscription->gateway_customer_id) {
            $subscription->update(['status' => 'past_due']);
            event(new PaymentFailed($subscription, 'No hay método de pago registrado'));
            return false;
        }

        try {
            $culqi = new \Culqi\Culqi(['api_key' => config('services.culqi.secret_key')]);

            $charge = $culqi->Charges->create([
                'amount' => $amount,
                'currency_code' => 'PEN',
                'email' => $tenant->user?->email ?? "{$tenant->ruc}@facturacion.pe",
                'source_id' => $subscription->gateway_card_id,
                'description' => "Renovación {$plan->name} - {$tenant->razon_social}",
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                    'subscription_id' => (string) $subscription->id,
                    'type' => 'recurring',
                ],
            ]);

            // Registrar pago exitoso
            SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'amount' => $amount / 100,
                'currency' => 'PEN',
                'status' => 'completed',
                'gateway_charge_id' => $charge->id ?? null,
                'gateway_response' => (array) $charge,
                'paid_at' => now(),
                'period_start' => $subscription->current_period_end->toDateString(),
                'period_end' => $subscription->billing_cycle === 'yearly'
                    ? $subscription->current_period_end->addYear()->toDateString()
                    : $subscription->current_period_end->addMonth()->toDateString(),
            ]);

            $this->renewPeriod($subscription);

            return true;
        } catch (\Exception $e) {
            Log::error('Fallo en el pago recurrente', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            // Registrar pago fallido
            SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'amount' => $amount / 100,
                'currency' => 'PEN',
                'status' => 'failed',
                'gateway_response' => ['error' => $e->getMessage()],
                'period_start' => $subscription->current_period_end->toDateString(),
                'period_end' => $subscription->billing_cycle === 'yearly'
                    ? $subscription->current_period_end->addYear()->toDateString()
                    : $subscription->current_period_end->addMonth()->toDateString(),
            ]);

            $subscription->update(['status' => 'past_due']);
            event(new PaymentFailed($subscription, $e->getMessage()));

            return false;
        }
    }

    private function renewPeriod(Subscription $subscription): void
    {
        $newStart = $subscription->current_period_end;
        $newEnd = $subscription->billing_cycle === 'yearly'
            ? $newStart->addYear()
            : $newStart->addMonth();

        $subscription->update([
            'status' => 'active',
            'current_period_start' => $newStart,
            'current_period_end' => $newEnd,
        ]);

        $this->planService->clearCache($subscription->tenant);
    }
}

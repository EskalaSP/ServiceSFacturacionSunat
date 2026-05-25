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
     * Crea una nueva suscripción para un tenant.
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
            // Cancelar cualquier suscripción activa existente
            $tenant->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            $subscription = new Subscription();
            $subscription->tenant_id = $tenant->id;
            $subscription->plan_id = $plan->id;
            $subscription->billing_cycle = $billingCycle;

            if ($trialDays > 0 || ($plan->slug !== 'free' && ! $token)) {
                // Iniciar período de prueba
                $days = $trialDays > 0 ? $trialDays : 14;
                $subscription->status = 'trialing';
                $subscription->trial_ends_at = now()->addDays($days);
                $subscription->current_period_start = now();
                $subscription->current_period_end = now()->addDays($days);
            } elseif ($plan->price_monthly > 0 && $token) {
                // Suscripción de pago con cobro
                $chargeResult = $this->chargeWithCulqi($tenant, $plan, $billingCycle, $token);

                $subscription->status = 'active';
                $subscription->current_period_start = now();
                $subscription->current_period_end = $billingCycle === 'yearly'
                    ? now()->addYear()
                    : now()->addMonth();
                $subscription->payment_gateway = 'culqi';
                $subscription->gateway_customer_id = $chargeResult['customer_id'] ?? null;
                $subscription->gateway_card_id = $chargeResult['card_id'] ?? null;
                $subscription->card_last_four = $chargeResult['card_last_four'] ?? null;
                $subscription->card_brand = $chargeResult['card_brand'] ?? null;
            } else {
                // Plan gratuito
                $subscription->status = 'active';
                $subscription->current_period_start = now();
                $subscription->current_period_end = now()->addYear();
            }

            $subscription->save();

            // Registrar pago si se realizó un cobro
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

            // Actualizar plan del tenant
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

        $email = $tenant->user?->email ?? "{$tenant->ruc}@facturacion.pe";

        try {
            $culqi = new \Culqi\Culqi(['api_key' => config('services.culqi.secret_key')]);

            // Paso 1: Crear o reutilizar cliente en Culqi
            $customerId = $this->getOrCreateCulqiCustomer($culqi, $tenant, $email);

            // Paso 2: Crear tarjeta en Culqi (guarda el token para cobros recurrentes)
            $card = $culqi->Cards->create([
                'customer_id' => $customerId,
                'token_id' => $token,
            ]);

            if (is_string($card)) {
                throw new \RuntimeException("Error al registrar tarjeta: {$card}");
            }

            $cardId = $card->id;

            // Paso 3: Cobrar usando la tarjeta guardada (no el token de un solo uso)
            $charge = $culqi->Charges->create([
                'amount' => $amount,
                'currency_code' => 'PEN',
                'email' => $email,
                'source_id' => $cardId,
                'description' => "Suscripción {$plan->name} - {$tenant->razon_social}",
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                    'plan' => $plan->slug,
                    'billing_cycle' => $billingCycle,
                ],
            ]);

            if (is_string($charge)) {
                throw new \RuntimeException("Error en el cobro: {$charge}");
            }

            return [
                'charge_id' => $charge->id ?? null,
                'customer_id' => $customerId,
                'card_id' => $cardId,
                'card_last_four' => $card->source->last_four ?? ($charge->source->last_four ?? null),
                'card_brand' => $card->source->iin->card_brand ?? ($charge->source->iin->card_brand ?? null),
                'amount' => $amount / 100,
                'response' => (array) $charge,
            ];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Fallo en el cobro con Culqi', [
                'tenant_id' => $tenant->id,
                'plan' => $plan->slug,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Error al procesar el pago: ' . $e->getMessage());
        }
    }

    /**
     * Busca un cliente existente en Culqi por email o crea uno nuevo.
     */
    private function getOrCreateCulqiCustomer(\Culqi\Culqi $culqi, Tenant $tenant, string $email): string
    {
        // Verificar si el tenant ya tiene un cliente en Culqi de una suscripción anterior
        $existingCustomerId = $tenant->subscriptions()
            ->whereNotNull('gateway_customer_id')
            ->latest()
            ->value('gateway_customer_id');

        if ($existingCustomerId) {
            return $existingCustomerId;
        }

        // Crear nuevo cliente en Culqi
        $names = explode(' ', $tenant->razon_social ?? 'Cliente', 2);
        $customer = $culqi->Customers->create([
            'first_name' => $names[0] ?? 'Cliente',
            'last_name' => $names[1] ?? $tenant->ruc,
            'email' => $email,
            'address' => $tenant->direccion ?? 'Lima',
            'address_city' => 'Lima',
            'country_code' => 'PE',
            'phone_number' => $tenant->telefonos[0] ?? '999999999',
        ]);

        if (is_string($customer)) {
            throw new \RuntimeException("Error al crear cliente Culqi: {$customer}");
        }

        return $customer->id;
    }
}

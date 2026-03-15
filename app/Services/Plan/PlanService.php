<?php

namespace App\Services\Plan;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class PlanService
{
    private const TIER_ORDER = ['free' => 0, 'starter' => 1, 'growth' => 2, 'business' => 3];

    /**
     * Get the active plan for a tenant (from subscription or fallback to free).
     */
    public function getActivePlan(Tenant $tenant): Plan
    {
        return Cache::remember(
            "tenant:{$tenant->id}:active_plan",
            300,
            function () use ($tenant) {
                $subscription = $tenant->activeSubscription;

                if ($subscription) {
                    return $subscription->plan;
                }

                return Plan::where('slug', 'free')->first()
                    ?? $this->buildFreePlan();
            }
        );
    }

    /**
     * Get a specific limit value for the tenant's plan.
     */
    public function getLimit(Tenant $tenant, string $key, mixed $default = 0): mixed
    {
        $plan = $this->getActivePlan($tenant);

        return $plan->limits[$key] ?? $default;
    }

    /**
     * Check if a feature is available in the tenant's plan.
     */
    public function canUseFeature(Tenant $tenant, string $feature): bool
    {
        $plan = $this->getActivePlan($tenant);

        return in_array($feature, $plan->features ?? []);
    }

    /**
     * Check if tenant has reached a usage limit.
     * Returns [allowed, current, limit] array.
     */
    public function checkUsageLimit(Tenant $tenant, string $limitKey): array
    {
        $this->ensureUsageResetCurrent($tenant);

        $limit = $this->getLimit($tenant, $limitKey);

        // -1 means unlimited
        if ($limit === -1) {
            return ['allowed' => true, 'current' => 0, 'limit' => -1];
        }

        $current = match ($limitKey) {
            'documents_month' => $tenant->documents_this_month,
            'ai_messages_month' => $tenant->ai_messages_this_month,
            default => 0,
        };

        return [
            'allowed' => $current < $limit,
            'current' => $current,
            'limit' => $limit,
        ];
    }

    /**
     * Increment a usage counter for the tenant.
     */
    public function incrementUsage(Tenant $tenant, string $type, int $amount = 1): void
    {
        $this->ensureUsageResetCurrent($tenant);

        $column = match ($type) {
            'documents' => 'documents_this_month',
            'ai_messages' => 'ai_messages_this_month',
            default => null,
        };

        if ($column) {
            $tenant->increment($column, $amount);
            Cache::forget("tenant:{$tenant->id}:active_plan");
        }
    }

    /**
     * Reset monthly usage counters for a tenant.
     */
    public function resetMonthlyUsage(Tenant $tenant): void
    {
        $tenant->update([
            'documents_this_month' => 0,
            'ai_messages_this_month' => 0,
            'usage_reset_month' => now()->format('Y-m'),
        ]);

        Cache::forget("tenant:{$tenant->id}:active_plan");
    }

    /**
     * Find the cheapest plan above the current one that satisfies a feature or usage key.
     *
     * @param string $key   The feature name or limit key
     * @param string $type  'feature' or 'usage'
     * @return array|null   ['slug' => 'growth', 'price' => 99, 'label' => '...', 'trial_days' => 14] or null
     */
    public function getNextPlanFor(Tenant $tenant, string $key, string $type): ?array
    {
        $currentPlan = $this->getActivePlan($tenant);
        $currentTier = self::TIER_ORDER[$currentPlan->slug] ?? 0;

        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($plans as $plan) {
            $planTier = self::TIER_ORDER[$plan->slug] ?? 0;

            // Only consider plans above current tier
            if ($planTier <= $currentTier) {
                continue;
            }

            $satisfies = match ($type) {
                'feature' => in_array($key, $plan->features ?? []),
                'usage' => ($plan->limits[$key] ?? 0) === -1
                    || ($plan->limits[$key] ?? 0) > ($currentPlan->limits[$key] ?? 0),
                default => false,
            };

            if ($satisfies) {
                return [
                    'slug' => $plan->slug,
                    'price' => (float) $plan->price_monthly,
                    'currency' => 'PEN',
                    'label' => "Desbloquea por S/{$plan->price_monthly}/mes",
                    'trial_days' => 14,
                ];
            }
        }

        return null;
    }

    /**
     * Get full usage report for a tenant.
     */
    public function getUsageReport(Tenant $tenant): array
    {
        $this->ensureUsageResetCurrent($tenant);
        $plan = $this->getActivePlan($tenant);

        $limits = $plan->limits ?? [];
        $tier = self::TIER_ORDER[$plan->slug] ?? 0;

        $nextUpgrade = $tier < 3
            ? $this->getNextUpgrade($tenant)
            : null;

        return [
            'plan' => [
                'slug' => $plan->slug,
                'tier' => $tier,
                'price' => (float) $plan->price_monthly,
            ],
            'usage' => [
                'documents_month' => [
                    'current' => $tenant->documents_this_month,
                    'limit' => $limits['documents_month'] ?? -1,
                    'unlimited' => ($limits['documents_month'] ?? -1) === -1,
                ],
                'ai_messages_month' => [
                    'current' => $tenant->ai_messages_this_month,
                    'limit' => $limits['ai_messages_month'] ?? 10,
                    'unlimited' => ($limits['ai_messages_month'] ?? 0) === -1,
                ],
                'team_members' => [
                    'limit' => $limits['team_members'] ?? 1,
                ],
                'sucursales' => [
                    'current' => $tenant->sucursales()->count(),
                    'limit' => $limits['sucursales'] ?? 1,
                ],
                'productos' => [
                    'limit' => $limits['productos'] ?? 100,
                    'unlimited' => ($limits['productos'] ?? 100) === -1,
                ],
            ],
            'features' => $plan->features ?? [],
            'next_upgrade' => $nextUpgrade,
        ];
    }

    /**
     * Ensure usage counters are reset if we're in a new month.
     */
    private function ensureUsageResetCurrent(Tenant $tenant): void
    {
        $currentMonth = now()->format('Y-m');

        if ($tenant->usage_reset_month !== $currentMonth) {
            $this->resetMonthlyUsage($tenant);
            $tenant->refresh();
        }
    }

    /**
     * Get the next plan above current tier (generic upgrade suggestion).
     */
    private function getNextUpgrade(Tenant $tenant): ?array
    {
        $currentPlan = $this->getActivePlan($tenant);
        $currentTier = self::TIER_ORDER[$currentPlan->slug] ?? 0;

        $nextPlan = Plan::where('is_active', true)
            ->where('sort_order', '>', $currentTier)
            ->orderBy('sort_order')
            ->first();

        if (! $nextPlan) {
            return null;
        }

        return [
            'slug' => $nextPlan->slug,
            'price' => (float) $nextPlan->price_monthly,
        ];
    }

    /**
     * Build a fallback free plan if none exists in DB.
     */
    private function buildFreePlan(): Plan
    {
        $plan = new Plan();
        $plan->slug = 'free';
        $plan->name = 'Free';
        $plan->price_monthly = 0;
        $plan->price_yearly = 0;
        $plan->limits = [
            'documents_month' => -1,
            'ai_messages_month' => 10,
            'team_members' => 1,
            'sucursales' => 1,
            'productos' => 100,
            'catalog_listings' => 5,
            'rfqs_month' => 1,
            'b2b_requests_month' => 3,
            'reviews_month' => 1,
            'feed_posts_month' => 2,
        ];
        $plan->features = [
            'facturacion', 'boletas', 'notas', 'guias_remision',
            'cotizaciones', 'notas_venta', 'inventario_basico', 'reportes_basicos',
            'feed_view', 'marketplace_browse', 'b2b_basic',
        ];

        return $plan;
    }

    /**
     * Clear cached plan data for a tenant.
     */
    public function clearCache(Tenant $tenant): void
    {
        Cache::forget("tenant:{$tenant->id}:active_plan");
    }
}

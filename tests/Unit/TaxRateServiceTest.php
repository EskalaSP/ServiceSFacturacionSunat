<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\TaxRateService;
use Tests\TestCase;

class TaxRateServiceTest extends TestCase
{
    public function test_general_regimen_returns_18_percent(): void
    {
        $service = new TaxRateService();
        $tenant = new Tenant(['tax_regime' => 'general']);

        $this->assertSame(18.0, $service->defaultIgvRate($tenant, '2026-01-15'));
        $this->assertSame(18.0, $service->defaultIgvRate($tenant, '2029-12-31'));
    }

    public function test_null_tenant_falls_back_to_18(): void
    {
        $service = new TaxRateService();
        $this->assertSame(18.0, $service->defaultIgvRate(null));
    }

    public function test_mype_restaurantes_schedule_by_year(): void
    {
        $service = new TaxRateService();
        $tenant = new Tenant(['tax_regime' => 'mype_restaurantes']);

        $this->assertSame(10.5, $service->defaultIgvRate($tenant, '2026-01-01'));
        $this->assertSame(10.5, $service->defaultIgvRate($tenant, '2026-12-31'));
        $this->assertSame(15.0, $service->defaultIgvRate($tenant, '2027-06-15'));
        $this->assertSame(18.0, $service->defaultIgvRate($tenant, '2028-01-01'));
        $this->assertSame(18.0, $service->defaultIgvRate($tenant, '2030-01-01'));
    }

    public function test_igv_rate_override_wins_over_regimen(): void
    {
        $service = new TaxRateService();
        $tenant = new Tenant([
            'tax_regime' => 'mype_restaurantes',
            'igv_rate_override' => 12.0,
        ]);

        $this->assertSame(12.0, $service->defaultIgvRate($tenant, '2026-01-01'));
    }

    public function test_rate_for_item_prefers_explicit_porcentaje_igv(): void
    {
        $service = new TaxRateService();
        $tenant = new Tenant(['tax_regime' => 'general']);
        $item = ['tip_afe_igv' => '10', 'porcentaje_igv' => 10.5];

        $this->assertSame(10.5, $service->rateForItem($item, $tenant, '2026-01-01'));
    }

    public function test_rate_for_item_ivap_is_4_percent(): void
    {
        $service = new TaxRateService();
        $tenant = new Tenant(['tax_regime' => 'general']);
        $item = ['tip_afe_igv' => '17'];

        $this->assertSame(4.0, $service->rateForItem($item, $tenant));
    }

    public function test_rate_for_item_gravado_mype_restaurantes_2026(): void
    {
        $service = new TaxRateService();
        $tenant = new Tenant(['tax_regime' => 'mype_restaurantes']);
        $item = ['tip_afe_igv' => '10'];

        $this->assertSame(10.5, $service->rateForItem($item, $tenant, '2026-03-15'));
    }

    public function test_rate_for_item_exonerado_is_zero(): void
    {
        $service = new TaxRateService();
        $tenant = new Tenant(['tax_regime' => 'general']);

        $this->assertSame(0.0, $service->rateForItem(['tip_afe_igv' => '20'], $tenant));
        $this->assertSame(0.0, $service->rateForItem(['tip_afe_igv' => '30'], $tenant));
        $this->assertSame(0.0, $service->rateForItem(['tip_afe_igv' => '40'], $tenant));
    }

    // ──────────────────────────────────────────────────────────────
    // NRUS (Nuevo Régimen Único Simplificado)
    // ──────────────────────────────────────────────────────────────

    public function test_nrus_regime_always_returns_0(): void
    {
        $service = new TaxRateService();
        $tenant = new Tenant(['tax_regime' => 'nrus']);

        $this->assertSame(0.0, $service->defaultIgvRate($tenant, '2026-04-18'));
        $this->assertSame(0.0, $service->defaultIgvRate($tenant, '2029-12-31'));
    }

    public function test_nrus_default_tip_afe_igv_is_30(): void
    {
        $service = new TaxRateService();
        $tenant = new Tenant(['tax_regime' => 'nrus']);

        $this->assertSame('30', $service->defaultTipAfeIgv($tenant));
    }

    public function test_general_default_tip_afe_igv_is_10(): void
    {
        $service = new TaxRateService();
        $tenant = new Tenant(['tax_regime' => 'general']);

        $this->assertSame('10', $service->defaultTipAfeIgv($tenant));
    }

    public function test_nrus_item_without_tip_afe_igv_returns_0(): void
    {
        $service = new TaxRateService();
        $tenant = new Tenant(['tax_regime' => 'nrus']);

        // Sin tip_afe_igv explícito: debe aplicar default '30' → 0%
        $this->assertSame(0.0, $service->rateForItem([], $tenant));
    }

    public function test_is_nrus(): void
    {
        $service = new TaxRateService();
        $this->assertTrue($service->isNrus(new Tenant(['tax_regime' => 'nrus'])));
        $this->assertFalse($service->isNrus(new Tenant(['tax_regime' => 'general'])));
        $this->assertFalse($service->isNrus(new Tenant(['tax_regime' => 'mype_restaurantes'])));
        $this->assertFalse($service->isNrus(null));
    }
}

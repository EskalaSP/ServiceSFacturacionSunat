<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\TaxRateService;
use PHPUnit\Framework\TestCase;

/**
 * La lista de regímenes pasó de general/mype_restaurantes/nrus a los cuatro
 * oficiales: rus, rer, mype, general.
 *
 * Lo que este test protege es la tasa, no los nombres: renombrar 'nrus' sin
 * actualizar TaxRateService habría hecho que un emisor del RUS pasara de 0% a
 * 18% de IGV, emitiendo comprobantes mal sin que nadie tocara nada.
 */
class TaxRateRegimenTest extends TestCase
{
    private function tenant(string $regimen, ?float $override = null): Tenant
    {
        $tenant = new Tenant;
        $tenant->tax_regime = $regimen;
        $tenant->igv_rate_override = $override;

        return $tenant;
    }

    public function test_el_rus_no_paga_igv(): void
    {
        $servicio = new TaxRateService;

        $this->assertSame(0.0, $servicio->defaultIgvRate($this->tenant('rus')));
        $this->assertTrue($servicio->isNrus($this->tenant('rus')));
        $this->assertSame('30', $servicio->defaultTipAfeIgv($this->tenant('rus')));
    }

    public function test_los_demas_regimenes_tributan_al_18(): void
    {
        $servicio = new TaxRateService;

        foreach (['rer', 'mype', 'general'] as $regimen) {
            $this->assertSame(18.0, $servicio->defaultIgvRate($this->tenant($regimen)), $regimen);
            $this->assertSame('10', $servicio->defaultTipAfeIgv($this->tenant($regimen)), $regimen);
        }
    }

    public function test_el_override_manda_sobre_el_regimen(): void
    {
        $servicio = new TaxRateService;

        // Un restaurante MYPE acogido a la Ley 31556: el régimen es mype y la
        // tasa especial viaja en el override, que es justo el punto del cambio.
        $this->assertSame(10.5, $servicio->defaultIgvRate($this->tenant('mype', 10.5)));

        // Ni siquiera el RUS pisa un override explícito: si alguien lo declara,
        // es porque sabe algo que el régimen no dice.
        $this->assertSame(5.0, $servicio->defaultIgvRate($this->tenant('rus', 5.0)));
    }

    public function test_la_tasa_de_la_ley_31556_sigue_disponible_para_sugerirla(): void
    {
        $servicio = new TaxRateService;

        $this->assertSame(10.5, $servicio->tasaLey31556('2026-07-30'));
        $this->assertSame(15.0, $servicio->tasaLey31556('2027-01-15'));
        $this->assertNull($servicio->tasaLey31556('2035-01-01'));
    }

    public function test_un_regimen_desconocido_no_deja_al_emisor_sin_igv(): void
    {
        // Peor que un 18% de más es un 0% de menos: eso es una omisión de
        // tributo. Ante un valor que no se reconoce, se cobra IGV.
        $this->assertSame(18.0, (new TaxRateService)->defaultIgvRate($this->tenant('lo-que-sea')));
    }
}

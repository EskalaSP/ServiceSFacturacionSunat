<?php

declare(strict_types=1);

use App\Services\TaxRateService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alinea tax_regime con los cuatro regímenes que reconoce SUNAT.
 *
 *   nrus              → rus    (mismo régimen, el nombre oficial es Nuevo RUS)
 *   mype_restaurantes → mype   (es un MYPE; lo "restaurantes" era la tasa de
 *                               IGV especial de la Ley 31556, que ya se
 *                               configura aparte en igv_rate_override)
 *
 * `rer` no existía como opción y se agrega: un emisor del Régimen Especial no
 * tenía ninguna casilla que lo describiera y terminaba marcado como general.
 *
 * Los tenants que ya venían en 'mype_restaurantes' conservan su
 * igv_rate_override, así que su tasa no cambia: solo se corrige la etiqueta
 * del régimen.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ORDEN IMPORTANTE: primero congelar la tasa, después renombrar.
        //
        // Un tenant en 'mype_restaurantes' sin igv_rate_override recibía su tasa
        // del schedule de la Ley 31556 (10.5% en 2026). Al pasar a 'mype' ese
        // schedule ya no aplica y saltaría a 18% en silencio — emitiendo
        // comprobantes con el IGV equivocado sin que nadie toque nada.
        // Guardar la tasa vigente en el override deja el resultado idéntico.
        $tasaVigente = TaxRateService::LEY_31556[(int) date('Y')] ?? 18.0;

        DB::table('tenants')
            ->where('tax_regime', 'mype_restaurantes')
            ->whereNull('igv_rate_override')
            ->update(['igv_rate_override' => $tasaVigente]);

        DB::table('tenants')->where('tax_regime', 'nrus')->update(['tax_regime' => 'rus']);
        DB::table('tenants')->where('tax_regime', 'mype_restaurantes')->update(['tax_regime' => 'mype']);

        // Cualquier valor fuera de los cuatro queda en el default en vez de
        // dejar filas que ninguna validación aceptaría después.
        DB::table('tenants')
            ->whereNotIn('tax_regime', ['rus', 'rer', 'mype', 'general'])
            ->update(['tax_regime' => 'general']);
    }

    public function down(): void
    {
        DB::table('tenants')->where('tax_regime', 'rus')->update(['tax_regime' => 'nrus']);

        // 'mype' no se revierte a 'mype_restaurantes': sin saber la tasa que
        // tenía cada uno sería adivinar, y devolver a todos los MYPE a un
        // régimen de restaurantes es peor que dejarlos como MYPE.
    }
};

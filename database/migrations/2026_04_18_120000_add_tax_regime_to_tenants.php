<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega soporte para régimen tributario por tenant.
 *
 * tax_regime:
 *  - 'general' (default): 18% IGV (16% IGV + 2% IPM)
 *  - 'mype_restaurantes': tasa reducida por Ley 31556 ampliada
 *      2026: 10.5% (8% IGV + 2.5% IPM)
 *      2027: 15.0% (12% IGV + 3.0% IPM)
 *      2028: 18.0% (14.5% IGV + 3.5% IPM)
 *      2029+: 18.0% (régimen general)
 *
 * igv_rate_override: Permite forzar una tasa manual (tiene precedencia sobre el régimen).
 *   Útil si SUNAT cambia las reglas antes de que actualicemos el schedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('tax_regime', 32)->default('general')->after('plan');
            $table->decimal('igv_rate_override', 5, 2)->nullable()->after('tax_regime');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['tax_regime', 'igv_rate_override']);
        });
    }
};

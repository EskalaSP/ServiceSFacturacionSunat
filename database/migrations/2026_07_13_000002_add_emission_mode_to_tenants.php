<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo de emisión por empresa (Escenario 2):
 *   - 'plan'      → la emisión respeta los límites del plan/suscripción (comportamiento actual).
 *   - 'unlimited' → la empresa emite sin restricciones, sin depender de un plan.
 *
 * Las empresas existentes quedan en 'plan' para NO alterar el comportamiento vigente.
 * Las nuevas se crean según la configuración global (ver SettingsService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('emission_mode', 20)->default('plan')->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('emission_mode');
        });
    }
};

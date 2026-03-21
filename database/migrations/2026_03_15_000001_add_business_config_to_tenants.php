<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('telefonos')->nullable()->after('distrito');
            $table->json('emails')->nullable()->after('telefonos');
            $table->json('cuentas_bancarias')->nullable()->after('emails');
            $table->json('billeteras_digitales')->nullable()->after('cuentas_bancarias');
            $table->string('mensaje_agradecimiento', 500)->nullable()->after('billeteras_digitales');
            $table->string('mensaje_promocional', 500)->nullable()->after('mensaje_agradecimiento');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'telefonos',
                'emails',
                'cuentas_bancarias',
                'billeteras_digitales',
                'mensaje_agradecimiento',
                'mensaje_promocional',
            ]);
        });
    }
};

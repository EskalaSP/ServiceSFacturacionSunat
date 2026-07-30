<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indica si el RUC del emisor está obligado por SUNAT a enviar el Código de
 * producto SUNAT en cada ítem (padrón de contribuyentes con ind_padron = '12',
 * "Obligado a enviar código de producto").
 *
 * Por defecto false: el campo sigue siendo opcional (comportamiento vigente).
 * Se marca true cuando SUNAT incluye ese RUC en el padrón.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('obligado_codigo_producto')->default(false)->after('tax_regime');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('obligado_codigo_producto');
        });
    }
};

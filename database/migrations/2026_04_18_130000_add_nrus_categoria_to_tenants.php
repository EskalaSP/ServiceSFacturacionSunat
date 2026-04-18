<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega soporte para NRUS (Nuevo Régimen Único Simplificado).
 *
 * NRUS es un régimen para pequeños contribuyentes:
 *  - Pagan cuota mensual fija: S/ 20 (categoría 1) o S/ 50 (categoría 2)
 *  - SOLO emiten boletas de venta (no facturas)
 *  - Pueden emitir NC/ND pero únicamente contra sus boletas
 *  - Sus operaciones son INAFECTAS al IGV (igv = 0)
 *
 * nrus_categoria: '1' o '2' — solo documental, no afecta cálculos.
 *   El régimen NRUS se activa vía tax_regime='nrus' (ver migración 2026_04_18_120000).
 *   Si tax_regime != 'nrus' este campo se ignora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedTinyInteger('nrus_categoria')->nullable()->after('igv_rate_override');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('nrus_categoria');
        });
    }
};

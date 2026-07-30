<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo Código de Producto SUNAT — UNSPSC v14 (Catálogo N.° 25).
 *
 * Datos de referencia estáticos (~52k códigos de 8 dígitos: nivel producto y
 * nivel clase). Se usa para validar cbc:ItemClassificationCode en los ítems
 * (OBS-4332 / ERR-3496 desde 01/01/2027). Se carga con UnspscCodeSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unspsc_codes', function (Blueprint $table) {
            $table->char('codigo', 8)->primary();
            $table->string('descripcion');
            $table->char('clase', 6)->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unspsc_codes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jerarquía del catálogo UNSPSC v14 (Segmento → Familia → Clase) para el buscador
 * de Código de Producto SUNAT. Los productos (nivel 8 dígitos) están en unspsc_codes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unspsc_taxonomy', function (Blueprint $table) {
            $table->string('nivel', 10);              // segmento | familia | clase
            $table->string('codigo', 6);              // 2, 4 o 6 dígitos
            $table->string('nombre');
            $table->string('parent', 4)->nullable();  // código del nivel superior
            $table->primary(['nivel', 'codigo']);
            $table->index('parent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unspsc_taxonomy');
    }
};

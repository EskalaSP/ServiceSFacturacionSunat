<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sire_periodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Periodo tributario en formato yyyymm (ver validaciones 1005-1007 del manual)
            $table->string('per_tributario', 6);

            // Código de libro — 080000 RCE, 140000 RVIE (Anexo I)
            $table->string('cod_libro', 6)->default('080000');

            // Fase interna: propuesta | preliminar | generado
            $table->string('fase', 20)->default('propuesta');

            // Estado del padrón (servicio 5.33)
            $table->string('cod_estado', 10)->nullable();
            $table->string('des_estado', 100)->nullable();

            $table->timestamp('fec_cierre')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'per_tributario', 'cod_libro'], 'sire_per_unique');
            $table->index(['tenant_id', 'fase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sire_periodos');
    }
};

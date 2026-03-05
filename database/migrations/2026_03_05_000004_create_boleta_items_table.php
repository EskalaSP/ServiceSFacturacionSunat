<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boleta_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boleta_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 50)->nullable();
            $table->string('descripcion', 500);
            $table->string('unidad', 5);
            $table->decimal('cantidad', 12, 4);
            $table->decimal('mto_valor_unitario', 12, 4)->default(0);
            $table->decimal('mto_valor_venta', 12, 2)->default(0);
            $table->decimal('mto_base_igv', 12, 2)->default(0);
            $table->decimal('porcentaje_igv', 5, 2)->default(18);
            $table->decimal('igv', 12, 2)->default(0);
            $table->string('tip_afe_igv', 2)->default('10');
            $table->decimal('isc', 12, 2)->default(0);
            $table->decimal('icbper', 12, 2)->default(0);
            $table->decimal('total_impuestos', 12, 2)->default(0);
            $table->decimal('mto_precio_unitario', 12, 4)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boleta_items');
    }
};

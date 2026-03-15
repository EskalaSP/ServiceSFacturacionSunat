<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->string('metodo', 20); // efectivo|yape|plin|transferencia|tarjeta|cheque|otro
            $table->decimal('monto', 12, 2);
            $table->string('referencia', 100)->nullable();
            $table->decimal('monto_recibido', 12, 2)->nullable(); // solo efectivo
            $table->string('notas', 255)->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

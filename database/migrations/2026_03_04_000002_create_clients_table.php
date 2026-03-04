<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('tipo_documento', 1);
            $table->string('numero_documento', 15)->index();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('direccion', 500)->nullable();
            $table->string('email')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tipo_documento', 'numero_documento'], 'clients_tenant_doc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('tipo_documento', 2);
            $table->string('serie', 4);
            $table->unsignedInteger('correlativo')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'tipo_documento', 'serie'], 'series_tenant_tipo_serie_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};

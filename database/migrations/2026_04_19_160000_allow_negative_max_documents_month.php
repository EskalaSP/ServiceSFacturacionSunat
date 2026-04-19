<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte tenants.max_documents_month de UNSIGNED a SIGNED INT.
 *
 * Planes "ilimitados" (business, pro enterprise) guardan -1 como valor para
 * documents_month. La columna original era unsignedInteger y rechaza negativos,
 * rompiendo POST /suscripcion cuando se asignaba un plan business/pro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->integer('max_documents_month')->default(20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Antes de volver a unsigned, asegurar que ningún tenant tenga -1
            // (rompería el rollback). Los -1 se convierten a 999999 (casi ilimitado).
            \DB::table('tenants')
                ->where('max_documents_month', '<', 0)
                ->update(['max_documents_month' => 999999]);

            $table->unsignedInteger('max_documents_month')->default(20)->change();
        });
    }
};

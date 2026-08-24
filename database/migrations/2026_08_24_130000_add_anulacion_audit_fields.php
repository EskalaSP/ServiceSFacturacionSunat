<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de anulaciones: quién anuló y por qué motivo.
 * Aplica tanto a la Comunicación de Baja / Reversión (voided_documents)
 * como al Resumen Diario de anulación (summaries).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voided_documents', function (Blueprint $table) {
            $table->string('motivo', 255)->nullable()->after('detalles');
            $table->unsignedBigInteger('anulado_por')->nullable()->after('motivo');
        });

        Schema::table('summaries', function (Blueprint $table) {
            $table->string('motivo', 255)->nullable()->after('document_ids');
            $table->unsignedBigInteger('anulado_por')->nullable()->after('motivo');
        });
    }

    public function down(): void
    {
        Schema::table('voided_documents', function (Blueprint $table) {
            $table->dropColumn(['motivo', 'anulado_por']);
        });

        Schema::table('summaries', function (Blueprint $table) {
            $table->dropColumn(['motivo', 'anulado_por']);
        });
    }
};

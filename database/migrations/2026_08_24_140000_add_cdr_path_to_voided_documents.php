<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda la constancia (CDR) que SUNAT devuelve al procesar la Comunicación de Baja (RA).
 * Antes se recibía pero se descartaba (la tabla no tenía dónde guardarla).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voided_documents', function (Blueprint $table) {
            $table->string('cdr_path')->nullable()->after('xml_path');
        });
    }

    public function down(): void
    {
        Schema::table('voided_documents', function (Blueprint $table) {
            $table->dropColumn('cdr_path');
        });
    }
};

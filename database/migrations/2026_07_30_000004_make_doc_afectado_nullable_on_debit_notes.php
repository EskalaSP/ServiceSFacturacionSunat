<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La nota de débito por Penalidades (motivo 13, Cat. 10) puede emitirse SIN
 * documento afectado (ERR-2524 exime al motivo 13). Se permite null en los
 * campos del documento afectado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->string('doc_afectado_tipo', 2)->nullable()->change();
            $table->string('doc_afectado_serie', 4)->nullable()->change();
            $table->string('doc_afectado_correlativo', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->string('doc_afectado_tipo', 2)->nullable(false)->change();
            $table->string('doc_afectado_serie', 4)->nullable(false)->change();
            $table->string('doc_afectado_correlativo', 10)->nullable(false)->change();
        });
    }
};

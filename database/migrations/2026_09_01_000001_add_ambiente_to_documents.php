<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el campo `ambiente` (prueba | produccion) a todos los documentos.
 * Por defecto es 'produccion' para documentos nuevos.
 * La migración actualiza masivamente los existentes a 'prueba' ya que
 * ninguno fue emitido aún en producción real.
 */
return new class extends Migration
{
    private array $tablas = ['boletas', 'invoices', 'credit_notes', 'debit_notes', 'summaries', 'voided_documents'];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('ambiente', 10)->default('produccion')->after('tenant_id');
            });

            // Marcar todos los documentos existentes como prueba
            DB::table($tabla)->update(['ambiente' => 'prueba']);
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('ambiente');
            });
        }
    }
};

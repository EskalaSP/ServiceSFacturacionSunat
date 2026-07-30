<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el Código de Producto SUNAT (UNSPSC, cbc:ItemClassificationCode)
 * a las tablas de ítems de los comprobantes que se envían a SUNAT.
 */
return new class extends Migration
{
    private array $tables = ['invoice_items', 'boleta_items', 'credit_note_items', 'debit_note_items'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->char('cod_producto_sunat', 8)->nullable()->after('codigo');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('cod_producto_sunat');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['invoice_items', 'boleta_items', 'credit_note_items', 'debit_note_items', 'internal_document_items'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('mto_valor_unitario', 18, 10)->default(0)->change();
            });
        }
    }

    public function down(): void
    {
        $tables = ['invoice_items', 'boleta_items', 'credit_note_items', 'debit_note_items', 'internal_document_items'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('mto_valor_unitario', 12, 4)->default(0)->change();
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'invoices',
            'boletas',
            'credit_notes',
            'debit_notes',
            'dispatch_guides',
            'internal_documents',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dateTime('fecha_emision')->change();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'invoices',
            'boletas',
            'credit_notes',
            'debit_notes',
            'dispatch_guides',
            'internal_documents',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->date('fecha_emision')->change();
            });
        }
    }
};

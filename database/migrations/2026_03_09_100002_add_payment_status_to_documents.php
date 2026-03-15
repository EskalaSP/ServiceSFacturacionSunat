<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'boletas', 'internal_documents'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->enum('payment_status', ['pendiente', 'parcial', 'pagado'])->default('pendiente')->after('observacion');
                $t->decimal('monto_pagado', 12, 2)->default(0)->after('payment_status');
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'boletas', 'internal_documents'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['payment_status', 'monto_pagado']);
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boletas', function (Blueprint $table) {
            $table->decimal('mto_oper_exportacion', 12, 2)->default(0)->after('mto_oper_inafectas');
            $table->decimal('mto_base_ivap', 12, 2)->default(0)->after('mto_igv');
            $table->decimal('mto_ivap', 12, 2)->default(0)->after('mto_base_ivap');
        });
    }

    public function down(): void
    {
        Schema::table('boletas', function (Blueprint $table) {
            $table->dropColumn(['mto_oper_exportacion', 'mto_base_ivap', 'mto_ivap']);
        });
    }
};

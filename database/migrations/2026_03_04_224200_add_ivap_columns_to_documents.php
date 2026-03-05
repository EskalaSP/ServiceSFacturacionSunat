<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->decimal('mto_base_ivap', 12, 2)->default(0)->after('mto_igv');
                $table->decimal('mto_ivap', 12, 2)->default(0)->after('mto_base_ivap');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn(['mto_base_ivap', 'mto_ivap']);
            });
        }
    }
};

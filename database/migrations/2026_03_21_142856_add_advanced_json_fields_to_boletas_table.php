<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boletas', function (Blueprint $table) {
            $table->json('detraccion')->nullable()->after('cuotas');
            $table->json('percepcion')->nullable()->after('detraccion');
            $table->json('anticipos')->nullable()->after('percepcion');
            $table->json('descuentos_globales')->nullable()->after('anticipos');
            $table->json('extras')->nullable()->after('descuentos_globales');
        });
    }

    public function down(): void
    {
        Schema::table('boletas', function (Blueprint $table) {
            $table->dropColumn(['detraccion', 'percepcion', 'anticipos', 'descuentos_globales', 'extras']);
        });
    }
};

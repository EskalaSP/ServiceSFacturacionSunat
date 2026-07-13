<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los planes pueden declararse explícitamente ilimitados y/o con vigencia:
 *   - is_unlimited  → el plan no aplica ningún límite de comprobantes.
 *   - duration_days → días de vigencia de la suscripción (vencimiento).
 *                     null = sin vencimiento (se mantiene el comportamiento previo de +1 año).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('is_unlimited')->default(false)->after('features');
            $table->unsignedSmallInteger('duration_days')->nullable()->after('is_unlimited');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['is_unlimited', 'duration_days']);
        });
    }
};

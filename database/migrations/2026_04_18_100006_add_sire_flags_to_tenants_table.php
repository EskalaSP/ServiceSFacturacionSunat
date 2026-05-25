<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Activación explícita de SIRE (se marca true después de verificar credenciales)
            $table->boolean('sire_enabled')->default(false)->after('is_active');

            // Auditoría
            $table->string('sire_last_period_synced', 6)->nullable()->after('sire_enabled');
            $table->timestamp('sire_last_reconciliation_at')->nullable()->after('sire_last_period_synced');

            // Credenciales SIRE separadas (opcional, fallback a client_id/client_secret del tenant)
            $table->string('sire_client_id', 100)->nullable()->after('sire_last_reconciliation_at');
            $table->string('sire_client_secret', 255)->nullable()->after('sire_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'sire_enabled',
                'sire_last_period_synced',
                'sire_last_reconciliation_at',
                'sire_client_id',
                'sire_client_secret',
            ]);
        });
    }
};

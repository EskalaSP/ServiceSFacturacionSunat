<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los valores válidos para tenant_user.role son ahora: simple | completo | cajero
 * (VARCHAR(20) — ningún cambio de schema necesario).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Migrar filas antiguas con roles 'owner' → 'simple' y 'especial' → 'completo'
        DB::table('tenant_user')->where('role', 'owner')->update(['role' => 'simple']);
        DB::table('tenant_user')->where('role', 'especial')->update(['role' => 'completo']);
    }

    public function down(): void
    {
        DB::table('tenant_user')->where('role', 'simple')->update(['role' => 'owner']);
        DB::table('tenant_user')->where('role', 'completo')->update(['role' => 'especial']);
    }
};

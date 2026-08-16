<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles predefinidos y estado para los usuarios del panel administrativo:
 *   - super_admin: acceso total (incluye gestión de usuarios).
 *   - admin:       gestiona empresas, planes, series, sucursales y usuarios.
 *   - soporte:     ver empresas y reenviar comprobantes.
 *   - lectura:     solo lectura.
 *   - (null):      usuario cliente sin acceso al panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->nullable()->after('is_admin');
            $table->boolean('is_active')->default(true)->after('role');
        });

        // Los administradores existentes pasan a super_admin.
        DB::table('users')->where('is_admin', true)->update(['role' => 'super_admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pivote usuario ↔ empresa para el panel multiusuario (RBAC de 3 niveles).
 *
 *   - role = owner : dueño de la empresa. Permisos totales sobre ella; `abilities` se ignora.
 *   - role = cajero: ayudante. Solo puede las abilities listadas (permisos granulares por tipo).
 *
 * El super admin (users.role = super_admin) hace bypass de todo esto vía Gate::before.
 * `tenants.user_id` se conserva como "dueño principal / creador".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('cajero'); // owner | cajero
            $table->json('abilities')->nullable();          // solo aplica a cajero
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id'], 'tenant_user_unique');
        });

        // Backfill: cada empresa existente con dueño (user_id) pasa a tener su fila owner.
        $ahora = now();
        $filas = DB::table('tenants')
            ->whereNotNull('user_id')
            ->get(['id', 'user_id'])
            ->map(fn ($t) => [
                'tenant_id' => $t->id,
                'user_id' => $t->user_id,
                'role' => 'owner',
                'abilities' => null,
                'is_active' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ])
            ->all();

        foreach (array_chunk($filas, 500) as $chunk) {
            DB::table('tenant_user')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
    }
};

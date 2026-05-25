<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte las columnas ENUM de las tablas de suscripciones a VARCHAR.
 *
 * Motivación: los ENUM de MySQL son rígidos y no funcionan igual en Postgres
 * (CHECK constraint) o SQL Server (sin ENUM nativo). Agregar un nuevo valor
 * requiere una migración destructiva en cada motor distinto.
 *
 * Al convertir a VARCHAR:
 *   - Añadir un nuevo estado (ej. "pending") no requiere migración.
 *   - Los 3 motores principales (MySQL/Postgres/SQL Server) quedan compatibles.
 *   - La validación de valores permitidos pasa al nivel de aplicación
 *     (Model::$statusValidos + FormRequest / casts).
 *
 * Columnas afectadas:
 *   - subscriptions.status          (trialing|active|past_due|cancelled|expired|pending)
 *   - subscriptions.billing_cycle   (monthly|yearly)
 *   - payments.status               (pending|completed|failed|refunded)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ENUM → VARCHAR es una operación portable via Laravel Schema.
        // En MySQL: ALTER TABLE MODIFY COLUMN.
        // En PostgreSQL: elimina check constraint + ALTER TABLE ALTER COLUMN TYPE.
        // En SQL Server: ALTER TABLE ALTER COLUMN.

        Schema::table('subscriptions', function (Blueprint $t) {
            $t->string('status', 20)->default('trialing')->change();
            $t->string('billing_cycle', 10)->default('monthly')->change();
        });

        if (Schema::hasTable('subscription_payments') && Schema::hasColumn('subscription_payments', 'status')) {
            Schema::table('subscription_payments', function (Blueprint $t) {
                $t->string('status', 20)->default('pending')->change();
            });
        }
    }

    public function down(): void
    {
        // Revertir: volver a ENUM. Normalizar cualquier "pending" a "trialing"
        // porque "pending" no es parte del ENUM original.
        \DB::table('subscriptions')->where('status', 'pending')->update(['status' => 'trialing']);

        $driver = \DB::getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL no soporta ALTER COLUMN TYPE ... CHECK en una sola sentencia.
            // Dejamos las columnas como varchar — el rollback completo no es necesario.
            Schema::table('subscriptions', function (Blueprint $t) {
                $t->string('status', 20)->default('trialing')->change();
                $t->string('billing_cycle', 10)->default('monthly')->change();
            });

            if (Schema::hasTable('subscription_payments') && Schema::hasColumn('subscription_payments', 'status')) {
                Schema::table('subscription_payments', function (Blueprint $t) {
                    $t->string('status', 20)->default('pending')->change();
                });
            }
        } else {
            Schema::table('subscriptions', function (Blueprint $t) {
                $t->enum('status', ['trialing', 'active', 'past_due', 'cancelled', 'expired'])
                    ->default('trialing')->change();
                $t->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly')->change();
            });

            if (Schema::hasTable('subscription_payments') && Schema::hasColumn('subscription_payments', 'status')) {
                Schema::table('subscription_payments', function (Blueprint $t) {
                    $t->enum('status', ['pending', 'completed', 'failed', 'refunded'])
                        ->default('pending')->change();
                });
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'invoices',
        'boletas',
        'credit_notes',
        'debit_notes',
    ];

    private string $values = "'pendiente', 'enviado', 'aceptado', 'rechazado', 'anulado', 'anulacion_en_proceso'";

    public function up(): void
    {
        // MySQL usa ENUM (migración 2026_03_10_140826) y SQLite (tests) no soporta
        // ALTER TABLE ... DROP CONSTRAINT — los CHECK constraints son solo para PostgreSQL.
        if (! in_array(DB::connection()->getDriverName(), ['pgsql'], true)) {
            return;
        }

        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_sunat_status_check");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_sunat_status_check CHECK (sunat_status IN ({$this->values}))");
        }
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['pgsql'], true)) {
            return;
        }

        $original = "'pendiente', 'enviado', 'aceptado', 'rechazado', 'anulado'";
        foreach ($this->tables as $table) {
            DB::statement("UPDATE {$table} SET sunat_status = 'pendiente' WHERE sunat_status = 'anulacion_en_proceso'");
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_sunat_status_check");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_sunat_status_check CHECK (sunat_status IN ({$original}))");
        }
    }
};

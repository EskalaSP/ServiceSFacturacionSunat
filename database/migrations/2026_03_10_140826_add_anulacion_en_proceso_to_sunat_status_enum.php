<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = ['invoices', 'boletas', 'credit_notes', 'debit_notes'];

    private string $newEnum = "enum('pendiente','enviado','aceptado','rechazado','anulado','anulacion_en_proceso')";

    private string $oldEnum = "enum('pendiente','enviado','aceptado','rechazado','anulado')";

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `sunat_status` {$this->newEnum} NOT NULL DEFAULT 'pendiente'");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->tables as $table) {
            DB::statement("UPDATE `{$table}` SET `sunat_status` = 'pendiente' WHERE `sunat_status` = 'anulacion_en_proceso'");
            DB::statement("ALTER TABLE `{$table}` MODIFY `sunat_status` {$this->oldEnum} NOT NULL DEFAULT 'pendiente'");
        }
    }
};

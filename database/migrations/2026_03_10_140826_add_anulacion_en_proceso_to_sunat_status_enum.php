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
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `sunat_status` {$this->newEnum} NOT NULL DEFAULT 'pendiente'");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `sunat_status` {$this->oldEnum} NOT NULL DEFAULT 'pendiente'");
        }
    }
};

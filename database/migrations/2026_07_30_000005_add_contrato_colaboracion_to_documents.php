<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de Colaboración Empresarial sin contabilidad independiente
 * (RS 048-2026, cac:ContractDocumentReference). Bloque opcional {tipo, numero,
 * descripcion, porcentaje} aplicable a factura, boleta, NC y ND.
 */
return new class extends Migration
{
    private array $tables = ['invoices', 'boletas', 'credit_notes', 'debit_notes'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->json('contrato_colaboracion')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('contrato_colaboracion');
            });
        }
    }
};

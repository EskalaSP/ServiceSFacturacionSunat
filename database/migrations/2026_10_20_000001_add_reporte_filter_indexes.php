<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Índice para los filtros de reporte/ZIP (tenant + ambiente + estado + fecha). Solo Schema: portable MySQL/Postgres. */
return new class extends Migration
{
    private array $tablas = ['invoices', 'boletas', 'credit_notes', 'debit_notes'];

    // Los nombres de índice son únicos por esquema en Postgres/SQLite: se incluye la tabla.
    private function indice(string $tabla): string
    {
        return "idx_{$tabla}_reporte_filtros";
    }

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            if (Schema::hasIndex($tabla, $this->indice($tabla))) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $table->index(['tenant_id', 'ambiente', 'sunat_status', 'fecha_emision'], $this->indice($tabla));
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            if (Schema::hasIndex($tabla, $this->indice($tabla))) {
                Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                    $table->dropIndex($this->indice($tabla));
                });
            }
        }
    }
};

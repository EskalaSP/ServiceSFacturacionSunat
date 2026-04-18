<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sire_comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sire_periodo_id')->constrained('sire_periodos')->cascadeOnDelete();
            $table->foreignId('origen_ticket_id')->nullable()->constrained('sire_tickets')->nullOnDelete();

            // Denormalizado para queries rápidas (evita join cuando filtramos por tenant + periodo)
            $table->string('per_tributario', 6);

            // Fase: propuesta | preliminar | registrado
            $table->string('fase', 20);

            // CAR = Código de Autorización de Registro (identificador único SUNAT)
            $table->string('car_sunat', 30)->nullable();

            // Fechas
            $table->date('fec_emision');
            $table->date('fec_vencimiento')->nullable();

            // Comprobante (Tabla 10 SUNAT)
            $table->string('cod_tipo_cdp', 2);       // 01 factura, 07 NC, 08 ND, etc.
            $table->string('num_serie_cdp', 20);
            $table->string('num_cdp', 20);

            // Proveedor
            $table->string('num_doc_proveedor', 20);
            $table->string('tipo_doc_proveedor', 2);
            $table->string('razon_social_proveedor', 255);

            // Montos
            $table->string('cod_moneda', 3)->default('PEN');
            $table->decimal('mto_bi_gravada', 15, 2)->default(0);
            $table->decimal('mto_igv', 15, 2)->default(0);
            $table->decimal('mto_bi_no_gravada', 15, 2)->default(0);
            $table->decimal('mto_total', 15, 2);
            $table->decimal('tipo_cambio', 10, 4)->nullable();

            // Inconsistencias detectadas por SUNAT (ver 5.36, 5.44)
            $table->string('cod_inconsistencia', 10)->nullable();

            // Si está incluido en el registro o excluido (5.8, 5.37)
            $table->boolean('incluido')->default(true);

            // Línea original del TXT para auditoría y reparseo
            $table->text('raw_line')->nullable();

            $table->timestamps();

            // Un CAR único por tenant+periodo
            $table->unique(['tenant_id', 'per_tributario', 'car_sunat'], 'sire_cp_unique');
            $table->index(['tenant_id', 'per_tributario', 'fase'], 'sire_cp_fase_idx');
            $table->index(['tenant_id', 'num_doc_proveedor'], 'sire_cp_proveedor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sire_comprobantes');
    }
};

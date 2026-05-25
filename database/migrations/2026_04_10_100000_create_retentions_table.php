<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retentions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('serie', 4);             // R001
            $table->unsignedInteger('correlativo');
            $table->string('cod_local', 4)->default('0000');
            $table->dateTime('fecha_emision');

            // Proveedor (a quien se retiene)
            $table->string('proveedor_tipo_doc', 2);
            $table->string('proveedor_num_doc', 15);
            $table->string('proveedor_razon_social', 1500);
            $table->string('proveedor_direccion', 500)->nullable();

            // Régimen retención — Cat 23 (01=3%, 02=6%)
            $table->string('regimen', 2);
            $table->decimal('tasa', 5, 2);           // 3.00 o 6.00

            // Totales
            $table->decimal('imp_retenido', 12, 2)->default(0);
            $table->decimal('imp_pagado', 12, 2)->default(0);

            $table->text('observacion')->nullable();

            // Integración SUNAT
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('hash_cpe')->nullable();
            $table->string('sunat_status', 30)->default('pendiente');
            $table->string('sunat_code', 20)->nullable();
            $table->string('sunat_description', 500)->nullable();
            $table->json('sunat_notes')->nullable();
            $table->string('ticket', 100)->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'serie', 'correlativo']);
            $table->index(['tenant_id', 'sunat_status']);
        });

        Schema::create('retention_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retention_id')->constrained()->cascadeOnDelete();

            // Documento relacionado
            $table->string('tipo_doc', 2);           // 01=Factura
            $table->string('num_doc', 20);            // F001-00000123
            $table->date('fecha_emision_doc');
            $table->decimal('imp_total', 12, 2);      // Importe total del doc
            $table->string('moneda', 3)->default('PEN');

            // Pago(s)
            $table->json('pagos');                     // [{moneda, importe, fecha}]

            // Retención
            $table->date('fecha_retencion');
            $table->decimal('imp_retenido', 12, 2);
            $table->decimal('imp_pagar', 12, 2);       // Neto a pagar

            // Tipo de cambio (si moneda != PEN)
            $table->json('tipo_cambio')->nullable();    // {moneda_ref, moneda_obj, factor, fecha}

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_items');
        Schema::dropIfExists('retentions');
    }
};

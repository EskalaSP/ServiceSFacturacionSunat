<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perceptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('serie', 4);             // P001
            $table->unsignedInteger('correlativo');
            $table->string('cod_local', 4)->default('0000');
            $table->dateTime('fecha_emision');

            // Cliente (a quien se percibe)
            $table->string('cliente_tipo_doc', 2);
            $table->string('cliente_num_doc', 15);
            $table->string('cliente_razon_social', 1500);
            $table->string('cliente_direccion', 500)->nullable();

            // Régimen percepción — Cat 22 (01=2%, 02=1%, 03=0.5%)
            $table->string('regimen', 2);
            $table->decimal('tasa', 5, 2);

            // Totales
            $table->decimal('imp_percibido', 12, 2)->default(0);
            $table->decimal('imp_cobrado', 12, 2)->default(0);

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

        Schema::create('perception_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perception_id')->constrained()->cascadeOnDelete();

            // Documento relacionado
            $table->string('tipo_doc', 2);
            $table->string('num_doc', 20);
            $table->date('fecha_emision_doc');
            $table->decimal('imp_total', 12, 2);
            $table->string('moneda', 3)->default('PEN');

            // Cobro(s)
            $table->json('cobros');                    // [{moneda, importe, fecha}]

            // Percepción
            $table->date('fecha_percepcion');
            $table->decimal('imp_percibido', 12, 2);
            $table->decimal('imp_cobrar', 12, 2);      // Neto a cobrar

            // Tipo de cambio (si moneda != PEN)
            $table->json('tipo_cambio')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perception_items');
        Schema::dropIfExists('perceptions');
    }
};

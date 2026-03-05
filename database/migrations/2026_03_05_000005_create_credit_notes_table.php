<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serie', 4);
            $table->unsignedInteger('correlativo');
            $table->string('cod_local', 4)->default('0000');
            $table->date('fecha_emision');
            $table->string('tipo_moneda', 3)->default('PEN');
            $table->string('client_tipo_doc', 1);
            $table->string('client_num_doc', 15);
            $table->string('client_razon_social');
            $table->string('client_direccion', 500)->nullable();

            // Doc afectado
            $table->string('doc_afectado_tipo', 2);
            $table->string('doc_afectado_serie', 4);
            $table->string('doc_afectado_correlativo', 10);
            $table->string('cod_motivo', 2);
            $table->string('des_motivo');

            // Totales
            $table->decimal('mto_oper_gravadas', 12, 2)->default(0);
            $table->decimal('mto_oper_exoneradas', 12, 2)->default(0);
            $table->decimal('mto_oper_inafectas', 12, 2)->default(0);
            $table->decimal('mto_oper_gratuitas', 12, 2)->default(0);
            $table->decimal('mto_igv', 12, 2)->default(0);
            $table->decimal('mto_isc', 12, 2)->default(0);
            $table->decimal('mto_icbper', 12, 2)->default(0);
            $table->decimal('total_impuestos', 12, 2)->default(0);
            $table->decimal('valor_venta', 12, 2)->default(0);
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('mto_imp_venta', 12, 2)->default(0);
            $table->decimal('total_anticipos', 12, 2)->default(0);
            $table->decimal('total_descuentos', 12, 2)->default(0);

            $table->string('leyenda', 500)->nullable();
            $table->text('observacion')->nullable();

            $table->json('guias')->nullable();

            // Archivos
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('hash_cpe')->nullable();

            // SUNAT
            $table->enum('sunat_status', ['pendiente', 'enviado', 'aceptado', 'rechazado', 'anulado'])->default('pendiente');
            $table->string('sunat_code')->nullable();
            $table->text('sunat_description')->nullable();
            $table->json('sunat_notes')->nullable();
            $table->string('ticket')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'serie', 'correlativo']);
            $table->index(['tenant_id', 'sunat_status']);
            $table->index(['tenant_id', 'fecha_emision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};

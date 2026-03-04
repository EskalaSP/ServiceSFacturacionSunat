<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo_documento', 2);
            $table->string('serie', 4);
            $table->unsignedInteger('correlativo');
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('tipo_operacion', 4)->default('0101');
            $table->string('tipo_moneda', 3)->default('PEN');
            $table->string('forma_pago', 20)->default('Contado');

            // Cliente desnormalizado (para consulta rápida)
            $table->string('client_tipo_doc', 1);
            $table->string('client_num_doc', 15);
            $table->string('client_razon_social');
            $table->string('client_direccion', 500)->nullable();

            // Montos
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

            // Texto y observaciones
            $table->string('leyenda', 500)->nullable();
            $table->text('observacion')->nullable();

            // Documento afectado (NC/ND)
            $table->string('doc_afectado_tipo', 2)->nullable();
            $table->string('doc_afectado_serie', 4)->nullable();
            $table->string('doc_afectado_correlativo', 10)->nullable();
            $table->string('cod_motivo', 2)->nullable();
            $table->string('des_motivo')->nullable();

            // JSON fields para datos complejos
            $table->json('cuotas')->nullable();
            $table->json('detraccion')->nullable();
            $table->json('percepcion')->nullable();
            $table->json('anticipos')->nullable();
            $table->json('descuentos_globales')->nullable();
            $table->json('guias')->nullable();
            $table->json('extras')->nullable();

            // SUNAT
            $table->longText('xml_content')->nullable();
            $table->binary('cdr_content')->nullable();
            $table->string('hash_cpe', 100)->nullable();
            $table->enum('sunat_status', ['pendiente', 'enviado', 'aceptado', 'rechazado', 'anulado'])->default('pendiente');
            $table->string('sunat_code', 10)->nullable();
            $table->text('sunat_description')->nullable();
            $table->json('sunat_notes')->nullable();
            $table->string('ticket', 100)->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'tipo_documento', 'serie', 'correlativo'], 'documents_tenant_tipo_serie_corr_unique');
            $table->index(['tenant_id', 'sunat_status']);
            $table->index(['tenant_id', 'fecha_emision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

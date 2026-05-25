<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serie', 4);
            $table->unsignedInteger('correlativo');
            $table->string('cod_local', 4)->default('0000');
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('tipo_operacion', 4)->default('0101');
            $table->string('tipo_moneda', 3)->default('PEN');
            $table->string('forma_pago', 20)->default('Contado');
            $table->string('client_tipo_doc', 1);
            $table->string('client_num_doc', 15);
            $table->string('client_razon_social');
            $table->string('client_direccion', 500)->nullable();

            // Totales
            $table->decimal('mto_oper_gravadas', 12, 2)->default(0);
            $table->decimal('mto_oper_exoneradas', 12, 2)->default(0);
            $table->decimal('mto_oper_inafectas', 12, 2)->default(0);
            $table->decimal('mto_oper_exportacion', 12, 2)->default(0);
            $table->decimal('mto_oper_gratuitas', 12, 2)->default(0);
            $table->decimal('mto_igv', 12, 2)->default(0);
            $table->decimal('mto_base_ivap', 12, 2)->default(0);
            $table->decimal('mto_ivap', 12, 2)->default(0);
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

            // Campos JSON (específicos de factura)
            $table->json('cuotas')->nullable();
            $table->json('detraccion')->nullable();
            $table->json('percepcion')->nullable();
            $table->json('anticipos')->nullable();
            $table->json('descuentos_globales')->nullable();
            $table->json('guias')->nullable();
            $table->json('extras')->nullable();

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
            $table->index(['tenant_id', 'client_num_doc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->index(); // quotation, sale_note, proforma, etc.
            $table->string('numero', 30); // COT-202603-000001, NV-202603-000001
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('tipo_moneda', 3)->default('PEN');
            $table->string('forma_pago', 20)->default('Contado');

            // Cliente snapshot
            $table->string('client_tipo_doc', 1);
            $table->string('client_num_doc', 15);
            $table->string('client_razon_social');
            $table->string('client_direccion', 500)->nullable();

            // Totales (misma estructura que docs SUNAT para compatibilidad PDF)
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
            $table->decimal('total_descuentos', 12, 2)->default(0);

            $table->text('observacion')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('status', 20)->default('vigente'); // vigente, aceptada, rechazada, vencida, emitida, anulada

            $table->timestamps();
            $table->softDeletes();

            // Índice compuesto para listados ultra-rápidos por tenant+tipo
            $table->unique(['tenant_id', 'type', 'numero']);
            $table->index(['tenant_id', 'type', 'created_at']);
            $table->index(['tenant_id', 'type', 'status']);
            $table->index(['tenant_id', 'client_num_doc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_documents');
    }
};

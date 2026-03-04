<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serie', 4);
            $table->unsignedInteger('correlativo');
            $table->date('fecha_emision');

            // Destinatario
            $table->string('destinatario_tipo_doc', 1);
            $table->string('destinatario_num_doc', 15);
            $table->string('destinatario_razon_social');

            // Envío
            $table->string('cod_traslado', 2);
            $table->string('mod_traslado', 2);
            $table->date('fecha_traslado');
            $table->decimal('peso_total', 12, 3);
            $table->string('und_peso_total', 3)->default('KGM');
            $table->unsignedInteger('num_bultos')->nullable();

            // Direcciones
            $table->string('llegada_ubigeo', 6);
            $table->string('llegada_direccion', 500);
            $table->string('partida_ubigeo', 6);
            $table->string('partida_direccion', 500);

            // JSON fields
            $table->json('transportista')->nullable();
            $table->json('vehiculo')->nullable();
            $table->json('conductor')->nullable();
            $table->json('items');

            // SUNAT
            $table->longText('xml_content')->nullable();
            $table->binary('cdr_content')->nullable();
            $table->string('hash_cpe', 100)->nullable();
            $table->enum('sunat_status', ['pendiente', 'enviado', 'aceptado', 'rechazado'])->default('pendiente');
            $table->string('sunat_code', 10)->nullable();
            $table->text('sunat_description')->nullable();
            $table->string('ticket', 100)->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'serie', 'correlativo'], 'dispatch_guides_tenant_serie_corr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_guides');
    }
};

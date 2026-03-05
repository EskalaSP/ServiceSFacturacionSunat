<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voided_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('identifier'); // RA-20260305-001
            $table->string('correlativo', 5);
            $table->date('fecha_generacion');
            $table->date('fecha_comunicacion');
            $table->unsignedInteger('total_documentos')->default(0);
            $table->json('detalles')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('ticket')->nullable();
            $table->enum('sunat_status', ['pendiente', 'enviado', 'aceptado', 'rechazado'])->default('enviado');
            $table->string('sunat_code')->nullable();
            $table->text('sunat_description')->nullable();
            $table->json('sunat_notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'ticket']);
            $table->index(['tenant_id', 'fecha_comunicacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voided_documents');
    }
};

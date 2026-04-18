<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sire_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Num ticket formato AAAA99999999 (manual 5.2, 5.3, 5.31)
            $table->string('num_ticket', 20);
            $table->string('per_tributario', 6);

            // Proceso (Anexo I)
            $table->string('cod_proceso', 10);
            $table->string('des_proceso', 200)->nullable();

            // Estado del proceso (salida de 5.31)
            $table->string('cod_estado_proceso', 10)->nullable();
            $table->string('des_estado_proceso', 200)->nullable();

            // Archivos
            $table->string('nom_archivo_importacion', 200)->nullable();
            $table->string('nom_archivo_reporte', 200)->nullable();
            $table->string('cod_tipo_archivo_reporte', 10)->nullable();

            // Contadores
            $table->unsignedInteger('cnt_filas_validadas')->nullable();
            $table->unsignedInteger('cnt_cp_informados')->nullable();
            $table->unsignedInteger('cnt_cp_error')->nullable();

            // Ruta local al ZIP descargado (5.32)
            $table->string('archivo_local_path', 500)->nullable();

            // Polling
            $table->unsignedInteger('poll_attempts')->default(0);
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // Payloads para auditoría/debug
            $table->json('sunat_request_payload')->nullable();
            $table->json('sunat_last_response')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'num_ticket'], 'sire_tickets_unique');
            $table->index(['tenant_id', 'cod_estado_proceso'], 'sire_tickets_estado_idx');
            $table->index(['tenant_id', 'per_tributario', 'cod_proceso'], 'sire_tickets_periodo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sire_tickets');
    }
};

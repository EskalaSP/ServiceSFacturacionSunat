<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sire_upload_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sire_ticket_id')->nullable()->constrained()->nullOnDelete();

            $table->string('per_tributario', 6);
            $table->string('cod_proceso', 10);

            // Nombre exacto según convención SUNAT (error 1044 valida por posición)
            $table->string('nom_archivo', 200);
            $table->string('local_path', 500);

            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);

            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->index(['tenant_id', 'per_tributario'], 'sire_uploads_periodo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sire_upload_files');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sire_reconciliation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('per_tributario', 6);

            $table->unsignedInteger('total_sunat')->default(0);
            $table->unsignedInteger('total_local')->default(0);
            $table->unsignedInteger('match_count')->default(0);
            $table->unsignedInteger('only_sunat_count')->default(0);
            $table->unsignedInteger('only_local_count')->default(0);
            $table->unsignedInteger('diff_amount_count')->default(0);
            $table->decimal('diff_total_monto', 15, 2)->default(0);

            $table->json('details')->nullable();

            $table->timestamp('run_at');
            $table->timestamps();

            $table->index(['tenant_id', 'per_tributario'], 'sire_recon_periodo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sire_reconciliation_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('endpoint');
            $table->string('method', 10);
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};

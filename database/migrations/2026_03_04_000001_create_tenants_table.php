<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('ruc', 11)->unique();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('direccion', 500)->nullable();
            $table->string('ubigeo', 6)->nullable();
            $table->text('sol_user');
            $table->text('sol_pass');
            $table->string('certificate_path', 500)->nullable();
            $table->text('certificate_password')->nullable();
            $table->enum('environment', ['beta', 'production'])->default('beta');
            $table->string('webhook_url', 500)->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->string('api_key', 64)->unique();
            $table->string('api_secret', 64);
            $table->enum('plan', ['free', 'pro', 'business'])->default('free');
            $table->unsignedInteger('max_documents_month')->default(20);
            $table->boolean('is_active')->default(true);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

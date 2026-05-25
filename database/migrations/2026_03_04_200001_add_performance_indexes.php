<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenants: búsqueda rápida por API key + activo (ResolveTenant middleware)
        Schema::table('tenants', function (Blueprint $table) {
            $table->index(['api_key', 'is_active']);
        });

        // Documentos: consultas frecuentes por tenant
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->index(['tenant_id', 'tipo_documento']);
                $table->index(['tenant_id', 'created_at']);
                $table->index(['tenant_id', 'client_num_doc']);
            });
        }

        // Guías de remisión: índices faltantes
        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->index(['tenant_id', 'sunat_status']);
            $table->index(['tenant_id', 'fecha_emision']);
        });

        // Registros de API: limpieza por antigüedad
        Schema::table('api_logs', function (Blueprint $table) {
            $table->index('created_at', 'api_logs_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['api_key', 'is_active']);
        });

        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'tipo_documento']);
                $table->dropIndex(['tenant_id', 'created_at']);
                $table->dropIndex(['tenant_id', 'client_num_doc']);
            });
        }

        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'sunat_status']);
            $table->dropIndex(['tenant_id', 'fecha_emision']);
        });

        Schema::table('api_logs', function (Blueprint $table) {
            $table->dropIndex('api_logs_created_at_index');
        });
    }
};

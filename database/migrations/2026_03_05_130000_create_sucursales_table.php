<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('cod_local', 4);
            $table->string('direccion', 500);
            $table->string('ubigeo', 6);
            $table->string('telefono', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->boolean('is_principal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'cod_local'], 'sucursales_tenant_cod_local_unique');
        });

        // Agregar FK de sucursal_id a series
        Schema::table('series', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->nullable()->after('sucursal')->constrained('sucursales')->nullOnDelete();
        });

        // Agregar FK de sucursal_id a las tablas de documentos
        $tables = ['invoices', 'boletas', 'credit_notes', 'debit_notes', 'dispatch_guides'];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('sucursal_id')->nullable()->after('tenant_id')->constrained('sucursales')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = ['invoices', 'boletas', 'credit_notes', 'debit_notes', 'dispatch_guides'];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'sucursal_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('sucursal_id');
                });
            }
        }

        if (Schema::hasColumn('series', 'sucursal_id')) {
            Schema::table('series', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sucursal_id');
            });
        }

        Schema::dropIfExists('sucursales');
    }
};

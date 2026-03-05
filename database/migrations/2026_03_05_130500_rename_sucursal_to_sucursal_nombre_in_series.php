<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->renameColumn('sucursal', 'sucursal_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->renameColumn('sucursal_nombre', 'sucursal');
        });
    }
};

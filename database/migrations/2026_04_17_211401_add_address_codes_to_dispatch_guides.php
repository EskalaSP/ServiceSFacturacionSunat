<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->string('partida_ruc', 11)->nullable()->after('partida_direccion');
            $table->string('partida_cod_local', 4)->nullable()->after('partida_ruc');
            $table->string('llegada_ruc', 11)->nullable()->after('llegada_direccion');
            $table->string('llegada_cod_local', 4)->nullable()->after('llegada_ruc');
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->dropColumn(['partida_ruc', 'partida_cod_local', 'llegada_ruc', 'llegada_cod_local']);
        });
    }
};

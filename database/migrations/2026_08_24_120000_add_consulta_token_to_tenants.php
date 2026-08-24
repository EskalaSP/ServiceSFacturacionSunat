<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Token propio de cada empresa para consultar RUC/DNI en api.json.pe.
            // Se guarda cifrado (cast 'encrypted' en el modelo Tenant).
            $table->text('consulta_token')->nullable()->after('api_secret');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('consulta_token');
        });
    }
};

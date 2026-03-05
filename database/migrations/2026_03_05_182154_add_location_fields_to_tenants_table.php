<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('departamento', 100)->nullable()->after('ubigeo');
            $table->string('provincia', 100)->nullable()->after('departamento');
            $table->string('distrito', 100)->nullable()->after('provincia');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['departamento', 'provincia', 'distrito']);
        });
    }
};

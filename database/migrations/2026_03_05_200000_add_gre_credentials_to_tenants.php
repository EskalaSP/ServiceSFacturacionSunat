<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('client_id', 100)->nullable()->after('sol_pass')
                ->comment('SUNAT GRE OAuth2 client_id para guías de remisión');
            $table->text('client_secret')->nullable()->after('client_id')
                ->comment('SUNAT GRE OAuth2 client_secret');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['client_id', 'client_secret']);
        });
    }
};

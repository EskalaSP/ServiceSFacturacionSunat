<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->string('sunat_code', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Truncar valores que excedan 10 chars antes de achicar la columna.
        \DB::table('dispatch_guides')
            ->whereRaw('LENGTH(sunat_code) > 10')
            ->update(['sunat_code' => \DB::raw('LEFT(sunat_code, 10)')]);

        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->string('sunat_code', 10)->nullable()->change();
        });
    }
};

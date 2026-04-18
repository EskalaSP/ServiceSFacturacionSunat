<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL requiere CASCADE para drop tables con FKs dependientes
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS document_items CASCADE');
            DB::statement('DROP TABLE IF EXISTS documents CASCADE');
        } else {
            Schema::dropIfExists('document_items');
            Schema::dropIfExists('documents');
        }
    }

    public function down(): void
    {
        // No restaurar: los datos ya fueron migrados a las nuevas tablas
    }
};

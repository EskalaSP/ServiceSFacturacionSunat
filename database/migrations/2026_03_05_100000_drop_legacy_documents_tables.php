<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('document_items');
        Schema::dropIfExists('documents');
    }

    public function down(): void
    {
        // No restaurar: los datos ya fueron migrados a las nuevas tablas
    }
};

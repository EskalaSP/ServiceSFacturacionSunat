<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rastro de las consultas de ticket.
 *
 * `updated_at` no sirve como ancla: la verificación solo escribe en la fila
 * cuando SUNAT ya resolvió, así que en un resumen colgado se queda clavado en
 * la fecha de creación y no dice nada sobre cuándo se preguntó por última vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('summaries', function (Blueprint $table) {
            $table->timestamp('last_polled_at')->nullable()->after('ticket');
            $table->unsignedSmallInteger('poll_attempts')->default(0)->after('last_polled_at');

            // El barrido busca resúmenes sin estado final ordenados por antigüedad
            // de consulta; sin esto sería un scan completo cada vez.
            $table->index(['sunat_status', 'last_polled_at'], 'summaries_poll_idx');
        });
    }

    public function down(): void
    {
        Schema::table('summaries', function (Blueprint $table) {
            $table->dropIndex('summaries_poll_idx');
            $table->dropColumn(['last_polled_at', 'poll_attempts']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `identifier` es el número con el que SUNAT conoce al resumen. Dos resúmenes
 * con el mismo identificador son, para SUNAT, el mismo documento enviado dos
 * veces: el segundo queda como duplicado y su ticket nunca resuelve.
 *
 * El índice único es la garantía de que no vuelva a pasar aunque el código
 * falle. La causa (el correlativo se numeraba por fecha de referencia mientras
 * el identificador se armaba con la fecha de envío) va corregida aparte, en
 * SummaryController::generateCorrelativo().
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicados = DB::table('summaries')
            ->select('tenant_id', 'identifier', DB::raw('COUNT(*) AS repeticiones'))
            ->groupBy('tenant_id', 'identifier')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        // Los duplicados que ya existen corresponden a resúmenes REALES enviados
        // a SUNAT. Renombrarlos acá desincronizaría nuestro registro del de
        // SUNAT, y borrarlos perdería la trazabilidad de boletas ya informadas:
        // los dos son peores que quedarse sin índice. Se avisa y se sigue, para
        // que el despliegue no se caiga; hay que resolverlos a mano y volver a
        // correr la migración.
        if ($duplicados->isNotEmpty()) {
            $detalle = $duplicados
                ->map(fn ($d): string => "tenant {$d->tenant_id}: {$d->identifier} ×{$d->repeticiones}")
                ->implode('; ');

            echo "\n  [!] No se creó el índice único de summaries.identifier: hay duplicados previos.\n";
            echo "      {$detalle}\n";
            echo "      Resolvelos (revisando el ticket de cada uno contra SUNAT) y volvé a correr esta migración.\n\n";

            return;
        }

        Schema::table('summaries', function (Blueprint $table) {
            $table->unique(['tenant_id', 'identifier'], 'summaries_tenant_identifier_unique');
        });
    }

    public function down(): void
    {
        Schema::table('summaries', function (Blueprint $table) {
            $table->dropUnique('summaries_tenant_identifier_unique');
        });
    }
};

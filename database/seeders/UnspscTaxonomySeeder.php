<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga la jerarquía UNSPSC (segmentos/familias/clases) desde
 * database/data/unspsc_taxonomy.csv a la tabla unspsc_taxonomy.
 */
class UnspscTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/unspsc_taxonomy.csv');

        if (! is_file($path)) {
            $this->command?->error("No se encontró el CSV en {$path}.");

            return;
        }

        DB::table('unspsc_taxonomy')->truncate();

        $handle = fopen($path, 'r');
        fgetcsv($handle); // cabecera

        $batch = [];
        $total = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 4 || $row[1] === '') {
                continue;
            }

            $batch[] = [
                'nivel' => $row[0],
                'codigo' => $row[1],
                'nombre' => mb_substr((string) $row[2], 0, 255),
                'parent' => $row[3] !== '' ? $row[3] : null,
            ];

            if (count($batch) >= 1000) {
                DB::table('unspsc_taxonomy')->insert($batch);
                $total += count($batch);
                $batch = [];
            }
        }

        if ($batch) {
            DB::table('unspsc_taxonomy')->insert($batch);
            $total += count($batch);
        }

        fclose($handle);

        $this->command?->info("UNSPSC taxonomía: {$total} niveles cargados.");
    }
}

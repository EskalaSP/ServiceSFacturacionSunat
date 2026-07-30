<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga el catálogo Código de Producto SUNAT (UNSPSC v14) desde
 * database/data/unspsc_v14.csv a la tabla unspsc_codes.
 *
 * Idempotente: vacía la tabla y la vuelve a cargar. Inserta por lotes
 * para no agotar memoria (~52k filas).
 */
class UnspscCodeSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/unspsc_v14.csv');

        if (! is_file($path)) {
            $this->command?->error("No se encontró el CSV UNSPSC en {$path}. Ejecute la extracción primero.");

            return;
        }

        DB::table('unspsc_codes')->truncate();

        $handle = fopen($path, 'r');
        fgetcsv($handle); // descarta cabecera

        $batch = [];
        $total = 0;
        $chunk = 2000;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3 || $row[0] === '') {
                continue;
            }

            $batch[] = [
                'codigo' => $row[0],
                'descripcion' => mb_substr((string) $row[1], 0, 255),
                'clase' => $row[2],
            ];

            if (count($batch) >= $chunk) {
                DB::table('unspsc_codes')->insert($batch);
                $total += count($batch);
                $batch = [];
            }
        }

        if ($batch) {
            DB::table('unspsc_codes')->insert($batch);
            $total += count($batch);
        }

        fclose($handle);

        $this->command?->info("UNSPSC v14: {$total} códigos cargados.");
    }
}

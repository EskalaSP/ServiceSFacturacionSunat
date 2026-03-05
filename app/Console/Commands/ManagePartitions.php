<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManagePartitions extends Command
{
    protected $signature = 'partitions:create {--months=3 : Número de meses futuros a crear}';

    protected $description = 'Crea particiones mensuales para los próximos N meses en api_logs';

    // api_logs usa RANGE COLUMNS(created_at) con valores de fecha
    protected array $partitionedTables = [
        'api_logs' => 'date',
    ];

    public function handle(): int
    {
        $months = (int) $this->option('months');

        foreach ($this->partitionedTables as $table => $type) {
            $this->createPartitionsForTable($table, $type, $months);
        }

        return self::SUCCESS;
    }

    private function createPartitionsForTable(string $table, string $type, int $months): void
    {
        $existingPartitions = $this->getExistingPartitions($table);

        if ($existingPartitions->isEmpty()) {
            $this->warn("Tabla '{$table}' no está particionada. Omitiendo.");

            return;
        }

        $this->info("Tabla '{$table}': {$existingPartitions->count()} particiones existentes.");

        $hasFuture = $existingPartitions->contains('PARTITION_NAME', 'p_future');

        for ($i = 0; $i <= $months; $i++) {
            $date = now()->addMonths($i);
            $partitionName = 'p'.$date->format('Ym');
            $nextMonth = $date->copy()->addMonth();

            if ($existingPartitions->contains('PARTITION_NAME', $partitionName)) {
                $this->line("  Partición '{$partitionName}' ya existe.");

                continue;
            }

            if ($type === 'numeric') {
                $boundaryValue = (int) $nextMonth->format('Ym');
                $lessThan = $boundaryValue;
            } else {
                $boundaryValue = "'".$nextMonth->startOfMonth()->format('Y-m-d')."'";
                $lessThan = $boundaryValue;
            }

            if ($hasFuture) {
                DB::statement("ALTER TABLE {$table} REORGANIZE PARTITION p_future INTO (
                    PARTITION {$partitionName} VALUES LESS THAN ({$lessThan}),
                    PARTITION p_future VALUES LESS THAN (MAXVALUE)
                )");
            } else {
                DB::statement("ALTER TABLE {$table} ADD PARTITION (
                    PARTITION {$partitionName} VALUES LESS THAN ({$lessThan})
                )");
            }

            $this->info("  Partición '{$partitionName}' creada (< {$lessThan}).");
        }
    }

    private function getExistingPartitions(string $table)
    {
        return collect(DB::select(
            'SELECT PARTITION_NAME, PARTITION_DESCRIPTION
             FROM INFORMATION_SCHEMA.PARTITIONS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL',
            [config('database.connections.mysql.database'), $table]
        ));
    }
}

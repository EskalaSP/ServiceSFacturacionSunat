<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeOldApiLogs extends Command
{
    protected $signature = 'logs:purge {--days=90 : Eliminar logs con más de N días}';

    protected $description = 'Elimina registros antiguos de api_logs (usa DROP PARTITION si está particionada)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Eliminando api_logs anteriores a {$cutoffDate->toDateString()} ({$days} días)...");

        // Verificar si la tabla está particionada
        $partitions = DB::select(
            'SELECT PARTITION_NAME, PARTITION_DESCRIPTION
             FROM INFORMATION_SCHEMA.PARTITIONS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL',
            [config('database.connections.mysql.database'), 'api_logs']
        );

        if (! empty($partitions)) {
            $dropped = $this->purgeByPartition($partitions, $cutoffDate);
            $this->info("Particiones eliminadas: {$dropped}");
        } else {
            $deleted = DB::table('api_logs')
                ->where('created_at', '<', $cutoffDate)
                ->delete();
            $this->info("Registros eliminados: {$deleted}");
        }

        return self::SUCCESS;
    }

    private function purgeByPartition(array $partitions, $cutoffDate): int
    {
        $cutoffStr = $cutoffDate->startOfMonth()->format('Y-m-d');
        $dropped = 0;

        foreach ($partitions as $partition) {
            $name = $partition->PARTITION_NAME;

            if ($name === 'p_future') {
                continue;
            }

            // PARTITION_DESCRIPTION contiene el valor LESS THAN (ej: '2026-02-01' para RANGE COLUMNS)
            $boundary = trim($partition->PARTITION_DESCRIPTION, "'");

            // Si el boundary es menor o igual a la fecha de corte, la partición entera es vieja
            if ($boundary <= $cutoffStr) {
                DB::statement("ALTER TABLE api_logs DROP PARTITION {$name}");
                $this->line("  Partición '{$name}' eliminada (boundary: {$boundary}).");
                $dropped++;
            }
        }

        return $dropped;
    }
}

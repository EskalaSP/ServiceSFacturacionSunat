<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sunat\SunatCircuitBreaker;
use Illuminate\Console\Command;

class SunatCircuitCommand extends Command
{
    protected $signature = 'sunat:circuit
        {action : status|open|close}
        {--entorno=all : beta, produccion, o all}';

    protected $description = 'Gestiona el circuit breaker de SUNAT (status / forzar open / forzar close)';

    public function handle(): int
    {
        $action = $this->argument('action');
        $envOpt = $this->option('entorno');

        $envs = $envOpt === 'all'
            ? ['beta', 'produccion']
            : [$envOpt];

        $cb = new SunatCircuitBreaker();

        foreach ($envs as $env) {
            $endpoint = config("facturacion.sunat.{$env}.fe");

            if (! $endpoint) {
                $this->warn("Entorno [{$env}] no configurado, omitido.");
                continue;
            }

            match ($action) {
                'status' => $this->showStatus($cb, $env, $endpoint),
                'close'  => $this->forceClose($cb, $env, $endpoint),
                'open'   => $this->forceOpen($cb, $env, $endpoint),
                default  => $this->error("Acción desconocida: {$action}"),
            };
        }

        return self::SUCCESS;
    }

    private function showStatus(SunatCircuitBreaker $cb, string $env, string $endpoint): void
    {
        $stats = $cb->getStats($endpoint);

        $color = match ($stats['state']) {
            'closed'    => 'green',
            'half_open' => 'yellow',
            'open'      => 'red',
            default     => 'white',
        };

        $this->line("  <fg={$color}>[{$env}]</> Estado: <fg={$color};options=bold>{$stats['state']}</> | Fallas recientes: {$stats['failures']} | Endpoint: {$endpoint}");
    }

    private function forceClose(SunatCircuitBreaker $cb, string $env, string $endpoint): void
    {
        $cb->forceClose($endpoint);
        $this->info("[{$env}] Circuit breaker CERRADO (CLOSED) manualmente. Los jobs reanudarán.");
    }

    private function forceOpen(SunatCircuitBreaker $cb, string $env, string $endpoint): void
    {
        // Simula 5 fallas para abrir el circuito
        for ($i = 0; $i < 5; $i++) {
            $cb->recordFailure($endpoint);
        }
        $this->warn("[{$env}] Circuit breaker ABIERTO (OPEN) manualmente. Jobs pausados.");
    }
}

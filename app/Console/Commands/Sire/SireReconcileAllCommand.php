<?php

namespace App\Console\Commands\Sire;

use App\Models\Tenant;
use App\Sire\Jobs\ReconcilePeriodoJob;
use App\Sire\Models\SireComprobante;
use Illuminate\Console\Command;

/**
 * Encola ReconcilePeriodoJob para cada tenant con SIRE activado,
 * tomando el periodo más reciente donde existan comprobantes.
 *
 * Pensado para correr diario en el scheduler (03:00 AM).
 */
class SireReconcileAllCommand extends Command
{
    protected $signature = 'sire:reconcile-all
        {--periodo= : forzar un periodo específico (yyyymm)}
        {--tenant= : solo un tenant específico (id)}';

    protected $description = 'Encola reconciliación SIRE para todos los tenants activos.';

    public function handle(): int
    {
        $periodoForzado = $this->option('periodo');
        $tenantIdOpt    = $this->option('tenant');

        $tenants = Tenant::query()
            ->where('sire_enabled', true)
            ->where('is_active', true)
            ->when($tenantIdOpt, fn ($q) => $q->where('id', $tenantIdOpt))
            ->get(['id']);

        if ($tenants->isEmpty()) {
            $this->info('No hay tenants con SIRE activo.');
            return self::SUCCESS;
        }

        $encolados = 0;
        foreach ($tenants as $t) {
            $periodo = $periodoForzado ?: $this->periodoMasReciente($t->id);

            if (! $periodo) {
                continue;
            }

            ReconcilePeriodoJob::dispatch($t->id, $periodo);
            $encolados++;
        }

        $this->info("Encolados {$encolados} ReconcilePeriodoJob.");
        return self::SUCCESS;
    }

    private function periodoMasReciente(int $tenantId): ?string
    {
        return SireComprobante::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('per_tributario')
            ->value('per_tributario');
    }
}

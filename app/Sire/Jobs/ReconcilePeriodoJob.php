<?php

namespace App\Sire\Jobs;

use App\Models\Tenant;
use App\Sire\Services\Reconciliation\ReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Ejecuta reconciliación de un tenant+periodo en background.
 * Útil para el cron diario y para ejecuciones manuales desde UI.
 */
class ReconcilePeriodoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $perTributario,
    ) {
        $this->onQueue(config('sire.queues.process'));
    }

    public function handle(ReconciliationService $service): void
    {
        $tenant = Tenant::find($this->tenantId);

        if (! $tenant || ! $tenant->sire_enabled) {
            return;
        }

        try {
            $report = $service->run($tenant, $this->perTributario);

            Log::channel('stack')->info('[SIRE][Reconcile] OK', array_merge(
                ['tenant_id' => $tenant->id],
                $report->summary(),
            ));

            if ($this->requiereNotificacion($report)) {
                NotifyWebhookJob::dispatch(
                    tenantId: $tenant->id,
                    event: 'reconciliation.completed',
                    payload: $report->summary(),
                );
            }
        } catch (\Throwable $e) {
            Log::channel('stack')->error('[SIRE][Reconcile] falló', [
                'tenant_id' => $this->tenantId,
                'periodo'   => $this->perTributario,
                'error'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Disparamos webhook si hay algo que requiera atención:
     * - Duplicados detectados
     * - Outliers de monto
     * - Inconsistencias SUNAT
     */
    private function requiereNotificacion(\App\Sire\Services\Reconciliation\ReconciliationReport $report): bool
    {
        $a = $report->alertas;
        return ! empty($a['duplicados'] ?? [])
            || ! empty($a['outliers_monto'] ?? [])
            || ($a['con_inconsistencia_sunat'] ?? 0) > 0;
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Sire;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Sire\Jobs\ReconcilePeriodoJob;
use App\Sire\Models\SireReconciliationLog;
use App\Sire\Services\Reconciliation\ReconciliationService;
use App\Sire\Support\PeriodoTributario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SireReconciliationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReconciliationService $service,
    ) {}

    /**
     * GET /v1/sire/rce/{periodo}/reconciliar
     *
     * Corre reconciliación síncrona y devuelve el reporte completo.
     * Útil para periodos pequeños (<1000 CPs). Para periodos grandes usar async.
     */
    public function reconciliar(Request $request, string $periodo): JsonResponse
    {
        try {
            $per = PeriodoTributario::fromString($periodo);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $report = $this->service->run($request->get('tenant'), $per->toString());

        return $this->success($report->toArray());
    }

    /**
     * POST /v1/sire/rce/{periodo}/reconciliar-async
     *
     * Encola la reconciliación para ejecución en background.
     * Devuelve 202 + id del log creado después.
     */
    public function reconciliarAsync(Request $request, string $periodo): JsonResponse
    {
        try {
            $per = PeriodoTributario::fromString($periodo);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $tenant = $request->get('tenant');

        ReconcilePeriodoJob::dispatch($tenant->id, $per->toString());

        return $this->success([
            'mensaje'        => 'Reconciliación encolada. Consulta el historial con GET /v1/sire/rce/{periodo}/reconciliaciones',
            'per_tributario' => $per->toString(),
        ], 'Encolado', 202);
    }

    /**
     * GET /v1/sire/rce/{periodo}/reconciliaciones
     *
     * Historial de reconciliaciones ejecutadas.
     */
    public function historial(Request $request, string $periodo): JsonResponse
    {
        $tenant = $request->get('tenant');

        $logs = SireReconciliationLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('per_tributario', $periodo)
            ->orderByDesc('run_at')
            ->limit(20)
            ->get();

        return $this->success(
            $logs->map(fn (SireReconciliationLog $log) => [
                'id'                 => $log->id,
                'per_tributario'     => $log->per_tributario,
                'total_sunat'        => $log->total_sunat,
                'match_count'        => $log->match_count,
                'only_sunat_count'   => $log->only_sunat_count,
                'diff_amount_count'  => $log->diff_amount_count,
                'run_at'             => $log->run_at,
            ]),
        );
    }

    /**
     * GET /v1/sire/rce/reconciliaciones/{id}
     *
     * Detalle completo de una reconciliación guardada.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');

        $log = SireReconciliationLog::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);

        return $this->success([
            'id'             => $log->id,
            'per_tributario' => $log->per_tributario,
            'run_at'         => $log->run_at,
            'details'        => $log->details,
        ]);
    }
}

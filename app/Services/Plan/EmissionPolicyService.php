<?php

namespace App\Services\Plan;

use App\Models\Tenant;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Punto ÚNICO de decisión sobre si una empresa puede emitir un comprobante.
 *
 * Resuelve automáticamente qué configuración manda, respetando esta prioridad
 * (de mayor a menor):
 *
 *   1. Switch GLOBAL de emisión ilimitada  → todas las empresas ilimitadas.
 *   2. Entorno 'beta'                        → ilimitado (pruebas; comportamiento previo).
 *   3. Modo de emisión de la EMPRESA         → 'unlimited' emite sin límites.
 *   4. PLAN / suscripción                    → aplica vencimiento y cupo mensual.
 *
 * Devuelve un EmissionDecision; NO produce respuestas HTTP (eso es del middleware).
 */
class EmissionPolicyService
{
    /** Config por categoría: [clave de límite en el plan, valor por defecto]. */
    private const CATEGORIAS = [
        'sunat'    => ['documents_month', 30],
        'internal' => ['internal_documents_month', 20],
    ];

    public function __construct(
        private PlanService $planService,
        private SettingsService $settings,
    ) {}

    public function evaluate(Tenant $tenant, string $category = 'sunat'): EmissionDecision
    {
        // 1. Switch global: si está activo, nadie tiene límites.
        if ($this->settings->bool(SettingsService::EMISION_ILIMITADA_GLOBAL)) {
            return EmissionDecision::unlimited('global', $category);
        }

        // 2. Entorno beta: se preserva el bypass histórico para pruebas.
        if ($tenant->environment === 'beta') {
            return EmissionDecision::unlimited('beta', $category);
        }

        // 3. Empresa marcada como ilimitada (Escenario 2, opción sin plan).
        if ($tenant->hasUnlimitedEmission()) {
            return EmissionDecision::unlimited('empresa', $category);
        }

        // 4. Modo plan: vencimiento + cupo del plan/suscripción.
        return $this->evaluarPlan($tenant, $category);
    }

    private function evaluarPlan(Tenant $tenant, string $category): EmissionDecision
    {
        $plan = $this->planService->getActivePlan($tenant);

        // Suscripción vencida por fecha (aunque su estado siga en 'active').
        $suscripcion = $tenant->activeSubscription;
        if ($suscripcion && $suscripcion->current_period_end && $suscripcion->current_period_end->isPast()) {
            return EmissionDecision::subscriptionExpired($plan, $category);
        }

        [$limitKey, $default] = self::CATEGORIAS[$category] ?? self::CATEGORIAS['sunat'];
        $max = (int) $plan->getLimit($limitKey, $default);

        // Plan ilimitado (bandera explícita o sentinel -1 en el límite de la categoría).
        if ($plan->is_unlimited || $max === -1) {
            return EmissionDecision::unlimited('plan', $category);
        }

        $used = $this->contarDocumentos($tenant, $category);

        if ($used >= $max) {
            return EmissionDecision::limitReached($plan, $category, $max, $used);
        }

        return EmissionDecision::withinLimit($plan, $category, $max, $used);
    }

    /**
     * Cuenta los comprobantes emitidos en el mes en curso para la categoría.
     * Cacheado 300s por tenant/mes (misma clave que usaba el middleware).
     */
    private function contarDocumentos(Tenant $tenant, string $category): int
    {
        $mes = now()->format('Y-m');
        $monthStart = now()->startOfMonth()->toDateString() . ' 00:00:00';
        $monthEnd = now()->endOfMonth()->toDateString() . ' 23:59:59';

        if ($category === 'internal') {
            return Cache::remember(
                "tenant:{$tenant->id}:internal_count:{$mes}",
                300,
                fn () => (int) DB::table('internal_documents')
                    ->where('tenant_id', $tenant->id)
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count(),
            );
        }

        return Cache::remember(
            "tenant:{$tenant->id}:sunat_count:{$mes}",
            300,
            function () use ($tenant, $monthStart, $monthEnd) {
                $bind = [$tenant->id, $monthStart, $monthEnd];
                $bindings = array_merge($bind, $bind, $bind, $bind, $bind);

                return (int) DB::selectOne("
                    SELECT COALESCE(SUM(cnt), 0) AS total FROM (
                        SELECT COUNT(*) AS cnt FROM invoices WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
                        UNION ALL
                        SELECT COUNT(*) FROM boletas WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
                        UNION ALL
                        SELECT COUNT(*) FROM credit_notes WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
                        UNION ALL
                        SELECT COUNT(*) FROM debit_notes WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
                        UNION ALL
                        SELECT COUNT(*) FROM dispatch_guides WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
                    ) AS counts
                ", $bindings)->total;
            },
        );
    }
}

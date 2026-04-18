<?php

namespace App\Sire\Services\Reconciliation;

/**
 * Estructura del reporte de reconciliación para un periodo SIRE RCE.
 *
 * Agrupa 5 dimensiones de análisis:
 *   1. Totales (cantidad y montos agregados)
 *   2. Por proveedor (top + estadísticas)
 *   3. Por tipo de comprobante
 *   4. Cruce con BD local del tenant (clients + invoices/boletas emitidas)
 *   5. Alertas (duplicados, outliers, inconsistencias)
 */
class ReconciliationReport
{
    public function __construct(
        public int $tenantId,
        public string $perTributario,
        public array $totales = [],
        public array $porProveedor = [],
        public array $porTipo = [],
        public array $cruceLocal = [],
        public array $alertas = [],
        public ?string $runAt = null,
    ) {
        $this->runAt ??= now()->toIso8601String();
    }

    public function toArray(): array
    {
        return [
            'tenant_id'      => $this->tenantId,
            'per_tributario' => $this->perTributario,
            'run_at'         => $this->runAt,
            'totales'        => $this->totales,
            'por_proveedor'  => $this->porProveedor,
            'por_tipo'       => $this->porTipo,
            'cruce_local'    => $this->cruceLocal,
            'alertas'        => $this->alertas,
        ];
    }

    /**
     * Resumen compacto para logs / webhooks / listados.
     */
    public function summary(): array
    {
        return [
            'per_tributario'    => $this->perTributario,
            'total_sunat'       => $this->totales['total_comprobantes'] ?? 0,
            'suma_total'        => $this->totales['suma_total'] ?? 0,
            'proveedores_unicos'=> count($this->porProveedor),
            'duplicados'        => count($this->alertas['duplicados'] ?? []),
            'sin_contraparte'   => $this->cruceLocal['proveedores_no_en_clients'] ?? 0,
        ];
    }
}

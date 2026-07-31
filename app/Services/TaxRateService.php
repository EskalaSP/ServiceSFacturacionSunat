<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;

/**
 * Resuelve la tasa de IGV vigente considerando:
 *  - tip_afe_igv del ítem (gravado estándar, IVAP, exonerado, etc.)
 *  - porcentaje_igv explícito en el ítem (tiene máxima prioridad)
 *  - Régimen tributario del tenant (tenants.tax_regime)
 *  - Fecha de emisión (para el schedule MYPE Restaurantes/Hoteles)
 *
 * Ley 31556 (Restaurantes/Hoteles/Alojamientos Turísticos MYPE):
 *   2022-2024 (original): 10%   (8% IGV + 2% IPM)
 *   2026                : 10.5% (8% IGV + 2.5% IPM)
 *   2027                : 15%   (12% IGV + 3% IPM)
 *   2028                : 18%   (14.5% IGV + 3.5% IPM)
 *   2029+               : 18%   (régimen general)
 *
 * IMPORTANTE: Para SUNAT, en el XML UBL 2.1 se emite la tasa combinada
 * en <cbc:Percent> (ej: 10.5) bajo TaxTypeCode=1000 (IGV). No se declara
 * IPM como tributo separado — SUNAT siempre manejó el IGV+IPM combinado.
 */
class TaxRateService
{
    public const REGIMEN_GENERAL = 'general';

    /** Nuevo RUS. Antes se llamaba 'nrus' en este campo. */
    public const REGIMEN_RUS = 'rus';

    /**
     * Tasas combinadas de la Ley 31556 (restaurantes y hoteles), por año.
     *
     * Ya NO se aplican automáticamente por régimen: "restaurantes" nunca fue un
     * régimen tributario, era un MYPE con una tasa especial, y tenerlo en la
     * misma lista obligaba al emisor a elegir entre declarar su régimen real o
     * su tasa correcta. Ahora la tasa va en `igv_rate_override`.
     *
     * El schedule queda como referencia para sugerir el valor en el panel y
     * para la migración que rellenó el override de los que ya estaban.
     */
    public const LEY_31556 = [
        2022 => 10.0,
        2023 => 10.0,
        2024 => 10.0,
        2025 => 10.0, // el régimen original expiró fines de 2024; 2025 queda en 10 por si hay ampliación
        2026 => 10.5,
        2027 => 15.0,
        2028 => 18.0,
        2029 => 18.0,
    ];

    /**
     * Tasa de IGV por defecto para gravado estándar (tip_afe_igv = 10).
     *
     * Precedencia:
     *  1. $tenant->igv_rate_override (si está definido)
     *  2. Schedule por régimen + año de $fechaEmision
     *  3. 18.0 (régimen general)
     */
    public function defaultIgvRate(?Tenant $tenant, ?string $fechaEmision = null): float
    {
        if ($tenant && $tenant->igv_rate_override !== null) {
            return (float) $tenant->igv_rate_override;
        }

        $regimen = $tenant->tax_regime ?? self::REGIMEN_GENERAL;

        // RUS: siempre 0% (inafecto — el contribuyente paga cuota fija, no IGV por operación)
        if ($regimen === self::REGIMEN_RUS) {
            return 0.0;
        }

        // RER, MYPE y General tributan al 18%. Cualquier tasa distinta —la de
        // restaurantes y hoteles de la Ley 31556, por ejemplo— se declara en
        // igv_rate_override, que ya se resolvió arriba.
        return 18.0;
    }

    /**
     * Tasa que sugiere la Ley 31556 para el año dado, o null fuera de vigencia.
     *
     * No se aplica sola: sirve para que el panel proponga el valor al cargar el
     * override de un restaurante u hotel acogido.
     */
    public function tasaLey31556(?string $fechaEmision = null): ?float
    {
        return self::LEY_31556[$this->extractYear($fechaEmision)] ?? null;
    }

    /**
     * Indica si el tenant está en el Nuevo RUS.
     */
    public function isNrus(?Tenant $tenant): bool
    {
        return $tenant && $tenant->tax_regime === self::REGIMEN_RUS;
    }

    /**
     * tip_afe_igv por defecto para un tenant: '30' (Inafecto) si es RUS, '10' (Gravado) en otros casos.
     */
    public function defaultTipAfeIgv(?Tenant $tenant): string
    {
        return $this->isNrus($tenant) ? '30' : '10';
    }

    /**
     * Calcula la tasa efectiva para un ítem específico.
     *
     * Prioridad:
     *  1. $item['porcentaje_igv'] (cliente lo envía explícito)
     *  2. Si tip_afe_igv = '17' (IVAP) → 4%
     *  3. Si tip_afe_igv = '10' (gravado) → defaultIgvRate($tenant, $fecha)
     *  4. 0 (exonerado, inafecto, exportación, gratuito)
     */
    public function rateForItem(array $item, ?Tenant $tenant = null, ?string $fechaEmision = null): float
    {
        if (isset($item['porcentaje_igv'])) {
            return (float) $item['porcentaje_igv'];
        }

        // Para NRUS, si el item no especifica tip_afe_igv, default a '30' (Inafecto) → 0%
        $tipAfeIgv = $item['tip_afe_igv'] ?? $this->defaultTipAfeIgv($tenant);

        if ($tipAfeIgv === '17') {
            return 4.0;
        }

        if ($tipAfeIgv === '10') {
            return $this->defaultIgvRate($tenant, $fechaEmision);
        }

        // Exonerado/Inafecto/Exportación/Gratuitos: sin IGV o se resuelve en el builder
        return 0.0;
    }

    /**
     * Devuelve la tasa actual para mostrar al usuario (Settings/dashboard).
     */
    public function currentRateFor(Tenant $tenant): float
    {
        return $this->defaultIgvRate($tenant, now()->toDateString());
    }

    private function extractYear(?string $fechaEmision): int
    {
        if (! $fechaEmision) {
            return (int) now()->format('Y');
        }
        try {
            return (int) (new \DateTimeImmutable($fechaEmision))->format('Y');
        } catch (\Throwable) {
            return (int) now()->format('Y');
        }
    }
}

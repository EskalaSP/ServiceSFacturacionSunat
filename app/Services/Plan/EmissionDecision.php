<?php

namespace App\Services\Plan;

use App\Models\Plan;

/**
 * Resultado inmutable de evaluar si una empresa puede emitir un comprobante.
 * Lo produce EmissionPolicyService y lo consume el middleware (que traduce
 * la decisión a una respuesta HTTP). Mantener la capa de decisión libre de
 * detalles HTTP hace que la misma lógica sea reutilizable (API, panel, jobs).
 */
final class EmissionDecision
{
    public const CODE_LIMIT_REACHED = 'limite_alcanzado';
    public const CODE_SUBSCRIPTION_EXPIRED = 'suscripcion_vencida';

    /**
     * @param string      $source   De dónde salió la decisión: global|beta|empresa|plan
     * @param string|null $code     Motivo del bloqueo (cuando allowed=false)
     */
    private function __construct(
        public readonly bool $allowed,
        public readonly bool $unlimited,
        public readonly string $source,
        public readonly string $category = 'sunat',
        public readonly ?Plan $plan = null,
        public readonly ?int $limit = null,
        public readonly ?int $used = null,
        public readonly ?string $code = null,
    ) {}

    public static function unlimited(string $source, string $category = 'sunat'): self
    {
        return new self(allowed: true, unlimited: true, source: $source, category: $category);
    }

    public static function withinLimit(Plan $plan, string $category, int $limit, int $used): self
    {
        return new self(
            allowed: true,
            unlimited: false,
            source: 'plan',
            category: $category,
            plan: $plan,
            limit: $limit,
            used: $used,
        );
    }

    public static function limitReached(Plan $plan, string $category, int $limit, int $used): self
    {
        return new self(
            allowed: false,
            unlimited: false,
            source: 'plan',
            category: $category,
            plan: $plan,
            limit: $limit,
            used: $used,
            code: self::CODE_LIMIT_REACHED,
        );
    }

    public static function subscriptionExpired(Plan $plan, string $category): self
    {
        return new self(
            allowed: false,
            unlimited: false,
            source: 'plan',
            category: $category,
            plan: $plan,
            code: self::CODE_SUBSCRIPTION_EXPIRED,
        );
    }
}

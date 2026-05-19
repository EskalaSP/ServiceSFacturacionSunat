<?php

declare(strict_types=1);

namespace App\Services\Sunat;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Circuit breaker para SUNAT.
 *
 * Estados:
 *   CLOSED    → normal, todos los envíos pasan.
 *   OPEN      → SUNAT caído, bloquea envíos. Se reabre después del TTL.
 *   HALF_OPEN → TTL de OPEN expiró, deja pasar 1 request de prueba.
 *
 * Transiciones:
 *   CLOSED → OPEN:      5 SoapFaults en 60 s
 *   OPEN → HALF_OPEN:   TTL de 5 min expirado (automático por Cache TTL)
 *   HALF_OPEN → CLOSED: prueba exitosa
 *   HALF_OPEN → OPEN:   prueba fallida (TTL x2)
 */
class SunatCircuitBreaker
{
    const STATE_CLOSED    = 'closed';
    const STATE_OPEN      = 'open';
    const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private int $failureThreshold     = 5,
        private int $failureWindowSeconds = 60,
        private int $openTtlSeconds       = 300,
        private int $halfOpenTtlSeconds   = 30,
    ) {}

    /**
     * ¿Se puede enviar a SUNAT ahora?
     * En HALF_OPEN solo deja pasar al primer job (probe atómico).
     */
    public function isAvailable(string $endpoint): bool
    {
        return match ($this->getState($endpoint)) {
            self::STATE_CLOSED    => true,
            self::STATE_OPEN      => false,
            self::STATE_HALF_OPEN => Cache::add(
                $this->key($endpoint, 'probe'),
                1,
                $this->halfOpenTtlSeconds
            ),
        };
    }

    public function recordSuccess(string $endpoint): void
    {
        $wasOpen = $this->getState($endpoint) !== self::STATE_CLOSED;

        Cache::forget($this->key($endpoint, 'open_state'));
        Cache::forget($this->key($endpoint, 'was_open'));
        Cache::forget($this->key($endpoint, 'failures'));
        Cache::forget($this->key($endpoint, 'probe'));

        if ($wasOpen) {
            Log::info("SunatCircuitBreaker: [{$endpoint}] recuperado → CLOSED");
        }
    }

    public function recordFailure(string $endpoint): void
    {
        $failures = Cache::increment($this->key($endpoint, 'failures'));

        if ($failures === 1) {
            // Primera falla: inicia ventana de tiempo
            Cache::put($this->key($endpoint, 'failures'), 1, $this->failureWindowSeconds);
        }

        if ($failures >= $this->failureThreshold) {
            $this->transition(self::STATE_OPEN, $endpoint, $this->openTtlSeconds);
        }
    }

    public function recordProbeFailure(string $endpoint): void
    {
        Cache::forget($this->key($endpoint, 'probe'));
        $this->transition(self::STATE_OPEN, $endpoint, $this->openTtlSeconds * 2);
    }

    public function getState(string $endpoint): string
    {
        if (Cache::has($this->key($endpoint, 'open_state'))) {
            return self::STATE_OPEN;
        }
        if (Cache::has($this->key($endpoint, 'was_open'))) {
            return self::STATE_HALF_OPEN;
        }
        return self::STATE_CLOSED;
    }

    public function getStats(string $endpoint): array
    {
        return [
            'endpoint' => $endpoint,
            'state'    => $this->getState($endpoint),
            'failures' => (int) Cache::get($this->key($endpoint, 'failures'), 0),
        ];
    }

    /** Cierra el circuit breaker manualmente (para ops/incidentes). */
    public function forceClose(string $endpoint): void
    {
        $this->recordSuccess($endpoint);
        Log::info("SunatCircuitBreaker: [{$endpoint}] CLOSED por fuerza (manual)");
    }

    private function transition(string $state, string $endpoint, int $ttl): void
    {
        $current = $this->getState($endpoint);
        if ($current === $state) {
            return;
        }

        Log::warning("SunatCircuitBreaker: [{$endpoint}] {$current} → {$state} (TTL: {$ttl}s)");

        Cache::put($this->key($endpoint, 'open_state'), 1, $ttl);
        // 'was_open' vive más tiempo para que el estado HALF_OPEN persista después del TTL de OPEN
        Cache::put($this->key($endpoint, 'was_open'), 1, $ttl + $this->halfOpenTtlSeconds);
    }

    private function key(string $endpoint, string $suffix): string
    {
        return 'sunat_cb:' . substr(md5($endpoint), 0, 8) . ':' . $suffix;
    }
}

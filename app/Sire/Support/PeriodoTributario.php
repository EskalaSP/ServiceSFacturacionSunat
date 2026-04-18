<?php

namespace App\Sire\Support;

use InvalidArgumentException;

/**
 * Validador y utilidades para perTributario (formato yyyymm).
 * Centraliza las validaciones 1005, 1006, 1007, 1014 del manual SUNAT.
 */
class PeriodoTributario
{
    public function __construct(
        public readonly int $anio,
        public readonly int $mes,
    ) {
        if ($mes < 1 || $mes > 12) {
            throw new InvalidArgumentException("Mes inválido: {$mes}");
        }
        if ($anio < 2000 || $anio > 2100) {
            throw new InvalidArgumentException("Año inválido: {$anio}");
        }
    }

    public static function fromString(string $periodo): self
    {
        if (! preg_match('/^\d{6}$/', $periodo)) {
            throw new InvalidArgumentException("El periodo debe tener formato yyyymm: {$periodo}");
        }

        return new self(
            anio: (int) substr($periodo, 0, 4),
            mes: (int) substr($periodo, 4, 2),
        );
    }

    public function toString(): string
    {
        return sprintf('%04d%02d', $this->anio, $this->mes);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function isFuture(): bool
    {
        $now = now();

        return $this->anio > $now->year
            || ($this->anio === $now->year && $this->mes > $now->month);
    }

    public function toYyyyMmDd(string $day = '01'): string
    {
        return sprintf('%04d-%02d-%s', $this->anio, $this->mes, $day);
    }
}

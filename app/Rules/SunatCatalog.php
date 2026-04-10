<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que un valor exista en un catálogo SUNAT (Formato 1.3.4).
 *
 * Uso: new SunatCatalog('51')   → valida contra Catálogo 51 (tipo operación)
 *      new SunatCatalog('09')   → valida contra Catálogo 09 (tipo nota crédito)
 */
class SunatCatalog implements ValidationRule
{
    /** @var array<string, mixed> */
    private array $catalog;

    private string $catalogNumber;

    public function __construct(string $catalogNumber)
    {
        $this->catalogNumber = $catalogNumber;
        $this->catalog = config("sunat_catalogs.{$catalogNumber}", []);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail("El campo :attribute debe ser un código válido del Catálogo SUNAT N° {$this->catalogNumber}.");

            return;
        }

        $key = (string) $value;

        if (! array_key_exists($key, $this->catalog)) {
            $validCodes = implode(', ', array_keys($this->catalog));
            $fail("El campo :attribute contiene un código inválido '{$key}'. Catálogo SUNAT N° {$this->catalogNumber}. Códigos válidos: {$validCodes}.");
        }
    }

    /**
     * Obtiene la descripción de un código del catálogo.
     */
    public static function description(string $catalogNumber, string $code): ?string
    {
        $catalog = config("sunat_catalogs.{$catalogNumber}", []);
        $entry = $catalog[$code] ?? null;

        if ($entry === null) {
            return null;
        }

        if (is_string($entry)) {
            return $entry;
        }

        return $entry['desc'] ?? $entry['name'] ?? null;
    }

    /**
     * Obtiene todos los códigos válidos de un catálogo.
     *
     * @return array<string>
     */
    public static function codes(string $catalogNumber): array
    {
        return array_keys(config("sunat_catalogs.{$catalogNumber}", []));
    }

    /**
     * Obtiene la tasa asociada a un código (Cat 22, 23, 54).
     */
    public static function rate(string $catalogNumber, string $code): ?float
    {
        $entry = config("sunat_catalogs.{$catalogNumber}.{$code}");

        if (is_array($entry) && isset($entry['tasa'])) {
            return (float) $entry['tasa'];
        }

        return null;
    }

    /**
     * Obtiene el factor ICBPER para un año dado.
     */
    public static function icbperFactor(?int $year = null): float
    {
        $year ??= (int) date('Y');
        $factors = config('sunat_catalogs.icbper', []);

        return (float) ($factors[$year] ?? $factors[max(array_keys($factors))] ?? 0.50);
    }
}

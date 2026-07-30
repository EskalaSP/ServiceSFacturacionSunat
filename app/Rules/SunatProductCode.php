<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\UnspscCode;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida el Código de Producto SUNAT (UNSPSC v14 — Catálogo N.° 25),
 * que va en cbc:ItemClassificationCode de cada ítem.
 *
 * Reglas (Reglas de validación SUNAT, hoja Factura2_0, fila 154):
 *  - Debe tener 8 dígitos numéricos.
 *  - No puede terminar en '0000' → debe llegar mínimo a nivel clase UNSPSC (OBS-4337).
 *  - Debe existir en el catálogo (OBS-4332 hasta 31/12/2026; ERR-3496 desde 01/01/2027).
 *
 * El campo es opcional en general; la obligatoriedad depende del padrón del
 * emisor (ind_padron = '12') y se controla en el FormRequest, no aquí.
 */
class SunatProductCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $codigo = (string) $value;

        if (! preg_match('/^\d{8}$/', $codigo)) {
            $fail('El Código de producto SUNAT debe tener 8 dígitos numéricos (Catálogo N.° 25, UNSPSC).');

            return;
        }

        if (str_ends_with($codigo, '0000')) {
            $fail('El Código de producto SUNAT debe especificarse como mínimo a nivel de clase UNSPSC (no puede terminar en 0000).');

            return;
        }

        if (! UnspscCode::existe($codigo)) {
            $fail("El Código de producto SUNAT '{$codigo}' no es válido: no existe en el Catálogo N.° 25 (UNSPSC v14).");
        }
    }
}

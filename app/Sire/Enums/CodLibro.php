<?php

namespace App\Sire\Enums;

/**
 * Códigos de Libro SIRE (ver Anexo I del manual v22).
 */
enum CodLibro: string
{
    case RCE  = '080000'; // Registro de Compras Electrónico
    case RVIE = '140000'; // Registro de Ventas e Ingresos Electrónico

    public function label(): string
    {
        return match ($this) {
            self::RCE  => 'Registro de Compras Electrónico',
            self::RVIE => 'Registro de Ventas e Ingresos Electrónico',
        };
    }
}

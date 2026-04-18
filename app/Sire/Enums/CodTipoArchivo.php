<?php

namespace App\Sire\Enums;

/**
 * Extensión del archivo a descargar (Anexo III).
 */
enum CodTipoArchivo: string
{
    case TXT   = '0';
    case CSV   = '1';
    case EXCEL = '2';

    public function extension(): string
    {
        return match ($this) {
            self::TXT   => 'txt',
            self::CSV   => 'csv',
            self::EXCEL => 'xlsx',
        };
    }
}

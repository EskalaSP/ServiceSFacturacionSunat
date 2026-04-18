<?php

namespace App\Sire\Enums;

enum FasePeriodo: string
{
    case PROPUESTA  = 'propuesta';
    case PRELIMINAR = 'preliminar';
    case GENERADO   = 'generado';

    public function label(): string
    {
        return match ($this) {
            self::PROPUESTA  => 'Propuesta',
            self::PRELIMINAR => 'Preliminar',
            self::GENERADO   => 'Generado',
        };
    }
}

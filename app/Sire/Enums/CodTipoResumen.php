<?php

namespace App\Sire\Enums;

/**
 * Tipos de resumen (servicio 5.35 descargar resumen).
 */
enum CodTipoResumen: string
{
    case PROPUESTA              = '1';
    case PRELIMINAR             = '2';
    case NO_INCLUIDOS_EXCLUIDOS = '3';
    case REGISTRO               = '4';
    case PRELIMINAR_REGISTRADO  = '5';
    case AJUSTES_POSTERIORES    = '6';
    case NO_DOMICILIADOS        = '7';

    public function label(): string
    {
        return match ($this) {
            self::PROPUESTA              => 'Resumen de propuesta',
            self::PRELIMINAR             => 'Resumen de preliminar',
            self::NO_INCLUIDOS_EXCLUIDOS => 'Resumen no incluidos o excluidos',
            self::REGISTRO               => 'Resumen de registro',
            self::PRELIMINAR_REGISTRADO  => 'Resumen de preliminar registrado',
            self::AJUSTES_POSTERIORES    => 'Resumen ajustes posteriores',
            self::NO_DOMICILIADOS        => 'Resumen no domiciliados',
        };
    }
}

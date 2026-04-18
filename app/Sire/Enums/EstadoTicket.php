<?php

namespace App\Sire\Enums;

/**
 * Estados posibles del proceso (codEstadoProceso, salida de 5.31).
 */
enum EstadoTicket: string
{
    case PENDIENTE          = '01';
    case EN_PROCESO         = '02';
    case PROCESANDO         = '03';
    case TERMINADO          = '05';
    case TERMINADO_ERRORES  = '06';
    case ERROR              = '07';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE          => 'Pendiente',
            self::EN_PROCESO         => 'En proceso',
            self::PROCESANDO         => 'Procesando',
            self::TERMINADO          => 'Terminado',
            self::TERMINADO_ERRORES  => 'Terminado con errores',
            self::ERROR              => 'Error',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::TERMINADO,
            self::TERMINADO_ERRORES,
            self::ERROR,
        ], true);
    }

    public function isSuccess(): bool
    {
        return $this === self::TERMINADO;
    }
}

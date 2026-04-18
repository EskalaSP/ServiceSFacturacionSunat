<?php

namespace App\Sire\Exceptions;

class SireTicketFailedException extends SireException
{
    public static function conEstado(string $numTicket, string $estado, ?string $descripcion = null): self
    {
        return new self(
            "El ticket {$numTicket} terminó con estado '{$estado}'" .
                ($descripcion ? ": {$descripcion}" : '.'),
            sunatCode: $estado,
            httpStatus: 422,
            context: ['num_ticket' => $numTicket],
        );
    }

    public static function timeout(string $numTicket, int $attempts): self
    {
        return new self(
            "El ticket {$numTicket} no finalizó después de {$attempts} intentos de polling.",
            httpStatus: 408,
            context: ['num_ticket' => $numTicket, 'attempts' => $attempts],
        );
    }
}

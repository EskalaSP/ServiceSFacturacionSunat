<?php

namespace App\Sire\Exceptions;

class SireAuthException extends SireException
{
    public static function credencialesInvalidas(): self
    {
        return new self(
            'Credenciales SIRE rechazadas por SUNAT. Verifica que client_id/client_secret tengan seleccionada la URI "MIGE RCE y RVIE - SIRE" en Menú SOL, y que sol_user/sol_pass sean correctos.',
            sunatCode: 'unauthorized_client',
            httpStatus: 401,
        );
    }

    public static function tenantSinCredenciales(): self
    {
        return new self(
            'El tenant no tiene configurado client_id/client_secret/sol_user/sol_pass. Configúralos antes de usar SIRE.',
            httpStatus: 422,
        );
    }
}

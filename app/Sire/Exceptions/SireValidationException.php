<?php

namespace App\Sire\Exceptions;

/**
 * Error 422 de SUNAT. Incluye código 1001-2278 mapeado a mensaje legible.
 */
class SireValidationException extends SireException
{
    public function __construct(string $code, string $sunatMessage, array $context = [])
    {
        $friendly = SireErrorCatalog::friendlyMessage($code, $sunatMessage);

        parent::__construct(
            message: $friendly,
            sunatCode: $code,
            httpStatus: 422,
            context: $context,
        );
    }
}

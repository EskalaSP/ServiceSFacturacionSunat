<?php

namespace App\Sire\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Excepción base del módulo SIRE.
 * Incluye el código SUNAT y un HTTP status sugerido para el controller.
 */
class SireException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $sunatCode = null,
        public readonly int $httpStatus = 500,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

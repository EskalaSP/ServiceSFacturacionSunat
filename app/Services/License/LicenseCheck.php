<?php

namespace App\Services\License;

/**
 * Resultado de comprobar la licencia de esta instalación.
 *
 * `allowed` decide si el sistema puede emitir a producción. `offlineGrace`
 * indica que se permitió por periodo de gracia (servidor caído), para poder
 * avisarlo sin bloquear.
 */
final readonly class LicenseCheck
{
    public function __construct(
        public bool $allowed,
        public string $code,
        public string $message,
        public bool $offlineGrace = false,
    ) {}

    public static function allow(string $code = 'ok', string $message = 'Licencia válida.', bool $offlineGrace = false): self
    {
        return new self(true, $code, $message, $offlineGrace);
    }

    public static function deny(string $code, string $message): self
    {
        return new self(false, $code, $message);
    }
}

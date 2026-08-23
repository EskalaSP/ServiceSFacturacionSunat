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
    // Propiedades explícitas (no promovidas): la promoción en el constructor
    // no sobrevive a la ofuscación de variables.
    public bool $allowed;

    public string $code;

    public string $message;

    public bool $offlineGrace;

    public function __construct(bool $allowed, string $code, string $message, bool $offlineGrace = false)
    {
        $this->allowed = $allowed;
        $this->code = $code;
        $this->message = $message;
        $this->offlineGrace = $offlineGrace;
    }

    public static function allow(string $code = 'ok', string $message = 'Licencia válida.', bool $offlineGrace = false): self
    {
        return new self(true, $code, $message, $offlineGrace);
    }

    public static function deny(string $code, string $message): self
    {
        return new self(false, $code, $message);
    }
}

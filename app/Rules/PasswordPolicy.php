<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Validation\Rules\Password;

/**
 * Política de contraseña única del sistema: mínimo 6 caracteres, con al menos
 * una mayúscula, una minúscula, un número y un carácter especial.
 *
 * Se usa tanto al registrar/actualizar la contraseña como al iniciar sesión,
 * para mantener una sola fuente de verdad.
 */
class PasswordPolicy
{
    public static function rule(): Password
    {
        return Password::min(6)
            ->mixedCase()  // mayúscula + minúscula
            ->numbers()    // al menos un número
            ->symbols();   // al menos un carácter especial
    }
}

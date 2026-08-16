<?php

namespace App\Concerns;

use App\Rules\PasswordPolicy;
use Illuminate\Contracts\Validation\Rule;

trait PasswordValidationRules
{
    /**
     * Obtener las reglas de validación para contraseñas.
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', PasswordPolicy::rule(), 'confirmed'];
    }

    /**
     * Obtener las reglas de validación para la contraseña actual.
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}

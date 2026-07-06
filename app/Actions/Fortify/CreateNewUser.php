<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validar y crear un nuevo usuario registrado.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        // El primer usuario registrado en la BD queda automáticamente
        // como super administrador. El resto de registros están bloqueados
        // por EnsureFirstUserRegistration (los super admin crean usuarios
        // manualmente desde el panel).
        $esPrimerUsuario = ! User::query()->exists();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'is_admin' => $esPrimerUsuario,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeAdmin extends Command
{
    protected $signature = 'admin:make
                            {email : Email del usuario a promover a admin}
                            {--name= : Nombre (solo si se crea nuevo usuario)}
                            {--password= : Contraseña (solo si se crea nuevo usuario)}';

    protected $description = 'Promueve un usuario a admin. Si no existe, lo crea.';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $name = $this->option('name') ?: $this->ask('Nombre del nuevo usuario');
            $password = $this->option('password') ?: $this->secret('Contraseña (min 8 caracteres)');

            if (strlen((string) $password) < 8) {
                $this->error('La contraseña debe tener al menos 8 caracteres.');

                return self::FAILURE;
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]);

            $this->info("✔ Usuario admin creado: {$email}");

            return self::SUCCESS;
        }

        if ($user->is_admin) {
            $this->warn("{$email} ya es admin.");

            return self::SUCCESS;
        }

        $user->update(['is_admin' => true]);
        $this->info("✔ {$email} promovido a admin.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Tenant;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create {email} {ruc} {razon_social}';
    protected $description = 'Creates a tenant for a given user email';

    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("Usuario no encontrado.");
            return;
        }

        $tenant = Tenant::firstOrCreate(
            ['ruc' => $this->argument('ruc')],
            [
                'user_id' => $user->id,
                'razon_social' => $this->argument('razon_social'),
                'sol_user' => '',
                'sol_pass' => '',
                'environment' => 'beta',
                'plan' => 'free',
                'is_active' => true,
            ]
        );

        // Also create series
        \App\Models\Serie::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tipo_documento' => '01', 'serie' => 'F001'],
            ['correlativo' => 0, 'is_active' => true]
        );

        \App\Models\Serie::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tipo_documento' => '03', 'serie' => 'B001'],
            ['correlativo' => 0, 'is_active' => true]
        );

        $this->info("Tenant creado con RUC: {$tenant->ruc}");
    }
}

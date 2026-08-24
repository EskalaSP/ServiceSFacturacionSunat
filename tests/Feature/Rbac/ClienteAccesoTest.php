<?php

use App\Models\Tenant;
use App\Models\User;

it('el admin crea un usuario cliente y queda como dueño de la empresa', function () {
    $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true, 'is_active' => true]);
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)->post('/admin/usuarios', [
        'name' => 'Cliente Uno',
        'email' => 'cliente1@empresa.com',
        'password' => 'Cliente123!',
        'role' => 'cliente',
        'empresa_id' => $tenant->id,
        'is_active' => true,
    ])->assertRedirect();

    $cliente = User::where('email', 'cliente1@empresa.com')->first();

    expect($cliente)->not->toBeNull()
        ->and($cliente->role)->toBe('cliente')
        ->and($cliente->hasPanelAccess())->toBeFalse();

    $membresia = $cliente->membershipFor($tenant);
    expect($membresia)->not->toBeNull()
        ->and($membresia->role)->toBe('owner');
});

it('asignar dueño a una empresa crea su membresía owner', function () {
    $cliente = User::factory()->create(['role' => 'cliente']);
    $tenant = Tenant::factory()->create();

    $tenant->update(['user_id' => $cliente->id]);

    expect($cliente->membershipFor($tenant)?->role)->toBe('owner');
});

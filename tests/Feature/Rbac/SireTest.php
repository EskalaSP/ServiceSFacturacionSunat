<?php

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

function cajeroSire(Tenant $tenant, array $abilities): User
{
    $u = User::factory()->create();
    $tenant->miembros()->attach($u, [
        'role' => TenantMembership::ROLE_CAJERO,
        'abilities' => json_encode($abilities),
        'is_active' => true,
    ]);

    return $u;
}

it('el dueño ve el panel SIRE', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($tenant->user)->get('/sunat/sire')->assertOk();
});

it('un cajero con sire.gestionar ve el panel SIRE', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroSire($tenant, ['sire.gestionar']);

    $this->actingAs($cajero)->get('/sunat/sire')->assertOk();
});

it('un cajero sin sire.gestionar no ve el panel SIRE', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroSire($tenant, ['factura.emitir']);

    $this->actingAs($cajero)->get('/sunat/sire')->assertForbidden();
});

it('un cajero sin sire.gestionar no puede activar SIRE', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroSire($tenant, ['factura.emitir']);

    $this->actingAs($cajero)->post('/sunat/sire/activar')->assertForbidden();
});

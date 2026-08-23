<?php

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

function cajeroBoleta(Tenant $tenant, array $abilities): User
{
    $u = User::factory()->create();
    $tenant->miembros()->attach($u, [
        'role' => TenantMembership::ROLE_CAJERO,
        'abilities' => json_encode($abilities),
        'is_active' => true,
    ]);

    return $u;
}

it('el dueño puede abrir el formulario de boleta', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($tenant->user)->get('/sunat/boletas/nueva')->assertOk();
});

it('un cajero con boleta.emitir pasa el gate al emitir boleta', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroBoleta($tenant, ['boleta.emitir']);

    // El gate pasa; la emisión falla por datos vacíos y redirige (302), no 403.
    $this->actingAs($cajero)->post('/sunat/boletas', [])->assertRedirect();
});

it('un cajero sin boleta.emitir no puede emitir boleta', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroBoleta($tenant, ['factura.emitir']);

    $this->actingAs($cajero)->post('/sunat/boletas', [])->assertForbidden();
});

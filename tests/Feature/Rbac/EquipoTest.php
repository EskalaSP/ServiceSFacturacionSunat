<?php

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

function cajeroDe(Tenant $tenant, array $abilities = ['factura.emitir']): User
{
    $cajero = User::factory()->create();
    $tenant->miembros()->attach($cajero, [
        'role' => TenantMembership::ROLE_CAJERO,
        'abilities' => json_encode($abilities),
        'is_active' => true,
    ]);

    return $cajero;
}

it('el dueño puede abrir Mi equipo', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($tenant->user)->get('/sunat/equipo')->assertOk();
});

it('el dueño crea un cajero y recibe su contraseña temporal', function () {
    $tenant = Tenant::factory()->create();

    $res = $this->actingAs($tenant->user)->post('/sunat/equipo', [
        'name' => 'Ana Cajera',
        'email' => 'ana@empresa.com',
        'abilities' => ['boleta.emitir', 'boleta.descargar'],
    ]);

    $res->assertRedirect();
    $res->assertSessionHas('nuevoCajero');

    $cajero = User::where('email', 'ana@empresa.com')->first();
    expect($cajero)->not->toBeNull();

    $m = $cajero->membershipFor($tenant);
    expect($m->role)->toBe(TenantMembership::ROLE_CAJERO)
        ->and($m->permite('boleta.emitir'))->toBeTrue()
        ->and($m->permite('boleta.anular'))->toBeFalse();
});

it('un cajero no puede entrar a Mi equipo', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroDe($tenant);

    $this->actingAs($cajero)->get('/sunat/equipo')->assertForbidden();
});

it('no se pueden asignar permisos reservados al dueño (equipo.gestionar)', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($tenant->user)->post('/sunat/equipo', [
        'name' => 'X',
        'email' => 'x@e.com',
        'abilities' => ['equipo.gestionar'],
    ])->assertSessionHasErrors('abilities.0');
});

it('el dueño no puede tocar cajeros de otra empresa', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $cajeroB = cajeroDe($tenantB);

    $this->actingAs($tenantA->user)
        ->put("/sunat/equipo/{$cajeroB->id}", ['abilities' => [], 'is_active' => true])
        ->assertForbidden();
});

it('un cajero no puede editar la configuración de la empresa', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroDe($tenant, ['factura.emitir']);

    $this->actingAs($cajero)->put('/sunat/configuracion', [
        'sol_user' => 'USUARIO',
        'sol_pass' => 'clave',
        'environment' => 'beta',
        'serie_factura' => 'F001',
        'serie_boleta' => 'B001',
    ])->assertForbidden();
});

it('el super admin puede gestionar el equipo de cualquier empresa', function () {
    $tenant = Tenant::factory()->create();
    $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this->actingAs($super)
        ->withSession(['empresa_activa_id' => $tenant->id])
        ->get('/sunat/equipo')->assertOk();
});

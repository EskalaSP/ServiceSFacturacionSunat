<?php

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\EmpresaActiva;
use App\Support\Rbac\Ability;

it('al crear una empresa, su dueño queda como owner en el pivote', function () {
    $tenant = Tenant::factory()->create();

    $membresia = $tenant->user->membershipFor($tenant);

    expect($membresia)->not->toBeNull()
        ->and($membresia->role)->toBe(TenantMembership::ROLE_OWNER)
        ->and($membresia->is_active)->toBeTrue();
});

it('el owner puede todo en su empresa y nada en otra', function () {
    $tenant = Tenant::factory()->create();
    $owner = $tenant->user;
    $otra = Tenant::factory()->create();

    expect($owner->puede('factura.emitir', $tenant))->toBeTrue()
        ->and($owner->puede('factura.anular', $tenant))->toBeTrue()
        ->and($owner->puede(Ability::EQUIPO_GESTIONAR, $tenant))->toBeTrue()
        ->and($owner->puede('factura.emitir', $otra))->toBeFalse();
});

it('el super admin puede en cualquier empresa aunque no sea miembro', function () {
    $tenant = Tenant::factory()->create();
    $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    expect($super->puede('factura.emitir', $tenant))->toBeTrue()
        ->and($super->puede(Ability::SIRE_GESTIONAR, $tenant))->toBeTrue();
});

it('el cajero solo puede lo que tiene en abilities', function () {
    $tenant = Tenant::factory()->create();
    $cajero = User::factory()->create();

    $tenant->miembros()->attach($cajero, [
        'role' => TenantMembership::ROLE_CAJERO,
        'abilities' => json_encode(['boleta.emitir', 'boleta.descargar']),
        'is_active' => true,
    ]);

    expect($cajero->puede('boleta.emitir', $tenant))->toBeTrue()
        ->and($cajero->puede('boleta.anular', $tenant))->toBeFalse()
        ->and($cajero->puede('factura.emitir', $tenant))->toBeFalse();
});

it('un cajero desactivado no puede nada', function () {
    $tenant = Tenant::factory()->create();
    $cajero = User::factory()->create();

    $tenant->miembros()->attach($cajero, [
        'role' => TenantMembership::ROLE_CAJERO,
        'abilities' => json_encode(['factura.emitir']),
        'is_active' => false,
    ]);

    expect($cajero->puede('factura.emitir', $tenant))->toBeFalse();
});

it('EmpresaActiva lista solo las empresas del usuario, y todas para el super admin', function () {
    $t1 = Tenant::factory()->create();
    $owner = $t1->user;
    $t2 = Tenant::factory()->create();

    $svc = app(EmpresaActiva::class);

    $this->actingAs($owner);
    $idsOwner = $svc->disponibles()->pluck('id')->all();
    expect($idsOwner)->toContain($t1->id)->not->toContain($t2->id);

    $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->actingAs($super);
    $idsSuper = $svc->disponibles()->pluck('id')->all();
    expect($idsSuper)->toContain($t1->id, $t2->id);
});

it('EmpresaActiva.set rechaza una empresa ajena', function () {
    $ajena = Tenant::factory()->create();
    $intruso = User::factory()->create();

    $this->actingAs($intruso);

    expect(fn () => app(EmpresaActiva::class)->set($ajena))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('el catálogo de abilities cubre tipos y módulos, y el preset de cajero es sensato', function () {
    $todas = Ability::todas();

    expect($todas)->toContain('factura.emitir', 'guia_remitente.anular', Ability::EQUIPO_GESTIONAR);

    $preset = Ability::presetCajero();
    expect($preset)->toContain('factura.emitir')->not->toContain('factura.anular');
});

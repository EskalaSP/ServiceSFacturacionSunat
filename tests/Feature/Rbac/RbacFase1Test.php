<?php

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\EmpresaActiva;
use App\Support\Rbac\Ability;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('el rol simple solo consulta descarga reportes exporta y edita datos comerciales', function () {
    $preset = Ability::presetSimple();

    expect($preset)
        ->toContain('factura.descargar', 'boleta.descargar', Ability::REPORTE_VER, Ability::EXPORTAR, Ability::CONSULTA_CPE, Ability::CONFIG_EDITAR)
        ->not->toContain('factura.emitir', 'factura.reenviar', 'factura.anular', Ability::SERIE_GESTIONAR, Ability::APIKEY_VER);
});

it('el rol simple entra a consultas pero no al panel ni a nueva factura', function () {
    $tenant = Tenant::factory()->create(['sol_user' => 'USUARIO', 'sol_pass' => 'secreto']);
    $simple = User::factory()->create();
    $tenant->miembros()->attach($simple, [
        'role' => TenantMembership::ROLE_SIMPLE,
        'is_active' => true,
    ]);

    $this->actingAs($simple)
        ->withSession(['empresa_activa_id' => $tenant->id])
        ->get('/sunat')
        ->assertRedirect('/sunat/historial');

    $this->get('/sunat/historial')->assertOk();
    $this->get('/sunat/reportes')->assertOk();
    $this->get('/sunat/consulta-cpe')->assertOk();
    $this->get('/sunat/exportar')->assertOk();
    $this->get('/sunat/facturas/nueva')->assertForbidden();

    $this->getJson('/sunat/reportes/registro-ventas?fecha_desde=2026-08-01&fecha_hasta=2026-08-31')
        ->assertOk();
    $this->get('/sunat/reportes/registro-ventas/pdf?fecha_desde=2026-08-01&fecha_hasta=2026-08-31')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    $this->get('/sunat/reportes/registro-ventas/excel?fecha_desde=2026-08-01&fecha_hasta=2026-08-31')
        ->assertOk();
    $this->post('/sunat/historial/01/999999/reenviar')->assertForbidden();
});

it('el rol simple edita datos comerciales sin recibir ni modificar configuración sensible', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create([
        'sol_user' => 'USUARIO_REAL',
        'sol_pass' => 'clave-real',
        'environment' => 'produccion',
        'certificate_path' => 'certificados/original.pem',
        'certificate_password' => 'cert-real',
        'consulta_token' => 'token-real',
    ]);
    $simple = User::factory()->create();
    $tenant->miembros()->attach($simple, [
        'role' => TenantMembership::ROLE_SIMPLE,
        'is_active' => true,
    ]);

    $this->actingAs($simple)
        ->withSession(['empresa_activa_id' => $tenant->id])
        ->get('/sunat/configuracion')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tenant.sol_user', '')
            ->where('tenant.environment', 'produccion')
            ->where('tenant.serie_factura', '')
            ->where('tenant.serie_boleta', '')
            ->where('tenant.consulta_token_set', false));

    $this->put('/sunat/configuracion', [
        'razon_social' => 'EMPRESA ACTUALIZADA SAC',
        'direccion' => 'Av. Nueva 123',
        'mensaje_agradecimiento' => 'Gracias por su compra',
        'sol_user' => 'USUARIO_ATACANTE',
        'sol_pass' => 'clave-atacante',
        'environment' => 'beta',
        'serie_factura' => 'F999',
        'serie_boleta' => 'B999',
        'certificate' => UploadedFile::fake()->create('nuevo.pfx', 10),
        'certificate_password' => 'cert-atacante',
    ])->assertRedirect('/sunat/configuracion');

    $tenant->refresh();
    expect($tenant->razon_social)->toBe('EMPRESA ACTUALIZADA SAC')
        ->and($tenant->direccion)->toBe('Av. Nueva 123')
        ->and($tenant->mensaje_agradecimiento)->toBe('Gracias por su compra')
        ->and($tenant->sol_user)->toBe('USUARIO_REAL')
        ->and($tenant->sol_pass)->toBe('clave-real')
        ->and($tenant->environment)->toBe('produccion')
        ->and($tenant->certificate_path)->toBe('certificados/original.pem')
        ->and($tenant->certificate_password)->toBe('cert-real')
        ->and($tenant->consulta_token)->toBe('token-real');

    $this->put('/sunat/configuracion/consulta-token', ['consulta_token' => 'otro-token'])
        ->assertForbidden();
    $this->post('/sunat/configuracion/probar')->assertForbidden();
});

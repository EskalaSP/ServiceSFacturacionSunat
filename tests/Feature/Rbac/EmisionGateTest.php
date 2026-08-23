<?php

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

function cajeroCon(Tenant $tenant, array $abilities): User
{
    $u = User::factory()->create();
    $tenant->miembros()->attach($u, [
        'role' => TenantMembership::ROLE_CAJERO,
        'abilities' => json_encode($abilities),
        'is_active' => true,
    ]);

    return $u;
}

it('un cajero sin factura.emitir no puede emitir factura', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroCon($tenant, ['boleta.emitir']);

    $this->actingAs($cajero)
        ->post('/sunat/facturas', ['tipo_documento' => '01'])
        ->assertForbidden();
});

it('un cajero con boleta.emitir pasa el gate al emitir boleta', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroCon($tenant, ['boleta.emitir']);

    // El gate pasa; la emisión falla por datos vacíos y redirige (302), no 403.
    $this->actingAs($cajero)
        ->post('/sunat/facturas', ['tipo_documento' => '03'])
        ->assertRedirect();
});

it('un cajero sin nota_credito.emitir no puede emitir nota de crédito', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroCon($tenant, ['factura.emitir']);

    $this->actingAs($cajero)->post('/sunat/nota-credito', [])->assertForbidden();
});

it('un cajero sin cotizacion.emitir no puede crear cotización', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroCon($tenant, ['factura.emitir']);

    $this->actingAs($cajero)->post('/sunat/cotizaciones', [])->assertForbidden();
});

it('un cajero sin gestionar-clientes no puede crear clientes', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroCon($tenant, ['factura.emitir']);

    $this->actingAs($cajero)->post('/sunat/clientes', [
        'tipo_documento' => '6',
        'numero_documento' => '20123456789',
        'razon_social' => 'X',
    ])->assertForbidden();
});

it('el dueño sí pasa el gate al emitir factura', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($tenant->user)
        ->post('/sunat/facturas', ['tipo_documento' => '01'])
        ->assertRedirect();
});

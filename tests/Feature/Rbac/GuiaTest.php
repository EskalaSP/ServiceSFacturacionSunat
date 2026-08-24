<?php

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

function cajeroGuia(Tenant $tenant, array $abilities): User
{
    $u = User::factory()->create();
    $tenant->miembros()->attach($u, [
        'role' => TenantMembership::ROLE_CAJERO,
        'abilities' => json_encode($abilities),
        'is_active' => true,
    ]);

    return $u;
}

it('el dueño puede abrir el formulario de guías', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($tenant->user)->get('/sunat/guias/nueva')->assertOk();
});

it('un cajero sin permiso de guías no puede emitir', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroGuia($tenant, ['factura.emitir']);

    $this->actingAs($cajero)->post('/sunat/guias', ['tipo_documento' => '09'])->assertForbidden();
});

it('un cajero con guia_remitente pasa el gate en una guía remitente', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroGuia($tenant, ['guia_remitente.emitir']);

    // El gate pasa; la emisión falla por datos vacíos y redirige (302), no 403.
    $this->actingAs($cajero)->post('/sunat/guias', ['tipo_documento' => '09'])->assertRedirect();
});

it('un cajero con solo guia_remitente no puede emitir guía transportista', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroGuia($tenant, ['guia_remitente.emitir']);

    $this->actingAs($cajero)->post('/sunat/guias', ['tipo_documento' => '31'])->assertForbidden();
});

<?php

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

function cajeroF4(Tenant $tenant, array $abilities): User
{
    $u = User::factory()->create();
    $tenant->miembros()->attach($u, [
        'role' => TenantMembership::ROLE_CAJERO,
        'abilities' => json_encode($abilities),
        'is_active' => true,
    ]);

    return $u;
}

it('el dueño ve series, consulta, exportar y reportes', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($tenant->user)->get('/sunat/series')->assertOk();
    $this->actingAs($tenant->user)->get('/sunat/consulta-cpe')->assertOk();
    $this->actingAs($tenant->user)->get('/sunat/exportar')->assertOk();
    $this->actingAs($tenant->user)->get('/sunat/reportes')->assertOk();
});

it('un cajero sin serie.gestionar no ve las series', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroF4($tenant, ['factura.emitir']);

    $this->actingAs($cajero)->get('/sunat/series')->assertForbidden();
});

it('un cajero con serie.gestionar puede crear una serie', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroF4($tenant, ['serie.gestionar']);

    $this->actingAs($cajero)
        ->post('/sunat/series', ['tipo_documento' => '01', 'serie' => 'F001', 'correlativo' => 1])
        ->assertRedirect();

    $this->assertDatabaseHas('series', ['tenant_id' => $tenant->id, 'serie' => 'F001']);
});

it('un cajero sin consulta.cpe no puede consultar', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroF4($tenant, ['factura.emitir']);

    $this->actingAs($cajero)
        ->getJson('/sunat/consulta-cpe/buscar?tipo_doc=01&serie=F001&correlativo=1&fecha_emision=19/08/2026&monto=100')
        ->assertForbidden();
});

it('un cajero sin exportar no puede descargar', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroF4($tenant, ['factura.emitir']);

    $this->actingAs($cajero)
        ->get('/sunat/exportar/descargar?fecha_desde=2026-08-01&fecha_hasta=2026-08-23')
        ->assertForbidden();
});

it('un cajero sin reporte.ver no puede generar reportes', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroF4($tenant, ['factura.emitir']);

    $this->actingAs($cajero)
        ->getJson('/sunat/reportes/registro-ventas?fecha_desde=2026-08-01&fecha_hasta=2026-08-23')
        ->assertForbidden();
});

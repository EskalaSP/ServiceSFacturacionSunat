<?php

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

function cajeroF3(Tenant $tenant, array $abilities): User
{
    $u = User::factory()->create();
    $tenant->miembros()->attach($u, [
        'role' => TenantMembership::ROLE_CAJERO,
        'abilities' => json_encode($abilities),
        'is_active' => true,
    ]);

    return $u;
}

it('el dueño abre retención, percepción, reversión y nota de venta', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($tenant->user)->get('/sunat/retenciones/nueva')->assertOk();
    $this->actingAs($tenant->user)->get('/sunat/percepciones/nueva')->assertOk();
    $this->actingAs($tenant->user)->get('/sunat/reversiones/nueva')->assertOk();
    $this->actingAs($tenant->user)->get('/sunat/nota-venta/nueva')->assertOk();
});

it('un cajero sin permisos no puede emitir ninguno de los 4', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroF3($tenant, ['factura.emitir']);

    $this->actingAs($cajero)->post('/sunat/retenciones', [])->assertForbidden();
    $this->actingAs($cajero)->post('/sunat/percepciones', [])->assertForbidden();
    $this->actingAs($cajero)->post('/sunat/nota-venta', [])->assertForbidden();
    $this->actingAs($cajero)->post('/sunat/reversiones', [
        'tipo_documento' => '20', 'serie' => 'R001', 'correlativo' => '1', 'motivo' => 'x', 'fecha_generacion' => '2026-08-23',
    ])->assertForbidden();
});

it('un cajero con el permiso correcto pasa el gate', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs(cajeroF3($tenant, ['retencion.emitir']))->post('/sunat/retenciones', [])->assertRedirect();
    $this->actingAs(cajeroF3($tenant, ['percepcion.emitir']))->post('/sunat/percepciones', [])->assertRedirect();
    $this->actingAs(cajeroF3($tenant, ['nota_venta.emitir']))->post('/sunat/nota-venta', [])->assertRedirect();
});

it('la reversión valida el documento (no existe → vuelve con error)', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroF3($tenant, ['reversion.emitir']);

    $this->actingAs($cajero)->post('/sunat/reversiones', [
        'tipo_documento' => '20', 'serie' => 'R001', 'correlativo' => '1', 'motivo' => 'x', 'fecha_generacion' => '2026-08-23',
    ])->assertRedirect()->assertSessionHas('error');
});

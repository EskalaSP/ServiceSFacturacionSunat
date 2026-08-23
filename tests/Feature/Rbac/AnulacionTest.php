<?php

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

function cajeroAnul(Tenant $tenant, array $abilities): User
{
    $u = User::factory()->create();
    $tenant->miembros()->attach($u, [
        'role' => TenantMembership::ROLE_CAJERO,
        'abilities' => json_encode($abilities),
        'is_active' => true,
    ]);

    return $u;
}

it('el dueño puede abrir la pantalla de anulación', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($tenant->user)->get('/sunat/anulaciones/nueva')->assertOk();
});

it('un cajero sin anulacion.emitir no puede anular', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroAnul($tenant, ['factura.emitir']);

    $this->actingAs($cajero)->post('/sunat/anulaciones', [
        'tipo_documento' => '01',
        'serie' => 'F001',
        'correlativo' => '1',
        'motivo' => 'prueba',
        'fecha_generacion' => '2026-08-23',
    ])->assertForbidden();
});

it('con permiso pasa el gate y valida el documento (no existe → vuelve con error)', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroAnul($tenant, ['anulacion.emitir']);

    $this->actingAs($cajero)->post('/sunat/anulaciones', [
        'tipo_documento' => '01',
        'serie' => 'F001',
        'correlativo' => '1',
        'motivo' => 'prueba',
        'fecha_generacion' => '2026-08-23',
    ])->assertRedirect()->assertSessionHas('error');
});

it('rechaza anular una boleta por esta vía', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroAnul($tenant, ['anulacion.emitir']);

    // tipo 03 no está permitido por la validación del request (in:01,07,08).
    $this->actingAs($cajero)->post('/sunat/anulaciones', [
        'tipo_documento' => '03',
        'serie' => 'B001',
        'correlativo' => '1',
        'motivo' => 'prueba',
        'fecha_generacion' => '2026-08-23',
    ])->assertSessionHasErrors('tipo_documento');
});

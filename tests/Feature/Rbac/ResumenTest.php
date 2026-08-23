<?php

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

function cajeroResumen(Tenant $tenant, array $abilities): User
{
    $u = User::factory()->create();
    $tenant->miembros()->attach($u, [
        'role' => TenantMembership::ROLE_CAJERO,
        'abilities' => json_encode($abilities),
        'is_active' => true,
    ]);

    return $u;
}

it('el dueño puede abrir la pantalla de resumen diario', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($tenant->user)->get('/sunat/resumenes/nueva')->assertOk();
});

it('un cajero sin resumen.emitir no puede enviar resumen', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroResumen($tenant, ['boleta.emitir']);

    $this->actingAs($cajero)
        ->post('/sunat/resumenes', ['fecha_resumen' => now()->format('Y-m-d')])
        ->assertForbidden();
});

it('con permiso pasa el gate; sin boletas pendientes vuelve con error', function () {
    $tenant = Tenant::factory()->create();
    $cajero = cajeroResumen($tenant, ['resumen.emitir']);

    $this->actingAs($cajero)
        ->post('/sunat/resumenes', ['fecha_resumen' => now()->format('Y-m-d')])
        ->assertRedirect()
        ->assertSessionHas('error');
});

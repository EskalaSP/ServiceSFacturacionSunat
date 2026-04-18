<?php

use App\Sire\Enums\EstadoTicket;
use App\Sire\Models\SireTicket;
use Tests\Feature\Sire\SireTestHelper;

uses(SireTestHelper::class);

beforeEach(function () {
    $this->setUpSunatMock();
    $this->setUpSireTenant();
});

describe('GET /v1/sire/tickets', function () {
    test('lista tickets del tenant paginado', function () {
        SireTicket::factory()->count(3)->create([
            'tenant_id'          => $this->tenant->id,
            'cod_estado_proceso' => EstadoTicket::PROCESANDO->value,
        ])->each(fn ($t, $i) => $t->update(['num_ticket' => '202604000' . str_pad((string) $i, 7, '0', STR_PAD_LEFT)]));

        $response = $this->getJson('/api/v1/sire/tickets', $this->headers());

        $response->assertStatus(200);
        expect($response->json('data.data'))->toHaveCount(3);
    });

    test('filtra por estado', function () {
        SireTicket::factory()->create([
            'tenant_id'          => $this->tenant->id,
            'num_ticket'         => '2026040000000A',
            'cod_estado_proceso' => EstadoTicket::TERMINADO->value,
        ]);
        SireTicket::factory()->create([
            'tenant_id'          => $this->tenant->id,
            'num_ticket'         => '2026040000000B',
            'cod_estado_proceso' => EstadoTicket::PROCESANDO->value,
        ]);

        $response = $this->getJson('/api/v1/sire/tickets?estado=05', $this->headers());
        $response->assertStatus(200);
        expect($response->json('data.data'))->toHaveCount(1);
    });

    test('no ve tickets de otros tenants', function () {
        // $this->tenant ya fue creado en el beforeEach global
        $user = \App\Models\User::factory()->create();
        $otherTenant = \App\Models\Tenant::create([
            'user_id' => $user->id,
            'ruc' => '20200000002', 'razon_social' => 'OTRO', 'direccion' => 'X',
            'ubigeo' => '150101', 'sol_user' => 'x', 'sol_pass' => 'x',
            'environment' => 'beta', 'plan' => 'business',
            'api_key' => 'other_key_' . bin2hex(random_bytes(8)),
            'api_secret' => 'other_' . bin2hex(random_bytes(8)),
            'is_active' => true,
            'sire_enabled' => true,
        ]);

        SireTicket::factory()->create([
            'tenant_id' => $otherTenant->id,
            'num_ticket' => '9999888877776666',
        ]);

        $response = $this->getJson('/api/v1/sire/tickets', $this->headers());

        expect(collect($response->json('data.data'))->pluck('num_ticket'))
            ->not->toContain('9999888877776666');
    });
});

describe('GET /v1/sire/tickets/{numTicket}', function () {
    test('devuelve ticket específico', function () {
        $t = SireTicket::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'num_ticket' => '2026040012345678',
        ]);

        $response = $this->getJson('/api/v1/sire/tickets/2026040012345678', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('data.num_ticket', '2026040012345678');
    });

    test('404 si el ticket no existe', function () {
        $response = $this->getJson('/api/v1/sire/tickets/9999999999999999', $this->headers());
        $response->assertStatus(404);
    });
});

describe('POST /v1/sire/tickets/{num}/refrescar', function () {
    test('ya finalizado no hace request a SUNAT', function () {
        $t = SireTicket::factory()->create([
            'tenant_id'          => $this->tenant->id,
            'num_ticket'         => '2026040012345678',
            'cod_estado_proceso' => EstadoTicket::TERMINADO->value,
            'finished_at'        => now(),
        ]);

        $response = $this->postJson('/api/v1/sire/tickets/2026040012345678/refrescar', [], $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('data.finalizado', true);
    });
});

describe('GET /v1/sire/tickets/{num}/archivo', function () {
    test('409 si aún no hay archivo descargado', function () {
        SireTicket::factory()->create([
            'tenant_id'          => $this->tenant->id,
            'num_ticket'         => '2026040012345678',
            'cod_estado_proceso' => EstadoTicket::PROCESANDO->value,
        ]);

        $response = $this->getJson('/api/v1/sire/tickets/2026040012345678/archivo', $this->headers());
        $response->assertStatus(409);
    });
});

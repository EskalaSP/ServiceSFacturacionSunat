<?php

use Tests\Feature\Sire\SireTestHelper;

uses(SireTestHelper::class);

beforeEach(function () {
    $this->setUpSunatMock();
});

describe('GET /v1/sire/periodos (servicio 5.33)', function () {
    test('lista ejercicios y periodos habilitados', function () {
        $this->setUpSireTenant();

        $this->mockAuthSuccess();
        $this->mockResponse(200, [[
            'numEjercicio' => '2026',
            'desEstado'    => 'ACTIVO',
            'lisPeriodos'  => [
                ['perTributario' => '202601', 'codEstado' => '01', 'desEstado' => 'Pendiente'],
                ['perTributario' => '202602', 'codEstado' => '02', 'desEstado' => 'Generado'],
            ],
        ]]);

        $response = $this->getJson('/api/v1/sire/periodos', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('data.libro', '080000');
        $response->assertJsonPath('data.libro_desc', 'Registro de Compras Electrónico');
        $response->assertJsonCount(1, 'data.ejercicios');
        $response->assertJsonPath('data.ejercicios.0.anio', '2026');
        expect($response->json('data.ejercicios.0.periodos'))->toHaveCount(2);
    });

    test('acepta parámetro libro=rvie', function () {
        $this->setUpSireTenant();
        $this->mockAuthSuccess();
        $this->mockResponse(200, []);

        $response = $this->getJson('/api/v1/sire/periodos?libro=rvie', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('data.libro', '140000');
    });

    test('rechaza libro inválido con 422', function () {
        $this->setUpSireTenant();

        $response = $this->getJson('/api/v1/sire/periodos?libro=foo', $this->headers());
        $response->assertStatus(422);
    });

    test('bloquea cuando sire_enabled=false', function () {
        $this->setUpSireTenant(sireEnabled: false);

        $response = $this->getJson('/api/v1/sire/periodos', $this->headers());
        $response->assertStatus(403);
        $response->assertJsonPath('error_code', 'sire_not_enabled');
    });
});

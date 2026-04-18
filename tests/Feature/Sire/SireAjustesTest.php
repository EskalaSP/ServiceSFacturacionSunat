<?php

use App\Sire\Jobs\PollTicketJob;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Sire\SireTestHelper;

uses(SireTestHelper::class);

beforeEach(function () {
    $this->setUpSunatMock();
    $this->setUpSireTenant();
});

describe('Ajustes Posteriores (4 variants × 4 acciones)', function () {
    $variants = ['actual', 'no-domiciliados', 'periodos-anteriores', 'periodos-anteriores-nd'];

    foreach ($variants as $i => $variant) {
        test("POST /ajustes-posteriores/{$variant}/cargar dispara TUS + ticket", function () use ($variant, $i) {
            Queue::fake();
            $this->mockAuthSuccess();
            $this->mockResponse(201, '', ['Location' => 'https://api-sire.sunat.gob.pe/v1/upload/x']);
            $this->mockResponse(204, ['numTicket' => '2026040088888888']);

            // Secuencia única por variante para evitar colisión de nombre de ZIP en Windows
            // (variants 'actual' y 'periodos-anteriores' comparten codProceso=59)
            $response = $this->postJson("/api/v1/sire/rce/202604/ajustes-posteriores/{$variant}/cargar", [
                'txt' => "data line\n",
                'secuencia' => 100 + $i,
            ], $this->headers());

            $response->assertStatus(202);
            $response->assertJsonPath('data.variant', str_replace('-', '_', $variant));
            Queue::assertPushed(PollTicketJob::class);
        });

        test("POST /ajustes-posteriores/{$variant}/enviar funciona", function () use ($variant) {
            Queue::fake();
            $this->mockAuthSuccess();
            $this->mockResponse(200, ['numTicket' => '2026040077777777']);

            $response = $this->postJson("/api/v1/sire/rce/202604/ajustes-posteriores/{$variant}/enviar", [
                'num_ticket_carga' => '2026040011111111',
            ], $this->headers());

            $response->assertStatus(202);
        });

        test("GET /ajustes-posteriores/{$variant}/descargar funciona", function () use ($variant) {
            Queue::fake();
            $this->mockAuthSuccess();
            $this->mockResponse(200, ['numTicket' => '2026040033333333']);

            $response = $this->getJson("/api/v1/sire/rce/202604/ajustes-posteriores/{$variant}/descargar?formato=excel", $this->headers());

            $response->assertStatus(202);
        });

        test("POST /ajustes-posteriores/{$variant}/eliminar funciona", function () use ($variant) {
            $this->mockAuthSuccess();
            $this->mockResponse(200, ['resultado' => 'OK']);

            $response = $this->postJson("/api/v1/sire/rce/202604/ajustes-posteriores/{$variant}/eliminar", [
                'cod_ajuste_posterior' => 'AP-202604-001',
                'detalles' => [[
                    'cod_tipo_cdp'  => '01',
                    'num_serie_cdp' => 'F001',
                    'num_cdp'       => '123',
                    'cod_car'       => '11-202604-001',
                ]],
            ], $this->headers());

            $response->assertStatus(200);
            $response->assertJsonPath('data.eliminados', 1);
        });
    }

    test('variant inválido devuelve 422', function () {
        $response = $this->postJson(
            '/api/v1/sire/rce/202604/ajustes-posteriores/invalido/cargar',
            ['txt' => 'x'],
            $this->headers(),
        );
        $response->assertStatus(422);
    });
});

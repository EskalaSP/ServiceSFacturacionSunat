<?php

use App\Sire\Jobs\PollTicketJob;
use App\Sire\Models\SireTicket;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Sire\SireTestHelper;

uses(SireTestHelper::class);

beforeEach(function () {
    $this->setUpSunatMock();
    $this->setUpSireTenant();
});

describe('GET /v1/sire/rce/{periodo}/propuesta (5.34)', function () {
    test('solicita propuesta y crea ticket + PollTicketJob', function () {
        Queue::fake();

        $this->mockAuthSuccess();
        $this->mockTicketResponse('2026040099999999');

        $response = $this->getJson('/api/v1/sire/rce/202604/propuesta', $this->headers());

        $response->assertStatus(202);
        $response->assertJsonPath('data.num_ticket', '2026040099999999');
        $response->assertJsonPath('data.per_tributario', '202604');

        expect(SireTicket::where('num_ticket', '2026040099999999')->exists())->toBeTrue();
        Queue::assertPushed(PollTicketJob::class);
    });

    test('acepta filtros opcionales', function () {
        Queue::fake();
        $this->mockAuthSuccess();
        $this->mockTicketResponse();

        $response = $this->getJson(
            '/api/v1/sire/rce/202604/propuesta?formato=csv&cod_tipo_cdp=01&mto_desde=100&mto_hasta=5000',
            $this->headers(),
        );

        $response->assertStatus(202);
    });

    test('periodo inválido devuelve 422', function () {
        $response = $this->getJson('/api/v1/sire/rce/2026-04/propuesta', $this->headers());
        $response->assertStatus(422);
    });

    test('periodo futuro parseable pero SUNAT rechaza', function () {
        $this->mockAuthSuccess();
        $this->mockResponse(422, [
            'cod' => '422',
            'errors' => [['cod' => '1007', 'msg' => 'El perTributario de búsqueda no debe ser mayor a la fecha actual']],
        ]);

        $response = $this->getJson('/api/v1/sire/rce/209912/propuesta', $this->headers());
        $response->assertStatus(422);
        $response->assertJsonPath('errors.sunat_code', '1007');
    });
});

describe('POST /v1/sire/rce/{periodo}/aceptar-propuesta (5.2)', function () {
    test('acepta propuesta y genera ticket', function () {
        Queue::fake();
        $this->mockAuthSuccess();
        $this->mockTicketResponse('2026040011111111');

        $response = $this->postJson('/api/v1/sire/rce/202604/aceptar-propuesta', [], $this->headers());

        $response->assertStatus(202);
        $response->assertJsonPath('data.num_ticket', '2026040011111111');
        Queue::assertPushed(PollTicketJob::class);
    });
});

describe('POST /v1/sire/rce/{periodo}/registrar-preliminar (5.4)', function () {
    test('registra preliminar sincrónicamente', function () {
        $this->mockAuthSuccess();
        $this->mockResponse(200, ['respuesta' => 'T']);

        $response = $this->postJson('/api/v1/sire/rce/202604/registrar-preliminar', [], $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('data.exitoso', true);
        $response->assertJsonPath('data.per_tributario', '202604');
    });

    test('maneja error 1008 (ya existe preliminar)', function () {
        $this->mockAuthSuccess();
        $this->mockResponse(422, [
            'cod'    => '422',
            'errors' => [['cod' => '1008', 'msg' => 'Ya se encuentra en preliminar']],
        ]);

        $response = $this->postJson('/api/v1/sire/rce/202604/registrar-preliminar', [], $this->headers());
        $response->assertStatus(422);
        $response->assertJsonPath('errors.sunat_code', '1008');
    });
});

describe('GET /v1/sire/rce/{periodo}/resumen (5.35)', function () {
    test('descarga resumen binario directo', function () {
        $this->mockAuthSuccess();
        $this->mockResponse(200, 'BINARY_TXT_CONTENT', [
            'Content-Type' => 'text/plain',
        ]);

        $response = $this->get('/api/v1/sire/rce/202604/resumen?tipo=propuesta&formato=txt', $this->headers());

        $response->assertStatus(200);
        expect($response->headers->get('Content-Disposition'))->toContain('resumen-202604');
    });
});

describe('GET /v1/sire/rce/constancia (5.49)', function () {
    test('descarga PDF de constancia', function () {
        $this->mockAuthSuccess();
        $this->mockResponse(200, 'PDF_BYTES', ['Content-Type' => 'application/pdf']);

        $response = $this->get(
            '/api/v1/sire/rce/constancia?nom_constancia=LE20100000001202604.pdf',
            $this->headers(),
        );

        $response->assertStatus(200);
        expect($response->headers->get('Content-Disposition'))->toContain('LE20100000001202604.pdf');
    });

    test('requiere nom_constancia', function () {
        $response = $this->getJson('/api/v1/sire/rce/constancia', $this->headers());
        $response->assertStatus(422);
    });
});

describe('GET /v1/sire/rce/{periodo}/comprobantes', function () {
    test('lista paginada con totales', function () {
        $periodo = App\Sire\Models\SirePeriodo::create([
            'tenant_id'      => $this->tenant->id,
            'per_tributario' => '202604',
            'cod_libro'      => '080000',
            'fase'           => 'propuesta',
        ]);

        App\Sire\Models\SireComprobante::create([
            'tenant_id' => $this->tenant->id,
            'sire_periodo_id' => $periodo->id,
            'per_tributario' => '202604',
            'fase' => 'propuesta',
            'car_sunat' => '11-202604-001',
            'fec_emision' => '2026-04-15',
            'cod_tipo_cdp' => '01',
            'num_serie_cdp' => 'F001',
            'num_cdp' => '1',
            'num_doc_proveedor' => '20100000002',
            'tipo_doc_proveedor' => '6',
            'razon_social_proveedor' => 'PROV A',
            'mto_bi_gravada' => 1000,
            'mto_igv' => 180,
            'mto_total' => 1180,
        ]);

        $response = $this->getJson('/api/v1/sire/rce/202604/comprobantes', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('data.totales.total_comprobantes', 1);
        $response->assertJsonPath('data.totales.suma_total', 1180);
        expect($response->json('data.comprobantes.data'))->toHaveCount(1);
    });

    test('filtros funcionan', function () {
        $response = $this->getJson(
            '/api/v1/sire/rce/202604/comprobantes?fase=propuesta&num_doc_proveedor=20100000999',
            $this->headers(),
        );
        $response->assertStatus(200);
        $response->assertJsonPath('data.totales.total_comprobantes', 0);
    });
});

<?php

use App\Sire\Jobs\PollTicketJob;
use App\Sire\Models\SireUploadFile;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Sire\SireTestHelper;

uses(SireTestHelper::class);

beforeEach(function () {
    $this->setUpSunatMock();
    $this->setUpSireTenant();
});

describe('Uploads TUS (5.3 / 5.5 / 5.6)', function () {
    test('POST /reemplazar-propuesta sube TXT y devuelve ticket', function () {
        Queue::fake();

        $this->mockAuthSuccess();
        // TUS: paso 1 POST crea recurso → 201 + Location header
        $this->mockResponse(201, '', ['Location' => 'https://api-sire.sunat.gob.pe/v1/upload/abc123']);
        // TUS: paso 2 PATCH con bytes → 204 + body con numTicket
        $this->mockResponse(204, ['numTicket' => '2026040044444444']);

        $response = $this->postJson('/api/v1/sire/rce/202604/reemplazar-propuesta', [
            'txt'       => "202604|11-202604-TEST|1|15/04/2026|\n",
            'secuencia' => 1,
        ], $this->headers());

        $response->assertStatus(202);
        $response->assertJsonPath('data.num_ticket', '2026040044444444');
        expect(SireUploadFile::where('tenant_id', $this->tenant->id)->exists())->toBeTrue();
        Queue::assertPushed(PollTicketJob::class);
    });

    test('POST /reemplazar-propuesta con respuesta custom (ticket en POST)', function () {
        Queue::fake();
        $this->mockAuthSuccess();
        // Algunos endpoints SUNAT devuelven numTicket directo en el POST
        $this->mockResponse(200, ['numTicket' => '2026040055555555']);

        $response = $this->postJson('/api/v1/sire/rce/202604/reemplazar-propuesta', [
            'txt'       => 'data',
            'secuencia' => 22, // secuencia diferente para evitar colisión de nombre de ZIP
        ], $this->headers());

        $response->assertStatus(202);
        $response->assertJsonPath('data.num_ticket', '2026040055555555');
    });

    test('POST /no-domiciliados funciona con archivo ZIP existente', function () {
        Queue::fake();
        $this->mockAuthSuccess();
        $this->mockResponse(200, ['numTicket' => '2026040066666666']);

        $tempZip = tempnam(sys_get_temp_dir(), 'sire-') . '.zip';
        $zip = new ZipArchive();
        $zip->open($tempZip, ZipArchive::CREATE);
        $zip->addFromString('test.txt', 'content');
        $zip->close();

        $file = new \Illuminate\Http\Testing\File(
            'LE20100000001202604080000056000000001.zip',
            fopen($tempZip, 'r'),
        );

        $response = $this->post('/api/v1/sire/rce/202604/no-domiciliados', [
            'archivo' => new \Illuminate\Http\UploadedFile(
                $tempZip,
                'LE20100000001202604080000056000000001.zip',
                'application/zip',
                null,
                true,
            ),
        ], $this->headers());

        $response->assertStatus(202);
        unlink($tempZip);
    });

    test('POST /reemplazar-propuesta sin txt ni archivo = 422', function () {
        $this->mockAuthSuccess();

        $response = $this->postJson('/api/v1/sire/rce/202604/reemplazar-propuesta', [], $this->headers());

        $response->assertStatus(422);
    });

    test('error 1024 (archivo ya enviado) se mapea correctamente', function () {
        $this->mockAuthSuccess();
        $this->mockResponse(422, [
            'cod'    => '422',
            'errors' => [['cod' => '1024', 'msg' => 'Archivo ya enviado']],
        ]);

        $response = $this->postJson('/api/v1/sire/rce/202604/reemplazar-propuesta', [
            'txt' => 'data',
        ], $this->headers());

        $response->assertStatus(422);
        $response->assertJsonPath('errors.sunat_code', '1024');
        expect($response->json('message'))->toContain('previamente enviado');
    });
});

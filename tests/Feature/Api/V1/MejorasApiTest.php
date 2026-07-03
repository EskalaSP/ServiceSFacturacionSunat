<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tenant;

function tenantHeaders(Tenant $tenant): array
{
    return [
        'X-Api-Key'    => $tenant->api_key,
        'X-Api-Secret' => $tenant->api_secret,
        'Accept'       => 'application/json',
    ];
}

// ── Punto 2 ────────────────────────────────────────────────────────────────
test('POST /clientes acepta tipo_doc/num_doc como alias', function () {
    $tenant = Tenant::factory()->create();

    $response = $this->postJson('/api/v1/clientes', [
        'tipo_doc'         => '6',
        'num_doc'          => '20512345678',
        'razon_social'     => 'CLIENTE ALIAS SAC',
    ], tenantHeaders($tenant));

    $response->assertCreated()
        ->assertJsonPath('estado', 'exito');

    expect(Client::where('tenant_id', $tenant->id)->first())
        ->tipo_documento->toBe('6')
        ->numero_documento->toBe('20512345678')
        ->razon_social->toBe('CLIENTE ALIAS SAC');
});

test('POST /clientes sigue aceptando tipo_documento/numero_documento (compat)', function () {
    $tenant = Tenant::factory()->create();

    $response = $this->postJson('/api/v1/clientes', [
        'tipo_documento'   => '1',
        'numero_documento' => '12345678',
        'razon_social'     => 'CLIENTE CANONICO',
    ], tenantHeaders($tenant));

    $response->assertCreated();
    expect(Client::where('numero_documento', '12345678')->exists())->toBeTrue();
});

// ── Punto 3 ────────────────────────────────────────────────────────────────
test('POST /facturas rechaza cliente sin RUC con mensaje accionable', function () {
    $tenant = Tenant::factory()->create();

    $response = $this->postJson('/api/v1/facturas', [
        'serie'         => 'F001',
        'fecha_emision' => '2026-04-19',
        'cliente' => [
            'tipo_doc'     => '1', // DNI — inválido para factura
            'num_doc'      => '12345678',
            'razon_social' => 'PERSONA NATURAL',
        ],
        'items' => [[
            'descripcion'     => 'Producto X',
            'unidad'          => 'NIU',
            'cantidad'        => 1,
            'precio_unitario' => 100,
        ]],
    ], tenantHeaders($tenant));

    $response->assertStatus(422)
        ->assertJsonPath('estado', 'error');

    // La key es literal "cliente.tipo_doc" (con punto), no anidada.
    $errores = $response->json('errores');
    $mensajes = (array) ($errores['cliente.tipo_doc'] ?? []);
    $sugiere = collect($mensajes)->contains(
        fn ($m) => is_string($m) && str_contains($m, '/boletas')
    );

    expect($sugiere)->toBeTrue(
        'Se esperaba que algún mensaje de cliente.tipo_doc sugiera POST /boletas. Mensajes: ' . json_encode($mensajes)
    );
});

// ── Punto 4 ────────────────────────────────────────────────────────────────
test('POST /anulaciones rechaza boletas y sugiere /resumenes', function () {
    $tenant = Tenant::factory()->create();

    $response = $this->postJson('/api/v1/anulaciones', [
        'fecha_generacion'   => '2026-04-19',
        'fecha_comunicacion' => '2026-04-19',
        'detalles' => [[
            'tipo_documento' => '03',
            'serie'          => 'B001',
            'correlativo'    => '1',
            'motivo'         => 'Prueba',
        ]],
    ], tenantHeaders($tenant));

    $response->assertStatus(422)
        ->assertJsonPath('estado', 'error')
        ->assertJsonPath('codigo_error', 'boletas_no_soportadas_en_ra')
        ->assertJsonPath('siguiente_accion.endpoint', 'POST /api/v1/resumenes');
});

// ── Punto 5 ────────────────────────────────────────────────────────────────
test('PUT /facturas rechaza edición de factura aceptada con siguiente_accion', function () {
    $tenant = Tenant::factory()->create();

    $invoice = Invoice::create([
        'tenant_id'           => $tenant->id,
        'serie'               => 'F001',
        'correlativo'         => '1',
        'cod_local'           => '0000',
        'fecha_emision'       => '2026-04-19',
        'tipo_operacion'      => '0101',
        'tipo_moneda'         => 'PEN',
        'forma_pago'          => 'Contado',
        'client_tipo_doc'     => '6',
        'client_num_doc'      => '20512345678',
        'client_razon_social' => 'CLIENTE ACEPTADO',
        'sunat_status'        => 'aceptado',
    ]);

    $response = $this->putJson("/api/v1/facturas/{$invoice->id}", [
        'observacion' => 'Corrección tardía',
    ], tenantHeaders($tenant));

    $response->assertStatus(422)
        ->assertJsonPath('estado', 'error')
        ->assertJsonPath('codigo_error', 'documento_aceptado_no_editable')
        ->assertJsonPath('siguiente_accion.operacion', 'emitir_nota_credito')
        ->assertJsonPath('siguiente_accion.doc_afectado.tipo', '01')
        ->assertJsonPath('siguiente_accion.doc_afectado.serie', 'F001');

    expect((string) $response->json('siguiente_accion.doc_afectado.correlativo'))
        ->toBe('1');
});

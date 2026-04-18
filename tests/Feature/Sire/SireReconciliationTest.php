<?php

use App\Sire\Jobs\ReconcilePeriodoJob;
use App\Sire\Models\SireComprobante;
use App\Sire\Models\SirePeriodo;
use App\Sire\Models\SireReconciliationLog;
use App\Sire\Services\Reconciliation\ReconciliationService;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Sire\SireTestHelper;

uses(SireTestHelper::class);

beforeEach(function () {
    $this->setUpSunatMock();
    $this->setUpSireTenant();

    $this->periodo = SirePeriodo::create([
        'tenant_id'      => $this->tenant->id,
        'per_tributario' => '202604',
        'cod_libro'      => '080000',
        'fase'           => 'propuesta',
    ]);

    // 3 comprobantes de prueba
    foreach ([
        ['CAR001', '20100000002', 'PROV A', 1000, 180, 1180],
        ['CAR002', '20100000003', 'PROV B', 500, 90, 590],
        ['CAR003', '20100000002', 'PROV A', 2000, 360, 2360], // mismo proveedor
    ] as [$car, $ruc, $razon, $bi, $igv, $total]) {
        SireComprobante::create([
            'tenant_id'              => $this->tenant->id,
            'sire_periodo_id'        => $this->periodo->id,
            'per_tributario'         => '202604',
            'fase'                   => 'propuesta',
            'car_sunat'              => $car,
            'fec_emision'            => '2026-04-15',
            'cod_tipo_cdp'           => '01',
            'num_serie_cdp'          => 'F001',
            'num_cdp'                => '1',
            'num_doc_proveedor'      => $ruc,
            'tipo_doc_proveedor'     => '6',
            'razon_social_proveedor' => $razon,
            'mto_bi_gravada'         => $bi,
            'mto_igv'                => $igv,
            'mto_total'              => $total,
        ]);
    }
});

describe('ReconciliationService', function () {
    test('calcula totales correctamente', function () {
        $report = app(ReconciliationService::class)->run($this->tenant, '202604');

        expect($report->totales['total_comprobantes'])->toBe(3);
        expect($report->totales['suma_bi_gravada'])->toBe(3500.0);
        expect($report->totales['suma_igv'])->toBe(630.0);
        expect($report->totales['suma_total'])->toBe(4130.0);
    });

    test('agrupa por proveedor y ordena por monto', function () {
        $report = app(ReconciliationService::class)->run($this->tenant, '202604');

        expect($report->porProveedor)->toHaveCount(2);
        expect($report->porProveedor[0]['num_doc'])->toBe('20100000002'); // top: PROV A con 2 CPs
        expect($report->porProveedor[0]['cantidad_cp'])->toBe(2);
        expect($report->porProveedor[0]['suma_total'])->toBe(3540.0);
    });

    test('agrupa por tipo de comprobante', function () {
        $report = app(ReconciliationService::class)->run($this->tenant, '202604');

        expect($report->porTipo)->toHaveCount(1);
        expect($report->porTipo[0]['cod'])->toBe('01');
        expect($report->porTipo[0]['nombre'])->toBe('Factura');
        expect($report->porTipo[0]['cantidad'])->toBe(3);
    });

    test('persiste en sire_reconciliation_logs', function () {
        app(ReconciliationService::class)->run($this->tenant, '202604');

        $log = SireReconciliationLog::where('tenant_id', $this->tenant->id)->first();
        expect($log)->not->toBeNull();
        expect($log->total_sunat)->toBe(3);
        expect($log->per_tributario)->toBe('202604');
    });

    test('actualiza tenant.sire_last_period_synced', function () {
        app(ReconciliationService::class)->run($this->tenant, '202604');

        expect($this->tenant->refresh()->sire_last_period_synced)->toBe('202604');
        expect($this->tenant->refresh()->sire_last_reconciliation_at)->not->toBeNull();
    });
});

describe('Endpoints reconciliación', function () {
    test('GET /reconciliar devuelve reporte completo', function () {
        $response = $this->getJson('/api/v1/sire/rce/202604/reconciliar', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['tenant_id', 'per_tributario', 'totales', 'por_proveedor', 'por_tipo', 'cruce_local', 'alertas'],
        ]);
        $response->assertJsonPath('data.totales.total_comprobantes', 3);
    });

    test('POST /reconciliar-async encola job', function () {
        Queue::fake();

        $response = $this->postJson('/api/v1/sire/rce/202604/reconciliar-async', [], $this->headers());

        $response->assertStatus(202);
        Queue::assertPushed(ReconcilePeriodoJob::class);
    });

    test('GET /reconciliaciones lista historial', function () {
        // Generar 2 reconciliaciones
        app(ReconciliationService::class)->run($this->tenant, '202604');
        app(ReconciliationService::class)->run($this->tenant, '202604');

        $response = $this->getJson('/api/v1/sire/rce/202604/reconciliaciones', $this->headers());

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(2);
    });
});

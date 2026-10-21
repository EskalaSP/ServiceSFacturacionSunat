<?php

use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Reports\ReportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function docVenta(string $modelo, Tenant $tenant, array $attrs = []): object
{
    static $n = 0;
    $n++;

    $base = [
        'tenant_id' => $tenant->id,
        'ambiente' => 'produccion',
        'serie' => $modelo === Boleta::class ? 'B001' : ($modelo === CreditNote::class ? 'FC01' : 'F001'),
        'correlativo' => $n,
        'cod_local' => '0000',
        'fecha_emision' => '2026-08-10 15:30:00',
        'tipo_moneda' => 'PEN',
        'client_tipo_doc' => '6',
        'client_num_doc' => '20512345678',
        'client_razon_social' => 'CLIENTE SAC',
        'mto_oper_gravadas' => 100,
        'mto_igv' => 18,
        'mto_imp_venta' => 118,
        'sunat_status' => 'aceptado',
    ];
    if ($modelo === CreditNote::class) {
        $base += ['doc_afectado_tipo' => '01', 'doc_afectado_serie' => 'F001', 'doc_afectado_correlativo' => '1', 'cod_motivo' => '01', 'des_motivo' => 'Anulacion'];
    } else {
        $base += ['tipo_operacion' => '0101', 'forma_pago' => 'Contado'];
    }

    return $modelo::unguarded(fn () => $modelo::create(array_merge($base, $attrs)));
}

function qs(array $p): string
{
    return http_build_query($p);
}

const RANGO_VENTAS = ['fecha_desde' => '2026-08-01', 'fecha_hasta' => '2026-08-10'];

it('el registro de ventas muestra todo y separa los totales por ambiente y estado', function () {
    $tenant = Tenant::factory()->create();
    docVenta(Invoice::class, $tenant);
    docVenta(Boleta::class, $tenant, ['mto_imp_venta' => 50]);
    docVenta(Invoice::class, $tenant, ['sunat_status' => 'anulado', 'mto_imp_venta' => 20]);
    docVenta(Invoice::class, $tenant, ['sunat_status' => 'rechazado', 'mto_imp_venta' => 10]);
    docVenta(CreditNote::class, $tenant, ['mto_imp_venta' => 30]);
    docVenta(Invoice::class, $tenant, ['sunat_status' => 'anulacion_en_proceso']);
    docVenta(Invoice::class, $tenant, ['sunat_status' => 'pendiente']);
    docVenta(Invoice::class, $tenant, ['ambiente' => 'prueba', 'mto_imp_venta' => 1000]);
    docVenta(Invoice::class, $tenant, ['ambiente' => 'prueba', 'sunat_status' => 'rechazado', 'mto_imp_venta' => 400]);

    $r = $this->actingAs($tenant->user)->getJson('/sunat/reportes/registro-ventas?'.qs(RANGO_VENTAS))->assertOk();
    $linea = fn (string $amb, string $clave) => collect($r->json("data.bloques.{$amb}.lineas"))->firstWhere('clave', $clave)['totales'];

    expect($r->json('data.total_documentos'))->toBe(6)
        ->and(collect($r->json('data.documentos'))->pluck('tipo_doc')->unique()->sort()->values()->all())->toBe(['01', '03'])
        ->and($linea('produccion', 'aceptados'))->toMatchArray(['cantidad' => 2, 'mto_imp_venta' => 168])
        ->and($linea('produccion', 'rechazados')['mto_imp_venta'])->toBe(10)
        ->and($linea('produccion', 'anulados')['mto_imp_venta'])->toBe(20)
        ->and(collect($r->json('data.bloques.produccion.lineas'))->pluck('etiqueta')->implode(' '))->not->toContain('CRÉDITO')
        ->and(collect($r->json('data.bloques.produccion.lineas'))->pluck('clave'))->not->toContain('neto')
        ->and(collect($r->json('data.bloques.prueba.lineas'))->pluck('clave'))->not->toContain('neto');
});

it('muestra solo totales de tipos seleccionados y omite filas en cero', function () {
    $tenant = Tenant::factory()->create();
    docVenta(Invoice::class, $tenant, ['mto_imp_venta' => 200]);
    docVenta(Boleta::class, $tenant, ['mto_imp_venta' => 80]);
    docVenta(CreditNote::class, $tenant, ['mto_imp_venta' => 30]);

    $r = $this->actingAs($tenant->user)
        ->getJson('/sunat/reportes/registro-ventas?'.qs(RANGO_VENTAS + ['tipos' => ['boleta'], 'ambientes' => ['produccion']]))
        ->assertOk();

    $lineas = collect($r->json('data.bloques.produccion.lineas'));
    expect($r->json('data.total_documentos'))->toBe(1)
        ->and(array_keys($r->json('data.bloques')))->toBe(['produccion'])
        ->and($lineas->pluck('clave')->all())->toBe(['boleta', 'aceptados'])
        ->and($lineas->every(fn (array $linea) => $linea['totales']['cantidad'] > 0))->toBeTrue()
        ->and($lineas->pluck('etiqueta')->implode(' '))->not->toContain('FACTURA')->not->toContain('CRÉDITO')->not->toContain('DÉBITO');
});

it('ordena el detalle por fecha y luego serie correlativo', function () {
    $tenant = Tenant::factory()->create();
    docVenta(Invoice::class, $tenant, ['fecha_emision' => '2026-08-09 10:00:00', 'serie' => 'F002', 'correlativo' => 2]);
    docVenta(Invoice::class, $tenant, ['fecha_emision' => '2026-08-08 10:00:00', 'serie' => 'F003', 'correlativo' => 1]);
    docVenta(Invoice::class, $tenant, ['fecha_emision' => '2026-08-09 10:00:00', 'serie' => 'F001', 'correlativo' => 9]);

    $r = $this->actingAs($tenant->user)->getJson('/sunat/reportes/registro-ventas?'.qs(RANGO_VENTAS))->assertOk();

    expect(collect($r->json('data.documentos'))->pluck('numero_completo')->all())->toBe(['F003-000001', 'F001-000009', 'F002-000002']);
});

it('el reporte de ventas permite filtrar por ambiente y estado', function () {
    $tenant = Tenant::factory()->create();
    docVenta(Invoice::class, $tenant, ['sunat_status' => 'anulado']);
    docVenta(Invoice::class, $tenant, ['ambiente' => 'prueba', 'sunat_status' => 'anulado']);
    docVenta(Invoice::class, $tenant);

    $r = $this->actingAs($tenant->user)
        ->getJson('/sunat/reportes/registro-ventas?'.qs(RANGO_VENTAS + ['estados' => ['anulado'], 'ambientes' => ['prueba']]))
        ->assertOk();

    expect($r->json('data.total_documentos'))->toBe(1)
        ->and(array_keys($r->json('data.bloques')))->toBe(['prueba']);

    $r = $this->actingAs($tenant->user)
        ->getJson('/sunat/reportes/registro-ventas?'.qs(RANGO_VENTAS + ['estados' => ['anulacion_en_proceso', 'pendiente']]))
        ->assertOk();
    expect($r->json('data.total_documentos'))->toBe(0);
});

it('separa aceptado y aceptado con observaciones', function () {
    $tenant = Tenant::factory()->create();
    docVenta(Invoice::class, $tenant);
    docVenta(Invoice::class, $tenant, ['sunat_code' => '3033']);
    docVenta(Invoice::class, $tenant, ['sunat_code' => '0', 'sunat_notes' => ['4001 observacion']]);

    $url = fn (array $estados) => '/sunat/reportes/registro-ventas?'.qs(RANGO_VENTAS + ['estados' => $estados]);

    expect($this->actingAs($tenant->user)->getJson($url(['aceptado']))->json('data.total_documentos'))->toBe(1)
        ->and($this->actingAs($tenant->user)->getJson($url(['aceptado_obs']))->json('data.total_documentos'))->toBe(2)
        ->and($this->actingAs($tenant->user)->getJson($url(['aceptado', 'aceptado_obs']))->json('data.total_documentos'))->toBe(3);
});

it('normaliza mayusculas y tildes de estado y ambiente', function () {
    $tenant = Tenant::factory()->create();
    docVenta(Invoice::class, $tenant, ['sunat_status' => 'ANULADO', 'ambiente' => 'PRODUCCIÓN', 'mto_imp_venta' => 25]);

    $r = $this->actingAs($tenant->user)
        ->getJson('/sunat/reportes/registro-ventas?'.qs(RANGO_VENTAS + ['estados' => ['anulado'], 'ambientes' => ['produccion']]))
        ->assertOk();

    expect($r->json('data.total_documentos'))->toBe(1)
        ->and($r->json('data.documentos.0.estado_grupo'))->toBe('anulado')
        ->and($r->json('data.documentos.0.ambiente'))->toBe('produccion');
});

it('concilia los totales aceptados por ambiente contra una consulta SQL directa', function () {
    $tenant = Tenant::factory()->create();
    docVenta(Invoice::class, $tenant, ['mto_imp_venta' => 200]);
    docVenta(Boleta::class, $tenant, ['sunat_status' => 'rechazado', 'mto_imp_venta' => 20]);
    docVenta(Invoice::class, $tenant, ['sunat_status' => 'anulado', 'mto_imp_venta' => 30]);
    docVenta(CreditNote::class, $tenant, ['mto_imp_venta' => 50]);
    docVenta(Invoice::class, $tenant, ['ambiente' => 'prueba', 'mto_imp_venta' => 500]);
    docVenta(Boleta::class, $tenant, ['ambiente' => 'prueba', 'sunat_status' => 'rechazado', 'mto_imp_venta' => 100]);

    $respuesta = $this->actingAs($tenant->user)
        ->getJson('/sunat/reportes/registro-ventas?'.qs(RANGO_VENTAS + ['tipos' => ['factura', 'boleta', 'nota-credito', 'nota-debito']]))
        ->assertOk();

    $union = collect(['invoices' => '01', 'boletas' => '03', 'credit_notes' => '07', 'debit_notes' => '08'])
        ->map(fn (string $tipo, string $tabla) => "SELECT '{$tipo}' AS tipo, LOWER(ambiente) AS ambiente, LOWER(sunat_status) AS estado, mto_imp_venta FROM {$tabla} WHERE tenant_id = ? AND fecha_emision BETWEEN ? AND ?")
        ->implode(' UNION ALL ');
    $bindings = [];
    foreach (range(1, 4) as $_) {
        array_push($bindings, $tenant->id, '2026-08-01 00:00:00', '2026-08-10 23:59:59');
    }

    $sql = "SELECT ambiente, ROUND(SUM(CASE
                WHEN estado = 'aceptado' AND tipo <> '07' THEN mto_imp_venta
                ELSE 0 END), 2) AS total_aceptados
            FROM ({$union}) documentos GROUP BY ambiente";
    $totalesSql = collect(DB::select($sql, $bindings))->keyBy('ambiente');

    foreach (['produccion', 'prueba'] as $ambiente) {
        $totalReporte = collect($respuesta->json("data.bloques.{$ambiente}.lineas"))->firstWhere('clave', 'aceptados')['totales']['mto_imp_venta'];
        expect((float) $totalReporte)->toBe((float) $totalesSql[$ambiente]->total_aceptados);
    }
});

it('las notas de credito seleccionadas muestran subtotal sin generar netos', function () {
    $tenant = Tenant::factory()->create();
    docVenta(Invoice::class, $tenant);
    docVenta(Boleta::class, $tenant);
    docVenta(CreditNote::class, $tenant, ['mto_imp_venta' => 50, 'mto_oper_gravadas' => 42.37, 'mto_igv' => 7.63]);
    docVenta(CreditNote::class, $tenant, ['ambiente' => 'prueba', 'mto_imp_venta' => 50]);

    $r = $this->actingAs($tenant->user)
        ->getJson('/sunat/reportes/registro-ventas?'.qs(RANGO_VENTAS + ['tipos' => ['factura', 'boleta', 'nota-credito']]))
        ->assertOk();
    $lineasProduccion = collect($r->json('data.bloques.produccion.lineas'));
    $lineasPrueba = collect($r->json('data.bloques.prueba.lineas'));

    expect($lineasProduccion->firstWhere('clave', 'nota-credito')['totales']['mto_imp_venta'])->toBe(50)
        ->and($lineasPrueba->firstWhere('clave', 'nota-credito')['totales']['mto_imp_venta'])->toBe(50)
        ->and($lineasProduccion->pluck('clave'))->not->toContain('neto')
        ->and($lineasPrueba->pluck('clave'))->not->toContain('neto');
});

it('rechaza valores invalidos y rangos mayores a 366 dias', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($tenant->user)
        ->getJson('/sunat/reportes/registro-ventas?'.qs(RANGO_VENTAS + ['tipos' => ['guia']]))
        ->assertStatus(422);

    $this->actingAs($tenant->user)
        ->getJson('/sunat/reportes/registro-ventas?'.qs(['fecha_desde' => '2024-01-01', 'fecha_hasta' => '2026-01-01']))
        ->assertStatus(422);
});

it('exporta el registro de ventas a Excel y PDF', function () {
    $tenant = Tenant::factory()->create();
    docVenta(Invoice::class, $tenant, ['client_razon_social' => '=HYPERLINK("http://x")']);

    $excel = $this->actingAs($tenant->user)->get('/sunat/reportes/registro-ventas/excel?'.qs(RANGO_VENTAS))->assertOk();
    expect($excel->headers->get('content-disposition'))->toContain('.xlsx');

    docVenta(Invoice::class, $tenant, ['sunat_status' => 'anulado']);
    docVenta(Invoice::class, $tenant, ['ambiente' => 'prueba', 'sunat_status' => 'rechazado']);

    $excel = $this->actingAs($tenant->user)->get('/sunat/reportes/registro-ventas/excel?'.qs(RANGO_VENTAS))->assertOk();
    expect($excel->headers->get('content-disposition'))->toContain('.xlsx');

    $zip = new ZipArchive;
    $zip->open($excel->baseResponse->getFile()->getPathname());
    $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    expect($hoja)->toContain('autoFilter ref="A1:S4"')
        ->toContain('ySplit="1"')->toContain('topLeftCell="A2"')
        ->toContain('TOTALES PRODUCCIÓN')->toContain('TOTALES PRUEBA')->toContain('TOTAL ACEPTADOS (PRODUCCIÓN)')
        ->toContain('SUBTOTAL(109')->toContain('SUBTOTAL(103')->toContain('SUMPRODUCT(')
        ->not->toContain('NETO')->not->toContain('RESUMEN FINAL')->not->toContain('NOTA DE CRÉDITO')->not->toContain('NOTA DE DÉBITO')
        ->not->toContain('DOCUMENTOS DE')->not->toContain('<mergeCells')->not->toContain('<f>HYPERLINK');
    expect(preg_match('/<c r="B2"[^>]*><v>[0-9.]+<\/v><\/c>/', $hoja))->toBe(1)
        ->and(preg_match('/<c r="P2"[^>]*><v>[0-9.]+<\/v><\/c>/', $hoja))->toBe(1);

    $pdf = $this->actingAs($tenant->user)->get('/sunat/reportes/registro-ventas/pdf?'.qs(RANGO_VENTAS))->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf');

    $data = app(ReportService::class)->registroVentas($tenant, RANGO_VENTAS);
    $htmlPdf = view('pdf.reports.registro_ventas', $data + [
        'tenant' => ['nombre_comercial' => $tenant->razon_social, 'ruc' => $tenant->ruc],
        'generated_at' => now()->format('d/m/Y H:i:s'),
    ])->render();
    expect($htmlPdf)->not->toContain('NETO')->not->toContain('RESUMEN FINAL')->not->toContain('NOTA DE CRÉDITO')->not->toContain('NOTA DE DÉBITO')->not->toContain('DOCUMENTOS DE');

    docVenta(CreditNote::class, $tenant, ['mto_imp_venta' => 30]);
    $filtrosNc = RANGO_VENTAS + ['tipos' => ['nota-credito']];
    $excelNc = $this->actingAs($tenant->user)->get('/sunat/reportes/registro-ventas/excel?'.qs($filtrosNc))->assertOk();
    $zip = new ZipArchive;
    $zip->open($excelNc->baseResponse->getFile()->getPathname());
    $hojaNc = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    expect($hojaNc)->toContain('NOTA DE CRÉDITO')->not->toContain('NETO')->not->toContain('NOTA DE DÉBITO');

    $dataNc = app(ReportService::class)->registroVentas($tenant, $filtrosNc);
    $htmlPdfNc = view('pdf.reports.registro_ventas', $dataNc + [
        'tenant' => ['nombre_comercial' => $tenant->razon_social, 'ruc' => $tenant->ruc],
        'generated_at' => now()->format('d/m/Y H:i:s'),
    ])->render();
    expect($htmlPdfNc)->toContain('NOTA DE CRÉDITO')->not->toContain('NETO')->not->toContain('NOTA DE DÉBITO');
});

it('el ZIP por defecto incluye solo aceptados y permite elegir otros estados', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();

    foreach (['aceptado', 'anulado', 'rechazado'] as $estado) {
        $d = docVenta(Invoice::class, $tenant, ['sunat_status' => $estado]);
        $d->update(['xml_path' => "xml/{$d->id}.xml"]);
        Storage::disk('public')->put("xml/{$d->id}.xml", '<x/>');
    }

    $entradas = function (array $extra) use ($tenant): array {
        $res = $this->actingAs($tenant->user)->get('/sunat/exportar/descargar?'.qs(RANGO_VENTAS + $extra))->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'z').'.zip';
        file_put_contents($tmp, $res->streamedContent());
        $zip = new ZipArchive;
        $zip->open($tmp);
        $n = $zip->numFiles;
        $zip->close();
        unlink($tmp);

        return [$n];
    };

    expect($entradas([]))->toBe([1])
        ->and($entradas(['estados' => ['aceptado', 'anulado', 'rechazado']]))->toBe([3]);
});

it('el ZIP distingue sin documentos de documentos sin archivos', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();

    $vacio = $this->actingAs($tenant->user)->get('/sunat/exportar/descargar?'.qs(RANGO_VENTAS))->assertStatus(404);
    expect($vacio->json('mensaje'))->toContain('No hay documentos');

    docVenta(Invoice::class, $tenant);
    $sinArchivos = $this->actingAs($tenant->user)->get('/sunat/exportar/descargar?'.qs(RANGO_VENTAS))->assertStatus(404);
    expect($sinArchivos->json('mensaje'))->toContain('ninguno tiene archivos');
});

it('la API legada con solo fechas sigue respondiendo y el parametro estado antiguo se respeta', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();
    $d = docVenta(Invoice::class, $tenant, ['sunat_status' => 'rechazado']);
    $d->update(['xml_path' => 'xml/r.xml']);
    Storage::disk('public')->put('xml/r.xml', '<x/>');

    $this->actingAs($tenant->user)->get('/sunat/exportar/descargar?'.qs(RANGO_VENTAS))->assertStatus(404);
    $this->actingAs($tenant->user)->get('/sunat/exportar/descargar?'.qs(RANGO_VENTAS + ['estado' => 'rechazado']))->assertOk();
});

it('el historial filtra por varios tipos, estados y ambiente', function () {
    $tenant = Tenant::factory()->create(['sol_user' => 'MODDATOS']);
    docVenta(Invoice::class, $tenant);
    docVenta(Invoice::class, $tenant, ['sunat_status' => 'anulado']);
    docVenta(Boleta::class, $tenant, ['ambiente' => 'prueba']);

    $this->actingAs($tenant->user)
        ->get('/sunat/historial?'.qs(['tipos' => ['facturas', 'boletas'], 'estados' => ['anulado'], 'ambientes' => ['produccion']]))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('sunat/historial')->has('documentos.data', 1));

    $this->actingAs($tenant->user)
        ->get('/sunat/historial?'.qs(['tipos' => ['boletas'], 'ambientes' => ['prueba', 'produccion']]))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('documentos.data', 1));
});

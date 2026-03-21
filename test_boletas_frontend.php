<?php
/**
 * Test Boletas — Simulando exactamente el payload del formulario InvoiceForm.tsx en modo boleta
 * Envía a platform-app proxy (port 8001) que reenvía a API-PRO (port 8000)
 */

$token = $argv[1] ?? null;
if (!$token) { echo "Usage: php test_boletas_frontend.php <token>\n"; exit(1); }

$BASE = 'http://localhost:8001/api/businesses/1/boletas';
$today = date('Y-m-d');

// ═══════════════════════════════════════════════════════════
// 16 CASOS — Exactamente como los genera el formulario frontend
// ═══════════════════════════════════════════════════════════

$cases = [];

// ── 01. Gravado estándar (10) — Cliente con DNI ──
$cases[] = ['name' => 'Gravado (10) - DNI', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '1', 'num_doc' => '47858586', 'razon_social' => 'JUAN PEREZ LOPEZ'],
    'items' => [[
        'descripcion' => 'Producto gravado boleta',
        'unidad' => 'NIU',
        'cantidad' => 2,
        'precio_unitario' => 50.00,
        'tip_afe_igv' => '10',
    ]],
]];

// ── 02. Exonerado (20) ──
$cases[] = ['name' => 'Exonerado (20)', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '1', 'num_doc' => '47858586', 'razon_social' => 'JUAN PEREZ LOPEZ'],
    'items' => [[
        'descripcion' => 'Servicio educativo exonerado',
        'unidad' => 'ZZ',
        'cantidad' => 1,
        'precio_unitario' => 200.00,
        'tip_afe_igv' => '20',
    ]],
]];

// ── 03. Inafecto (30) ──
$cases[] = ['name' => 'Inafecto (30)', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '1', 'num_doc' => '47858586', 'razon_social' => 'JUAN PEREZ LOPEZ'],
    'items' => [[
        'descripcion' => 'Alquiler inafecto',
        'unidad' => 'ZZ',
        'cantidad' => 1,
        'precio_unitario' => 500.00,
        'tip_afe_igv' => '30',
    ]],
]];

// ── 04. Mixto (10+20+30) — 3 items con distintas afectaciones ──
$cases[] = ['name' => 'Mixto (10+20+30)', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '1', 'num_doc' => '47858586', 'razon_social' => 'JUAN PEREZ LOPEZ'],
    'items' => [
        ['descripcion' => 'Producto gravado', 'unidad' => 'NIU', 'cantidad' => 1, 'precio_unitario' => 118.00, 'tip_afe_igv' => '10'],
        ['descripcion' => 'Servicio exonerado', 'unidad' => 'ZZ', 'cantidad' => 1, 'precio_unitario' => 50.00, 'tip_afe_igv' => '20'],
        ['descripcion' => 'Bien inafecto', 'unidad' => 'NIU', 'cantidad' => 1, 'precio_unitario' => 30.00, 'tip_afe_igv' => '30'],
    ],
]];

// ── 05. ICBPER (bolsas plásticas) ──
$cases[] = ['name' => 'ICBPER (bolsas)', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '1', 'num_doc' => '47858586', 'razon_social' => 'JUAN PEREZ LOPEZ'],
    'items' => [
        ['descripcion' => 'Producto con IGV', 'unidad' => 'NIU', 'cantidad' => 1, 'precio_unitario' => 59.00, 'tip_afe_igv' => '10'],
        ['descripcion' => 'Bolsa plástica', 'unidad' => 'NIU', 'cantidad' => 3, 'precio_unitario' => 0.50, 'tip_afe_igv' => '10', 'icbper' => 1.50, 'factor_icbper' => 0.50],
    ],
]];

// ── 06. ISC (sistema al valor) ──
$cases[] = ['name' => 'ISC (al valor)', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '1', 'num_doc' => '47858586', 'razon_social' => 'JUAN PEREZ LOPEZ'],
    'items' => [[
        'descripcion' => 'Bebida azucarada con ISC',
        'unidad' => 'NIU',
        'cantidad' => 10,
        'precio_unitario' => 5.00,
        'tip_afe_igv' => '10',
        'isc' => 4.24,
        'tip_sis_isc' => '01',
    ]],
]];

// ── 07. Gratuita gravada (11) ──
$cases[] = ['name' => 'Gratuita Gravada (11)', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '1', 'num_doc' => '47858586', 'razon_social' => 'JUAN PEREZ LOPEZ'],
    'items' => [
        ['descripcion' => 'Producto pagado', 'unidad' => 'NIU', 'cantidad' => 1, 'precio_unitario' => 100.00, 'tip_afe_igv' => '10'],
        ['descripcion' => 'Regalo promocional', 'unidad' => 'NIU', 'cantidad' => 1, 'precio_unitario' => 30.00, 'tip_afe_igv' => '11'],
    ],
]];

// ── 08. Gratuita inafecta (31) ──
$cases[] = ['name' => 'Gratuita Inafecta (31)', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '1', 'num_doc' => '47858586', 'razon_social' => 'JUAN PEREZ LOPEZ'],
    'items' => [
        ['descripcion' => 'Servicio pagado', 'unidad' => 'ZZ', 'cantidad' => 1, 'precio_unitario' => 80.00, 'tip_afe_igv' => '10'],
        ['descripcion' => 'Muestra gratuita', 'unidad' => 'NIU', 'cantidad' => 2, 'precio_unitario' => 15.00, 'tip_afe_igv' => '31'],
    ],
]];

// ── 09. Crédito + cuotas ──
$cases[] = ['name' => 'Credito + cuotas', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Credito',
    'cliente' => ['tipo_doc' => '1', 'num_doc' => '47858586', 'razon_social' => 'JUAN PEREZ LOPEZ'],
    'items' => [[
        'descripcion' => 'Electrodoméstico a crédito',
        'unidad' => 'NIU',
        'cantidad' => 1,
        'precio_unitario' => 590.00,
        'tip_afe_igv' => '10',
    ]],
    'cuotas' => [
        ['monto' => 295.00, 'fecha_pago' => date('Y-m-d', strtotime('+30 days'))],
        ['monto' => 295.00, 'fecha_pago' => date('Y-m-d', strtotime('+60 days'))],
    ],
]];

// ── 10. Descuento por item ──
$cases[] = ['name' => 'Desc. por Item', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '1', 'num_doc' => '47858586', 'razon_social' => 'JUAN PEREZ LOPEZ'],
    'items' => [[
        'descripcion' => 'Producto con 10% descuento',
        'unidad' => 'NIU',
        'cantidad' => 5,
        'precio_unitario' => 100.00,
        'tip_afe_igv' => '10',
        'descuentos' => [['cod_tipo' => '00', 'monto_base' => 500.00, 'factor' => 0.10, 'monto' => 50.00]],
    ]],
]];

// ── 11. Cliente genérico (tipo_doc=0) < S/700 ──
$cases[] = ['name' => 'Genérico < S/700', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '0', 'num_doc' => '00000000', 'razon_social' => 'CLIENTE VARIOS'],
    'items' => [[
        'descripcion' => 'Venta mostrador',
        'unidad' => 'NIU',
        'cantidad' => 1,
        'precio_unitario' => 25.00,
        'tip_afe_igv' => '10',
    ]],
]];

// ── 12. Cliente con RUC ──
$cases[] = ['name' => 'Cliente RUC (6)', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '6', 'num_doc' => '20609113753', 'razon_social' => 'EMPRESA DEMO SAC', 'direccion' => 'Av. Test 123'],
    'items' => [[
        'descripcion' => 'Servicio corporativo',
        'unidad' => 'ZZ',
        'cantidad' => 1,
        'precio_unitario' => 354.00,
        'tip_afe_igv' => '10',
    ]],
]];

// ── 13. Moneda USD ──
$cases[] = ['name' => 'USD', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'USD',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '1', 'num_doc' => '47858586', 'razon_social' => 'JUAN PEREZ LOPEZ'],
    'items' => [[
        'descripcion' => 'Producto en dólares',
        'unidad' => 'NIU',
        'cantidad' => 1,
        'precio_unitario' => 59.00,
        'tip_afe_igv' => '10',
    ]],
]];

// ── 14. S/700 exacto con genérico (debe pasar) ──
$cases[] = ['name' => 'S/700 exacto genérico', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '0', 'num_doc' => '00000000', 'razon_social' => 'CLIENTE VARIOS'],
    'items' => [[
        'descripcion' => 'Producto exacto 700',
        'unidad' => 'NIU',
        'cantidad' => 1,
        'precio_unitario' => 700.00,
        'tip_afe_igv' => '10',
    ]],
]];

// ── 15. > S/700 genérico (DEBE SER RECHAZADO por validación) ──
$cases[] = ['name' => '> S/700 genérico (DEBE FALLAR)', 'expect_fail' => true, 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '0', 'num_doc' => '00000000', 'razon_social' => 'CLIENTE VARIOS'],
    'items' => [[
        'descripcion' => 'Producto caro sin DNI',
        'unidad' => 'NIU',
        'cantidad' => 1,
        'precio_unitario' => 800.00,
        'tip_afe_igv' => '10',
    ]],
]];

// ── 16. Guía + leyenda extra + observación ──
$cases[] = ['name' => 'Guía + leyenda + obs', 'payload' => [
    'fecha_emision' => $today,
    'tipo_moneda' => 'PEN',
    'serie' => 'B001',
    'tipo_operacion' => '0101',
    'forma_pago' => 'Contado',
    'cliente' => ['tipo_doc' => '1', 'num_doc' => '47858586', 'razon_social' => 'JUAN PEREZ LOPEZ'],
    'items' => [[
        'descripcion' => 'Producto con guía',
        'unidad' => 'NIU',
        'cantidad' => 3,
        'precio_unitario' => 45.00,
        'tip_afe_igv' => '10',
    ]],
    'guias' => [['tipo_doc' => '09', 'nro_doc' => 'T001-1']],
    'leyendas' => [['code' => '2006', 'value' => 'Leyenda personalizada boleta']],
    'leyenda' => 'Observación de prueba boleta',
]];

// ═══════════════════════════════════════════════════════════
// EJECUTAR TESTS
// ═══════════════════════════════════════════════════════════

echo "=== BOLETA FRONTEND TEST — " . count($cases) . " Cases via Proxy ===\n";
echo "Endpoint: $BASE\n\n";

echo "--- STEP 1: Creating boletas via proxy ---\n";
$ids = [];
$expectedFails = 0;

foreach ($cases as $i => $case) {
    $num = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
    $expectFail = $case['expect_fail'] ?? false;

    $ch = curl_init($BASE);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            "Authorization: Bearer $token",
        ],
        CURLOPT_POSTFIELDS => json_encode($case['payload']),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $body = json_decode($resp, true);

    if ($expectFail) {
        if ($code === 422 || $code === 400) {
            echo "  OK   $num. {$case['name']} -> Correctly rejected (HTTP $code)\n";
            $expectedFails++;
        } else {
            echo "  WARN $num. {$case['name']} -> Expected rejection but got HTTP $code\n";
            if (isset($body['data']['id'])) $ids[$i] = $body['data']['id'];
        }
    } else {
        if ($code === 200 || $code === 201) {
            $id = $body['data']['id'] ?? $body['id'] ?? null;
            if ($id) {
                $ids[$i] = $id;
                echo "  OK   $num. {$case['name']} -> id=$id\n";
            } else {
                echo "  FAIL $num. {$case['name']} -> Created but no ID in response\n";
            }
        } else {
            $err = $body['message'] ?? $body['error'] ?? 'Unknown';
            echo "  FAIL $num. {$case['name']} -> HTTP $code: $err\n";
            if (!empty($body['errors'])) {
                foreach ($body['errors'] as $field => $msgs) {
                    echo "       - $field: " . (is_array($msgs) ? implode(', ', $msgs) : $msgs) . "\n";
                }
            }
        }
    }
}

echo "\nCreated: " . count($ids) . " | Expected rejections: $expectedFails | Unexpected failures: " . (count($cases) - count($ids) - $expectedFails) . "\n";

if (empty($ids)) {
    echo "\nNo boletas created. Aborting.\n";
    exit(1);
}

// ── STEP 2: Process queue ──
echo "\n--- STEP 2: Processing SUNAT queue ---\n";
exec('cd "C:\\laragon\\www\\API-PRO" && php artisan queue:work --once --queue=default 2>&1', $out);
// Process multiple times to handle all jobs
for ($j = 0; $j < count($ids) + 2; $j++) {
    exec('cd "C:\\laragon\\www\\API-PRO" && php artisan queue:work --once --queue=default 2>&1');
}

// Small pause for DB writes
sleep(2);

// ── STEP 3: Check SUNAT results ──
echo "\n--- STEP 3: SUNAT Results ---\n";
$pass = $obs = $fail = $pend = 0;

foreach ($ids as $i => $id) {
    $num = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
    $name = $cases[$i]['name'];

    // Read from API-PRO directly
    $ch = curl_init("http://localhost:8000/api/v1/boletas/$id");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-Api-Key: 33',
            'X-Api-Secret: HB4gr0vXebp2ylOyBaFTV85N9msHWxIo2Zwgdowy0f3ccea4',
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $body = json_decode($resp, true);
    $doc = $body['data'] ?? $body;

    $status = $doc['sunat_status'] ?? 'pending';
    $sunatCode = $doc['sunat_code'] ?? '';
    $sunatDesc = $doc['sunat_description'] ?? '';

    if ($status === 'aceptado') {
        if ($sunatCode && $sunatCode !== '0' && !str_starts_with($sunatCode, '0')) {
            echo "  OBS  $num. $name: $sunatCode - $sunatDesc\n";
            $obs++;
        } else {
            echo "  PASS $num. $name\n";
            $pass++;
        }
    } elseif ($status === 'rechazado') {
        echo "  FAIL $num. $name: $sunatCode - $sunatDesc\n";
        $fail++;
    } else {
        echo "  PEND $num. $name (status=$status)\n";
        $pend++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "PASS: $pass | OBS: $obs | FAIL: $fail | PEND: $pend | TOTAL: " . count($ids) . "\n";
echo "Acceptance: " . ($pass + $obs) . "/" . count($ids) . " (" . round(($pass + $obs) / count($ids) * 100) . "%)\n";
echo "Validation: S/700 rule = " . ($expectedFails === 1 ? 'OK' : 'FAILED') . "\n";

if ($fail === 0 && $pend === 0) {
    echo "\n*** ALL BOLETAS ACCEPTED BY SUNAT ***\n";
} elseif ($pend > 0) {
    echo "\nSome boletas still pending. Run queue again: php artisan queue:work --once\n";
}

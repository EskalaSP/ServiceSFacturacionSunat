<?php

/**
 * SECCIÓN 05 — BOLETAS (TIPO 03)
 */

declare(strict_types=1);

$baseFile = __DIR__ . '/../../API SUNAT PRO V2.1 ✅✅✅✅✅.postman_collection.json';
$existing = file_exists($baseFile) ? (json_decode(file_get_contents($baseFile), true) ?: ['item' => []]) : ['item' => []];

$boletasOriginal = null;
foreach ($existing['item'] as $f) {
    if (str_contains($f['name'], 'Boletas')) {
        $boletasOriginal = $f;
        break;
    }
}

// Aplanar items existentes (la folder original tiene sub-folders)
$existingFlat = [];
foreach ($boletasOriginal['item'] ?? [] as $sub) {
    if (isset($sub['item'])) {
        // Sub-folder: tomar todos sus items pero NO el sub-folder de "Resumen diario" (eso va a otra sección)
        if (!str_contains($sub['name'] ?? '', 'Resumen')) {
            foreach ($sub['item'] as $it) $existingFlat[] = $it;
        }
    } else {
        $existingFlat[] = $sub;
    }
}

$boletasNuevas = [
    // Casos comunes
    reqJson('Boleta básica (DNI, contado)', 'POST', 'boletas', [
        'serie' => 'B001',
        'fecha_emision' => '2026-04-19',
        'tipo_moneda' => 'PEN',
        'forma_pago' => 'Contado',
        'cliente' => [
            'tipo_doc' => '1',
            'num_doc' => '12345678',
            'razon_social' => 'JUAN PEREZ LOPEZ',
        ],
        'items' => [[
            'codigo' => 'P001',
            'descripcion' => 'Pollo a la brasa',
            'unidad' => 'NIU',
            'cantidad' => 1,
            'precio_unitario' => 35.00,
            'tip_afe_igv' => '10',
        ]],
    ]),

    reqJson('Boleta consumidor final (sin DNI)', 'POST', 'boletas', [
        'serie' => 'B001',
        'fecha_emision' => '2026-04-19',
        'cliente' => [
            'tipo_doc' => '0',
            'num_doc' => '00000000',
            'razon_social' => 'CONSUMIDOR FINAL',
        ],
        'items' => [[
            'codigo' => 'P002',
            'descripcion' => 'Producto varios',
            'unidad' => 'NIU',
            'cantidad' => 1,
            'precio_unitario' => 12.00,
            'tip_afe_igv' => '10',
        ]],
    ], 'tipo_doc=0 (Otros) para consumidor final sin DNI. SUNAT lo acepta para boletas < S/ 700.'),

    reqJson('Boleta NRUS (tipo_operacion 0113 — auto)', 'POST', 'boletas', [
        'serie' => 'B001',
        'fecha_emision' => '2026-04-19',
        'cliente' => [
            'tipo_doc' => '1',
            'num_doc' => '12345678',
            'razon_social' => 'CLIENTE NRUS',
        ],
        'items' => [[
            'codigo' => 'P001',
            'descripcion' => 'Producto NRUS',
            'unidad' => 'NIU',
            'cantidad' => 1,
            'precio_unitario' => 50.00,
            'tip_afe_igv' => '10',
        ]],
    ], 'Si el tenant tiene tax_regime=nrus, tipo_operacion se setea auto a 0113 e IGV=0.'),

    reqJson('Boleta MYPE Restaurantes (10.5% en 2026)', 'POST', 'boletas', [
        'serie' => 'B001',
        'fecha_emision' => '2026-04-19',
        'cliente' => [
            'tipo_doc' => '1',
            'num_doc' => '12345678',
            'razon_social' => 'CLIENTE RESTAURANTE',
        ],
        'items' => [[
            'codigo' => 'PLATO001',
            'descripcion' => 'Almuerzo ejecutivo',
            'unidad' => 'NIU',
            'cantidad' => 1,
            'precio_unitario' => 27.50,
            'tip_afe_igv' => '10',
        ]],
    ], 'Si el tenant tiene tax_regime=mype_restaurantes, el sistema aplica 10.5% (2026-2029) automáticamente según fecha.'),

    reqJson('Boleta solo registro (envío vía resumen)', 'POST', 'boletas', [
        'serie' => 'B001',
        'fecha_emision' => '2026-04-19',
        'cliente' => [
            'tipo_doc' => '1',
            'num_doc' => '12345678',
            'razon_social' => 'CLIENTE',
        ],
        'items' => [[
            'codigo' => 'P001', 'descripcion' => 'Producto', 'unidad' => 'NIU',
            'cantidad' => 1, 'precio_unitario' => 10.00, 'tip_afe_igv' => '10',
        ]],
        'solo_registro' => true,
    ], 'Marca la boleta como pendiente de resumen diario (no se envía individualmente).'),

    reqJson('Boleta envío manual (enviar_automatico=false)', 'POST', 'boletas', [
        'serie' => 'B001',
        'fecha_emision' => '2026-04-19',
        'cliente' => [
            'tipo_doc' => '1',
            'num_doc' => '12345678',
            'razon_social' => 'CLIENTE',
        ],
        'items' => [[
            'codigo' => 'P001', 'descripcion' => 'Producto', 'unidad' => 'NIU',
            'cantidad' => 1, 'precio_unitario' => 10.00, 'tip_afe_igv' => '10',
        ]],
        'enviar_automatico' => false,
    ], 'La boleta queda en "pendiente". Llamar luego POST /boletas/{id}/enviar para enviarla.'),

    // Gestión
    reqSimple('Listar boletas', 'GET', 'boletas?por_pagina=15'),
    reqSimple('Listar boletas pendientes (para resumen)', 'GET', 'boletas?sunat_status=pendiente'),
    reqSimple('Ver boleta por ID', 'GET', 'boletas/1'),
    reqJson('Actualizar boleta', 'PUT', 'boletas/1', [
        'cliente' => ['tipo_doc' => '1', 'num_doc' => '12345678', 'razon_social' => 'CLIENTE CORREGIDO'],
    ]),
    reqSimple('Eliminar boleta (solo si no aceptada)', 'DELETE', 'boletas/1'),
    reqSimple('Descargar XML', 'GET', 'boletas/1/xml'),
    reqSimple('Descargar CDR', 'GET', 'boletas/1/cdr'),
    reqSimple('Descargar PDF A4', 'GET', 'boletas/1/pdf?format=a4'),
    reqSimple('Descargar PDF Ticket 80mm', 'GET', 'boletas/1/pdf?format=ticket-80'),
    reqSimple('Enviar a SUNAT (manual)', 'POST', 'boletas/1/enviar'),
    reqSimple('Reenviar a SUNAT', 'POST', 'boletas/1/reenviar'),

    // Pagos en boletas
    reqJson('Registrar pago en boleta', 'POST', 'boletas/1/pagos', [
        'monto' => 35.00,
        'fecha' => '2026-04-19',
        'metodo' => 'efectivo',
    ]),
    reqSimple('Listar pagos de boleta', 'GET', 'boletas/1/pagos'),
    reqSimple('Eliminar pago', 'DELETE', 'boletas/1/pagos/1'),
];

return [
    'name' => '05. Boletas (Tipo 03)',
    'description' => 'CRUD + ejemplos para régimen general, NRUS, MYPE Restaurantes, solo_registro y envío manual. Lee 05-Boletas.md.',
    'item' => array_merge($existingFlat, $boletasNuevas),
];

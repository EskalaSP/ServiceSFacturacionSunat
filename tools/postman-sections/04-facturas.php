<?php

/**
 * SECCIÓN 04 — FACTURAS (TIPO 01)
 *
 * Reusa los 26 ejemplos existentes de la colección base + agrega endpoints
 * de gestión (GET, PUT, XML, CDR, PDF, enviar/reenviar, pagos).
 */

declare(strict_types=1);

$baseFile = __DIR__ . '/../../API SUNAT PRO V2.1 ✅✅✅✅✅.postman_collection.json';
$existing = file_exists($baseFile) ? (json_decode(file_get_contents($baseFile), true) ?: ['item' => []]) : ['item' => []];

// Buscar la folder existente "Facturas ✅"
$facturasOriginal = null;
foreach ($existing['item'] as $f) {
    if (str_contains($f['name'], 'Facturas')) {
        $facturasOriginal = $f;
        break;
    }
}

$existingItems = $facturasOriginal['item'] ?? [];

// === Ejemplos de creación ===
$ejemplos = [
    reqJson('Crear factura básica (RUC, 2 items)', 'POST', 'facturas', [
        'serie' => 'F001',
        'fecha_emision' => '2026-04-19',
        'tipo_operacion' => '0101',
        'tipo_moneda' => 'PEN',
        'forma_pago' => 'Contado',
        'cliente' => [
            'tipo_doc' => '6',
            'num_doc' => '20512345678',
            'razon_social' => 'CLIENTE DEMO SAC',
            'direccion' => 'AV. AREQUIPA 1234, LIMA',
        ],
        'items' => [
            [
                'codigo' => 'P001',
                'descripcion' => 'Laptop HP 15.6 pulgadas',
                'unidad' => 'NIU',
                'cantidad' => 2,
                'precio_unitario' => 2360.00,
                'tip_afe_igv' => '10',
            ],
            [
                'codigo' => 'P002',
                'descripcion' => 'Mouse inalámbrico Logitech',
                'unidad' => 'NIU',
                'cantidad' => 5,
                'precio_unitario' => 59.00,
                'tip_afe_igv' => '10',
            ],
        ],
    ], 'tipo_operacion 0101=Venta interna. tip_afe_igv 10=Gravado operación onerosa.'),

    reqJson('Crear factura crédito (con cuotas)', 'POST', 'facturas', [
        'serie' => 'F001',
        'fecha_emision' => '2026-04-19',
        'fecha_vencimiento' => '2026-05-19',
        'tipo_operacion' => '0101',
        'tipo_moneda' => 'PEN',
        'forma_pago' => 'Credito',
        'cliente' => ['tipo_doc' => '6', 'num_doc' => '20512345678', 'razon_social' => 'CLIENTE SAC'],
        'items' => [[
            'codigo' => 'P001', 'descripcion' => 'Producto', 'unidad' => 'NIU',
            'cantidad' => 10, 'precio_unitario' => 118.00, 'tip_afe_igv' => '10',
        ]],
        'cuotas' => [
            ['monto' => 590.00, 'fecha_pago' => '2026-05-19'],
            ['monto' => 590.00, 'fecha_pago' => '2026-06-19'],
        ],
    ]),

    reqJson('Crear factura exonerada (servicios médicos)', 'POST', 'facturas', [
        'serie' => 'F001',
        'fecha_emision' => '2026-04-19',
        'tipo_operacion' => '0101',
        'tipo_moneda' => 'PEN',
        'forma_pago' => 'Contado',
        'cliente' => ['tipo_doc' => '6', 'num_doc' => '20512345678', 'razon_social' => 'CLIENTE SAC'],
        'items' => [[
            'codigo' => 'SERV001', 'descripcion' => 'Consulta médica especializada',
            'unidad' => 'ZZ', 'cantidad' => 1, 'precio_unitario' => 200.00,
            'tip_afe_igv' => '20',
        ]],
    ], 'tip_afe_igv 20=Exonerado. No aplica IGV (sector salud, educación).'),

    reqJson('Crear factura exportación (tipo 0200)', 'POST', 'facturas', [
        'serie' => 'F001',
        'fecha_emision' => '2026-04-19',
        'tipo_operacion' => '0200',
        'tipo_moneda' => 'USD',
        'forma_pago' => 'Contado',
        'cliente' => ['tipo_doc' => '0', 'num_doc' => '00000000', 'razon_social' => 'FOREIGN CLIENT INC'],
        'items' => [[
            'codigo' => 'EXP001', 'descripcion' => 'Producto exportación',
            'unidad' => 'NIU', 'cantidad' => 100, 'precio_unitario' => 50.00,
            'tip_afe_igv' => '40',
        ]],
    ], 'tipo_operacion 0200=Exportación. tip_afe_igv 40=Exportación. Moneda USD típica.'),

    reqJson('Factura envío manual (enviar_automatico=false)', 'POST', 'facturas', [
        'serie' => 'F001',
        'fecha_emision' => '2026-04-19',
        'tipo_operacion' => '0101',
        'tipo_moneda' => 'PEN',
        'forma_pago' => 'Contado',
        'cliente' => ['tipo_doc' => '6', 'num_doc' => '20512345678', 'razon_social' => 'CLIENTE SAC'],
        'items' => [[
            'codigo' => 'P001', 'descripcion' => 'Producto', 'unidad' => 'NIU',
            'cantidad' => 1, 'precio_unitario' => 118.00, 'tip_afe_igv' => '10',
        ]],
        'enviar_automatico' => false,
    ], 'Queda en pendiente. Llamar POST /facturas/{id}/enviar para enviar a SUNAT.'),
];

// === Ítems de gestión (CRUD, downloads, pagos) ===
$nuevosGestion = [
    reqSimple('Listar facturas', 'GET', 'facturas?por_pagina=15',
        'Soporta filtros: serie, correlativo, fecha_desde, fecha_hasta, sunat_status, client_num_doc, sucursal_id.'),

    reqSimple('Listar facturas con items y pagos', 'GET', 'facturas?con=items,payments&por_pagina=15'),

    reqSimple('Filtrar facturas rechazadas', 'GET', 'facturas?sunat_status=rechazado'),

    reqSimple('Buscar factura por correlativo', 'GET', 'facturas?serie=F001&correlativo=1'),

    reqSimple('Ver factura por ID', 'GET', 'facturas/1'),

    reqJson('Actualizar factura (corregir y reenviar)', 'PUT', 'facturas/1', [
        'cliente' => [
            'tipo_doc' => '6',
            'num_doc' => '20512345678',
            'razon_social' => 'EMPRESA CORREGIDA SAC',
        ],
        'items' => [[
            'codigo' => 'P001',
            'descripcion' => 'Producto corregido',
            'unidad' => 'NIU',
            'cantidad' => 1,
            'precio_unitario' => 118.00,
            'tip_afe_igv' => '10',
        ]],
    ], 'Solo permitido si NO está aceptada. Auto-reenvía a SUNAT.'),

    reqSimple('Descargar XML', 'GET', 'facturas/1/xml'),
    reqSimple('Descargar CDR (ZIP de SUNAT)', 'GET', 'facturas/1/cdr'),
    reqSimple('Descargar PDF (A4)', 'GET', 'facturas/1/pdf?format=a4'),
    reqSimple('Descargar PDF (A5)', 'GET', 'facturas/1/pdf?format=a5'),
    reqSimple('Descargar PDF (Ticket 80mm)', 'GET', 'facturas/1/pdf?format=ticket-80'),
    reqSimple('Descargar PDF (Ticket 58mm)', 'GET', 'facturas/1/pdf?format=ticket-58'),

    reqSimple('Enviar a SUNAT (manual)', 'POST', 'facturas/1/enviar',
        'Para facturas creadas con enviar_automatico=false. Encola el job. Acepta status pendiente o rechazado.'),

    reqSimple('Reenviar a SUNAT (alias)', 'POST', 'facturas/1/reenviar',
        'Alias retro-compatible de /enviar.'),

    // Pagos
    reqJson('Registrar pago en factura', 'POST', 'facturas/1/pagos', [
        'monto' => 118.00,
        'fecha' => '2026-04-19',
        'metodo' => 'transferencia',
        'referencia' => 'OP 12345',
        'observacion' => 'Pago contra entrega',
    ]),
    reqJson('Registrar pago parcial', 'POST', 'facturas/1/pagos', [
        'monto' => 50.00,
        'fecha' => '2026-04-19',
        'metodo' => 'efectivo',
    ]),
    reqSimple('Listar pagos de la factura', 'GET', 'facturas/1/pagos'),
    reqSimple('Eliminar pago', 'DELETE', 'facturas/1/pagos/1'),
];

// Combinar: ejemplos + existentes (si los hay) + nuevos de gestión
$itemsCompletos = array_merge($ejemplos, $existingItems, $nuevosGestion);

return [
    'name' => '04. Facturas (Tipo 01)',
    'description' => 'CRUD completo de facturas + 19 ejemplos reales (básica, crédito, exonerada, inafecta, gratuita, exportación, detracción, percepción, retención, anticipos, descuentos, ICBPER, ISC, IVAP, contingencia, etc.). Ver 04-Facturas.md.',
    'item' => $itemsCompletos,
];

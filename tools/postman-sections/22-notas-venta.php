<?php

/**
 * SECCIÓN 22 — NOTAS DE VENTA (DOCUMENTO INTERNO, NO SUNAT)
 */

declare(strict_types=1);

$items = [
    reqJson('Crear nota de venta', 'POST', 'notas-venta', [
        'fecha_emision' => '2026-04-19',
        'tipo_moneda' => 'PEN',
        'forma_pago' => 'Crédito',
        'cliente' => [
            'tipo_doc' => '6',
            'num_doc' => '20512345678',
            'razon_social' => 'CLIENTE SAC',
        ],
        'items' => [[
            'codigo' => 'P001',
            'descripcion' => 'Producto interno',
            'unidad' => 'NIU',
            'cantidad' => 5,
            'precio_unitario' => 50.00,
            'tip_afe_igv' => '10',
        ]],
        'observacion' => 'Nota de venta interna — pendiente facturación',
    ], 'Documento interno para tracking de ventas. NO se envía a SUNAT.'),

    reqSimple('Listar notas de venta', 'GET', 'notas-venta?por_pagina=15'),
    reqSimple('Ver nota de venta por ID', 'GET', 'notas-venta/1'),
    reqJson('Actualizar nota de venta', 'PUT', 'notas-venta/1', [
        'observacion' => 'Actualizada — entregado',
    ]),
    reqSimple('Descargar PDF', 'GET', 'notas-venta/1/pdf?format=a4'),

    // Pagos en notas de venta
    reqJson('Registrar pago en nota de venta', 'POST', 'notas-venta/1/pagos', [
        'monto' => 295.00,
        'fecha' => '2026-04-19',
        'metodo' => 'transferencia',
        'referencia' => 'OP-12345',
    ]),
    reqSimple('Listar pagos de nota de venta', 'GET', 'notas-venta/1/pagos'),
    reqSimple('Eliminar pago', 'DELETE', 'notas-venta/1/pagos/1'),
];

return [
    'name' => '22. Notas de Venta (interno)',
    'description' => 'Documento interno con pagos asociables. NO se envía a SUNAT. Útil para tracking pre-facturación.',
    'item' => $items,
];

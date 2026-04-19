<?php

/**
 * SECCIÓN 21 — COTIZACIONES (DOCUMENTO INTERNO, NO SUNAT)
 */

declare(strict_types=1);

$items = [
    reqJson('Crear cotización', 'POST', 'cotizaciones', [
        'fecha_emision' => '2026-04-19',
        'fecha_validez' => '2026-05-19',
        'tipo_moneda' => 'PEN',
        'cliente' => [
            'tipo_doc' => '6',
            'num_doc' => '20512345678',
            'razon_social' => 'CLIENTE PROSPECTO SAC',
            'email' => 'compras@cliente.pe',
        ],
        'items' => [[
            'codigo' => 'P001',
            'descripcion' => 'Servicio de consultoría',
            'unidad' => 'ZZ',
            'cantidad' => 1,
            'precio_unitario' => 1180.00,
            'tip_afe_igv' => '10',
        ]],
        'observacion' => 'Cotización válida por 30 días. Precios incluyen IGV.',
    ], 'Documento interno (NO se envía a SUNAT). Útil para preventa.'),

    reqSimple('Listar cotizaciones', 'GET', 'cotizaciones?por_pagina=15'),
    reqSimple('Filtrar por estado', 'GET', 'cotizaciones?estado=aceptada'),
    reqSimple('Ver cotización por ID', 'GET', 'cotizaciones/1'),
    reqJson('Actualizar cotización', 'PUT', 'cotizaciones/1', [
        'fecha_validez' => '2026-06-19',
        'observacion' => 'Validez extendida 30 días más',
    ]),
    reqJson('Cambiar estado de cotización', 'PUT', 'cotizaciones/1/estado', [
        'estado' => 'aceptada',
    ], 'Estados: borrador | enviada | aceptada | rechazada | vencida.'),
    reqSimple('Descargar PDF', 'GET', 'cotizaciones/1/pdf?format=a4'),
];

return [
    'name' => '21. Cotizaciones (interno)',
    'description' => 'Documento interno para preventa. NO se envía a SUNAT. Cuenta como uso "internal" del plan, no "sunat".',
    'item' => $items,
];

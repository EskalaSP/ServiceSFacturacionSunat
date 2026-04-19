<?php

/**
 * SECCIÓN 13 — ACTUALIZAR DOCUMENTOS RECHAZADOS / PENDIENTES
 *
 * Lee 13-Actualizar.md.
 */

declare(strict_types=1);

$items = [
    reqJson('Corregir factura rechazada (cliente)', 'PUT', 'facturas/1', [
        'cliente' => [
            'tipo_doc' => '6',
            'num_doc' => '20512345678',
            'razon_social' => 'EMPRESA CORREGIDA SAC',
            'direccion' => 'AV. NUEVA 456',
        ],
    ], 'PUT auto-reenvía a SUNAT. Solo permitido si NO está aceptada.'),

    reqJson('Corregir factura rechazada (items)', 'PUT', 'facturas/1', [
        'items' => [[
            'codigo' => 'P001',
            'descripcion' => 'Producto corregido',
            'unidad' => 'NIU',
            'cantidad' => 2,
            'precio_unitario' => 118.00,
            'tip_afe_igv' => '10',
        ]],
    ], 'Reemplaza items y recalcula totales.'),

    reqJson('Corregir boleta rechazada', 'PUT', 'boletas/1', [
        'cliente' => [
            'tipo_doc' => '1',
            'num_doc' => '12345678',
            'razon_social' => 'JUAN PEREZ CORREGIDO',
        ],
    ]),

    reqJson('Corregir nota de crédito', 'PUT', 'notas-credito/1', [
        'cod_motivo' => '06',
        'des_motivo' => 'Devolución total — corregido',
    ]),

    reqJson('Corregir nota de débito', 'PUT', 'notas-debito/1', [
        'des_motivo' => 'Intereses recalculados',
    ]),

    reqJson('Corregir guía de remisión', 'PUT', 'guias-remision/1', [
        'transportista' => [
            'tipo_doc' => '6',
            'num_doc' => '20987654321',
            'razon_social' => 'TRANSPORTES CORREGIDO',
        ],
        'vehiculo' => ['placa' => 'XYZ-789'],
    ], 'Solo permitido si está pendiente o rechazada.'),
];

return [
    'name' => '13. Actualizar documentos',
    'description' => 'PUT a documentos en estado pendiente/rechazado. Auto-reenvía a SUNAT. NO funciona en aceptados (usar NC/RA). Lee 13-Actualizar.md.',
    'item' => $items,
];

<?php

/**
 * SECCIÓN 18 — RETENCIONES (TIPO 20)
 */

declare(strict_types=1);

$items = [
    reqJson('Crear retención (régimen general 3%)', 'POST', 'retenciones', [
        'serie' => 'R001',
        'fecha_emision' => '2026-04-19',
        'cod_local' => '0000',
        'proveedor' => [
            'tipo_doc' => '6',
            'num_doc' => '20987654321',
            'razon_social' => 'PROVEEDOR SAC',
            'direccion' => 'AV. PROVEEDOR 100',
        ],
        'regimen' => '01',
        'tasa' => 3,
        'observacion' => 'Retención por servicios profesionales',
        'documentos' => [[
            'tipo_doc' => '01',
            'num_doc' => 'F001-100',
            'fecha_emision' => '2026-04-15',
            'imp_total' => 1180.00,
            'moneda' => 'PEN',
            'pagos' => [[
                'fecha' => '2026-04-19',
                'imp_total' => 1180.00,
            ]],
            'fecha_retencion' => '2026-04-19',
        ]],
    ], 'Cat. 23 régimen: 01=Tasa Especial 3%. La API calcula imp_retenido si no se envía.'),

    reqJson('Crear retención múltiples documentos', 'POST', 'retenciones', [
        'serie' => 'R001',
        'fecha_emision' => '2026-04-19',
        'cod_local' => '0000',
        'proveedor' => [
            'tipo_doc' => '6', 'num_doc' => '20987654321', 'razon_social' => 'PROVEEDOR SAC',
        ],
        'regimen' => '01',
        'tasa' => 3,
        'documentos' => [
            [
                'tipo_doc' => '01', 'num_doc' => 'F001-100',
                'fecha_emision' => '2026-04-15', 'imp_total' => 590.00, 'moneda' => 'PEN',
                'pagos' => [['fecha' => '2026-04-19', 'imp_total' => 590.00]],
                'fecha_retencion' => '2026-04-19',
            ],
            [
                'tipo_doc' => '01', 'num_doc' => 'F001-101',
                'fecha_emision' => '2026-04-16', 'imp_total' => 1180.00, 'moneda' => 'PEN',
                'pagos' => [['fecha' => '2026-04-19', 'imp_total' => 1180.00]],
                'fecha_retencion' => '2026-04-19',
            ],
        ],
    ]),

    reqJson('Retención envío manual', 'POST', 'retenciones', [
        'serie' => 'R001',
        'fecha_emision' => '2026-04-19',
        'cod_local' => '0000',
        'proveedor' => ['tipo_doc' => '6', 'num_doc' => '20987654321', 'razon_social' => 'PROVEEDOR SAC'],
        'regimen' => '01', 'tasa' => 3,
        'documentos' => [[
            'tipo_doc' => '01', 'num_doc' => 'F001-100',
            'fecha_emision' => '2026-04-15', 'imp_total' => 1180.00, 'moneda' => 'PEN',
            'pagos' => [['fecha' => '2026-04-19', 'imp_total' => 1180.00]],
            'fecha_retencion' => '2026-04-19',
        ]],
        'enviar_automatico' => false,
    ]),

    reqSimple('Listar retenciones', 'GET', 'retenciones?per_page=15'),
    reqSimple('Filtrar retenciones por proveedor', 'GET', 'retenciones?proveedor_num_doc=20987654321'),
    reqSimple('Ver retención por ID', 'GET', 'retenciones/1'),
    reqSimple('Descargar XML', 'GET', 'retenciones/1/xml'),
    reqSimple('Descargar CDR', 'GET', 'retenciones/1/cdr'),
    reqSimple('Enviar a SUNAT (manual)', 'POST', 'retenciones/1/enviar'),
];

return [
    'name' => '18. Retenciones (Tipo 20)',
    'description' => 'Comprobante de retención del IGV. El comprador retiene 3% al proveedor (régimen general). Solo lo emiten Agentes de Retención designados por SUNAT.',
    'item' => $items,
];

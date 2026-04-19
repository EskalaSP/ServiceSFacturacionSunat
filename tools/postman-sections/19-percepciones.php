<?php

/**
 * SECCIÓN 19 — PERCEPCIONES (TIPO 40)
 */

declare(strict_types=1);

$items = [
    reqJson('Crear percepción 1% (venta interna)', 'POST', 'percepciones', [
        'serie' => 'P001',
        'fecha_emision' => '2026-04-19',
        'cod_local' => '0000',
        'cliente' => [
            'tipo_doc' => '6',
            'num_doc' => '20987654321',
            'razon_social' => 'CLIENTE SAC',
            'direccion' => 'AV. CLIENTE 100',
        ],
        'regimen' => '01',
        'tasa' => 1,
        'observacion' => 'Percepción venta interna',
        'documentos' => [[
            'tipo_doc' => '01',
            'num_doc' => 'F001-100',
            'fecha_emision' => '2026-04-15',
            'imp_total' => 1180.00,
            'moneda' => 'PEN',
            'cobros' => [[
                'fecha' => '2026-04-19',
                'imp_total' => 1180.00,
            ]],
            'fecha_percepcion' => '2026-04-19',
        ]],
    ], 'Cat. 22 régimen: 01=Venta interna. Tasas: 0.5% entre agentes percepción, 1% bienes consumo masivo, 2% combustible.'),

    reqJson('Crear percepción 2% (combustible)', 'POST', 'percepciones', [
        'serie' => 'P001',
        'fecha_emision' => '2026-04-19',
        'cod_local' => '0000',
        'cliente' => ['tipo_doc' => '6', 'num_doc' => '20987654321', 'razon_social' => 'CLIENTE SAC'],
        'regimen' => '02',
        'tasa' => 2,
        'documentos' => [[
            'tipo_doc' => '01', 'num_doc' => 'F001-100',
            'fecha_emision' => '2026-04-15', 'imp_total' => 5900.00, 'moneda' => 'PEN',
            'cobros' => [['fecha' => '2026-04-19', 'imp_total' => 5900.00]],
            'fecha_percepcion' => '2026-04-19',
        ]],
    ], 'Régimen 02 = Combustible. Tasa 2%.'),

    reqJson('Percepción envío manual', 'POST', 'percepciones', [
        'serie' => 'P001',
        'fecha_emision' => '2026-04-19',
        'cod_local' => '0000',
        'cliente' => ['tipo_doc' => '6', 'num_doc' => '20987654321', 'razon_social' => 'CLIENTE SAC'],
        'regimen' => '01', 'tasa' => 1,
        'documentos' => [[
            'tipo_doc' => '01', 'num_doc' => 'F001-100',
            'fecha_emision' => '2026-04-15', 'imp_total' => 1180.00, 'moneda' => 'PEN',
            'cobros' => [['fecha' => '2026-04-19', 'imp_total' => 1180.00]],
            'fecha_percepcion' => '2026-04-19',
        ]],
        'enviar_automatico' => false,
    ]),

    reqSimple('Listar percepciones', 'GET', 'percepciones?per_page=15'),
    reqSimple('Filtrar percepciones por cliente', 'GET', 'percepciones?cliente_num_doc=20987654321'),
    reqSimple('Ver percepción por ID', 'GET', 'percepciones/1'),
    reqSimple('Descargar XML', 'GET', 'percepciones/1/xml'),
    reqSimple('Descargar CDR', 'GET', 'percepciones/1/cdr'),
    reqSimple('Enviar a SUNAT (manual)', 'POST', 'percepciones/1/enviar'),
];

return [
    'name' => '19. Percepciones (Tipo 40)',
    'description' => 'Comprobante de percepción del IGV. El vendedor percibe un % adicional al cliente. Solo lo emiten Agentes de Percepción designados por SUNAT.',
    'item' => $items,
];

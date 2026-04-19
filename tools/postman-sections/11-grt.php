<?php

/**
 * SECCIÓN 11 — GUÍA DE REMISIÓN TRANSPORTISTA (GRT — Tipo 31)
 *
 * Sección NUEVA — emite la empresa transportista cuando lleva mercancía de terceros.
 * Lee 11-Guia-transportista.md para detalles.
 */

declare(strict_types=1);

$items = [
    // Atajo: endpoint dedicado (forza tipo_documento=31)
    reqJson('GRT — Transporte estándar (atajo)', 'POST', 'guias-remision-transportista', [
        'serie' => 'V001',
        'fecha_emision' => '2026-04-19',
        'remitente' => [
            'tipo_doc' => '6',
            'num_doc' => '20512345678',
            'razon_social' => 'EMPRESA REMITENTE SAC',
        ],
        'destinatario' => [
            'tipo_doc' => '6',
            'num_doc' => '20987654321',
            'razon_social' => 'EMPRESA DESTINATARIA SAC',
        ],
        'doc_relacionado' => [
            'tipo_codigo' => '04',
            'numero' => 'T001-1',
        ],
        'cod_traslado' => '01',
        'mod_traslado' => '01',
        'fecha_traslado' => '2026-04-20',
        'peso_total' => 500.00,
        'und_peso_total' => 'KGM',
        'num_bultos' => 5,
        'partida_ubigeo' => '150101',
        'partida_direccion' => 'AV. ALMACEN 100, LIMA',
        'llegada_ubigeo' => '040101',
        'llegada_direccion' => 'AV. DESTINO 200, AREQUIPA',
        'vehiculo' => [
            'placa' => 'ABC-123',
            'nro_circulacion' => 'TUC-12345',
        ],
        'conductor' => [[
            'tipo_doc' => '1',
            'num_doc' => '12345678',
            'nombres' => 'JUAN',
            'apellidos' => 'PEREZ',
            'licencia' => 'Q12345678',
        ]],
        'items' => [[
            'codigo' => 'M001',
            'descripcion' => 'Mercancia transportada',
            'unidad' => 'NIU',
            'cantidad' => 10,
        ]],
    ], 'Endpoint atajo: forza tipo_documento=31. La API requiere remitente (RUC) y doc_relacionado (factura/GRR del remitente).'),

    // Endpoint general con tipo_documento explícito
    reqJson('GRT — Vía endpoint general (tipo_documento=31)', 'POST', 'guias-remision', [
        'tipo_documento' => '31',
        'serie' => 'V001',
        'fecha_emision' => '2026-04-19',
        'remitente' => [
            'tipo_doc' => '6', 'num_doc' => '20512345678', 'razon_social' => 'EMPRESA REMITENTE SAC',
        ],
        'destinatario' => [
            'tipo_doc' => '6', 'num_doc' => '20987654321', 'razon_social' => 'EMPRESA DESTINATARIA SAC',
        ],
        'doc_relacionado' => ['tipo_codigo' => '04', 'numero' => 'T001-1'],
        'cod_traslado' => '01',
        'mod_traslado' => '01',
        'fecha_traslado' => '2026-04-20',
        'peso_total' => 500.00, 'und_peso_total' => 'KGM',
        'partida_ubigeo' => '150101', 'partida_direccion' => 'AV. ALMACEN 100, LIMA',
        'llegada_ubigeo' => '040101', 'llegada_direccion' => 'AV. DESTINO 200, AREQUIPA',
        'vehiculo' => ['placa' => 'ABC-123', 'nro_circulacion' => 'TUC-12345'],
        'conductor' => [[
            'tipo_doc' => '1', 'num_doc' => '12345678',
            'nombres' => 'JUAN', 'apellidos' => 'PEREZ', 'licencia' => 'Q12345678',
        ]],
        'items' => [['codigo' => 'M001', 'descripcion' => 'Mercancia', 'unidad' => 'NIU', 'cantidad' => 10]],
    ], 'Mismo resultado que el atajo, solo cambia la URL.'),

    reqJson('GRT — Subcontratado (yo subcontraté a otro transportista)', 'POST', 'guias-remision-transportista', [
        'serie' => 'V001',
        'fecha_emision' => '2026-04-19',
        'remitente' => [
            'tipo_doc' => '6', 'num_doc' => '20512345678', 'razon_social' => 'EMPRESA REMITENTE SAC',
        ],
        'destinatario' => [
            'tipo_doc' => '6', 'num_doc' => '20987654321', 'razon_social' => 'EMPRESA DESTINATARIA SAC',
        ],
        'doc_relacionado' => ['tipo_codigo' => '04', 'numero' => 'T001-1'],
        'cod_traslado' => '01', 'mod_traslado' => '01',
        'fecha_traslado' => '2026-04-20',
        'peso_total' => 500.00, 'und_peso_total' => 'KGM',
        'partida_ubigeo' => '150101', 'partida_direccion' => 'AV. ALMACEN 100',
        'llegada_ubigeo' => '040101', 'llegada_direccion' => 'AV. DESTINO 200',
        'vehiculo' => ['placa' => 'ABC-123'],
        'conductor' => [[
            'tipo_doc' => '1', 'num_doc' => '12345678',
            'nombres' => 'JUAN', 'apellidos' => 'PEREZ', 'licencia' => 'Q12345678',
        ]],
        'items' => [['codigo' => 'M001', 'descripcion' => 'Mercancia', 'unidad' => 'NIU', 'cantidad' => 10]],
        'datos_subcontratador' => [
            'tipo_doc' => '6',
            'num_doc' => '20111222333',
            'razon_social' => 'TRANSPORTES SUBCONTRATADO SAC',
        ],
    ], 'Si subcontratas el transporte, registrar en datos_subcontratador. La API setea automáticamente el indicador.'),

    reqJson('GRT — Pagador del flete = tercero', 'POST', 'guias-remision-transportista', [
        'serie' => 'V001',
        'fecha_emision' => '2026-04-19',
        'remitente' => [
            'tipo_doc' => '6', 'num_doc' => '20512345678', 'razon_social' => 'EMPRESA REMITENTE SAC',
        ],
        'destinatario' => [
            'tipo_doc' => '6', 'num_doc' => '20987654321', 'razon_social' => 'EMPRESA DESTINATARIA SAC',
        ],
        'doc_relacionado' => ['tipo_codigo' => '04', 'numero' => 'T001-1'],
        'cod_traslado' => '01', 'mod_traslado' => '01',
        'fecha_traslado' => '2026-04-20',
        'peso_total' => 500.00, 'und_peso_total' => 'KGM',
        'partida_ubigeo' => '150101', 'partida_direccion' => 'AV. ALMACEN 100',
        'llegada_ubigeo' => '040101', 'llegada_direccion' => 'AV. DESTINO 200',
        'vehiculo' => ['placa' => 'ABC-123'],
        'conductor' => [[
            'tipo_doc' => '1', 'num_doc' => '12345678',
            'nombres' => 'JUAN', 'apellidos' => 'PEREZ', 'licencia' => 'Q12345678',
        ]],
        'items' => [['codigo' => 'M001', 'descripcion' => 'Mercancia', 'unidad' => 'NIU', 'cantidad' => 10]],
        'datos_pagador_flete' => [
            'tipo' => 'tercero',
            'tipo_doc' => '6',
            'num_doc' => '20444555666',
            'razon_social' => 'EMPRESA QUE PAGA EL FLETE SAC',
        ],
    ], 'tipo: remitente | destinatario | tercero. Si es tercero, datos del tercero requeridos.'),

    reqJson('GRT — Múltiples conductores y vehículos secundarios', 'POST', 'guias-remision-transportista', [
        'serie' => 'V001',
        'fecha_emision' => '2026-04-19',
        'remitente' => [
            'tipo_doc' => '6', 'num_doc' => '20512345678', 'razon_social' => 'EMPRESA REMITENTE SAC',
        ],
        'destinatario' => [
            'tipo_doc' => '6', 'num_doc' => '20987654321', 'razon_social' => 'EMPRESA DESTINATARIA SAC',
        ],
        'doc_relacionado' => ['tipo_codigo' => '04', 'numero' => 'T001-1'],
        'cod_traslado' => '01', 'mod_traslado' => '01',
        'fecha_traslado' => '2026-04-20',
        'peso_total' => 1000.00, 'und_peso_total' => 'KGM',
        'partida_ubigeo' => '150101', 'partida_direccion' => 'AV. ALMACEN 100',
        'llegada_ubigeo' => '040101', 'llegada_direccion' => 'AV. DESTINO 200',
        'vehiculo' => [
            'placa' => 'ABC-123',
            'nro_circulacion' => 'TUC-12345',
            'secundarios' => [
                ['placa' => 'XYZ-456', 'nro_circulacion' => 'TUC-67890'],
            ],
        ],
        'conductor' => [
            ['tipo_doc' => '1', 'num_doc' => '12345678', 'nombres' => 'JUAN', 'apellidos' => 'PEREZ', 'licencia' => 'Q12345678'],
            ['tipo_doc' => '1', 'num_doc' => '87654321', 'nombres' => 'PEDRO', 'apellidos' => 'GOMEZ', 'licencia' => 'Q87654321'],
        ],
        'items' => [['codigo' => 'M001', 'descripcion' => 'Mercancia', 'unidad' => 'NIU', 'cantidad' => 20]],
    ], 'Recorridos largos suelen necesitar varios conductores y/o vehículos secundarios (acoplados).'),

    reqJson('GRT — DAM (importación/exportación)', 'POST', 'guias-remision-transportista', [
        'serie' => 'V001',
        'fecha_emision' => '2026-04-19',
        'remitente' => [
            'tipo_doc' => '6', 'num_doc' => '20512345678', 'razon_social' => 'IMPORTADORA SAC',
        ],
        'destinatario' => [
            'tipo_doc' => '6', 'num_doc' => '20987654321', 'razon_social' => 'CLIENTE NACIONAL SAC',
        ],
        'doc_relacionado' => [
            'tipo_codigo' => '50',
            'numero' => '118-2026-10-123456',
        ],
        'cod_traslado' => '08',
        'mod_traslado' => '01',
        'fecha_traslado' => '2026-04-20',
        'peso_total' => 2000.00, 'und_peso_total' => 'KGM',
        'partida_ubigeo' => '070101', 'partida_direccion' => 'PUERTO DEL CALLAO',
        'llegada_ubigeo' => '150101', 'llegada_direccion' => 'AV. ALMACEN CLIENTE 100',
        'vehiculo' => ['placa' => 'ABC-123'],
        'conductor' => [['tipo_doc' => '1', 'num_doc' => '12345678', 'nombres' => 'JUAN', 'apellidos' => 'PEREZ', 'licencia' => 'Q12345678']],
        'items' => [['codigo' => 'IMP001', 'descripcion' => 'Mercancía importada', 'unidad' => 'NIU', 'cantidad' => 50]],
    ], 'Cat. 61 tipo_codigo: 50=DAM, 04=factura, 49=DUA, 52=otros. cod_traslado=08 importación.'),

    reqJson('GRT — Envío manual', 'POST', 'guias-remision-transportista', [
        'serie' => 'V001',
        'fecha_emision' => '2026-04-19',
        'remitente' => ['tipo_doc' => '6', 'num_doc' => '20512345678', 'razon_social' => 'REMITENTE SAC'],
        'destinatario' => ['tipo_doc' => '6', 'num_doc' => '20987654321', 'razon_social' => 'DESTINATARIO SAC'],
        'doc_relacionado' => ['tipo_codigo' => '04', 'numero' => 'T001-1'],
        'cod_traslado' => '01', 'mod_traslado' => '01',
        'fecha_traslado' => '2026-04-20',
        'peso_total' => 500.00, 'und_peso_total' => 'KGM',
        'partida_ubigeo' => '150101', 'partida_direccion' => 'AV. ALMACEN 100',
        'llegada_ubigeo' => '040101', 'llegada_direccion' => 'AV. DESTINO 200',
        'vehiculo' => ['placa' => 'ABC-123'],
        'conductor' => [['tipo_doc' => '1', 'num_doc' => '12345678', 'nombres' => 'JUAN', 'apellidos' => 'PEREZ', 'licencia' => 'Q12345678']],
        'items' => [['codigo' => 'M001', 'descripcion' => 'Mercancia', 'unidad' => 'NIU', 'cantidad' => 10]],
        'enviar_automatico' => false,
    ], 'Para revisión visual antes de enviar a SUNAT.'),

    reqSimple('Listar GRT', 'GET', 'guias-remision?tipo_documento=31'),
    reqSimple('Ver GRT por ID', 'GET', 'guias-remision/1'),
    reqSimple('Descargar XML', 'GET', 'guias-remision/1/xml'),
    reqSimple('Descargar PDF', 'GET', 'guias-remision/1/pdf?format=a4'),
    reqSimple('Consultar estado SUNAT', 'GET', 'guias-remision/1/estado'),
    reqSimple('Enviar a SUNAT (manual)', 'POST', 'guias-remision/1/enviar'),
];

return [
    'name' => '11. Guía Remisión Transportista (Tipo 31)',
    'description' => 'GRT — emite el transportista cuando lleva mercancía de terceros. Requiere remitente RUC + doc_relacionado (factura/GRR). Funciona en producción (no en beta SUNAT). Lee 11-Guia-transportista.md.',
    'item' => $items,
];

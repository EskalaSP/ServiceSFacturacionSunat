<?php

/**
 * SECCIÓN 10 — GUÍA DE REMISIÓN REMITENTE (GRR — Tipo 09)
 *
 * Reusa los 7 ejemplos existentes + agrega gestión.
 */

declare(strict_types=1);

$baseFile = __DIR__ . '/../../API SUNAT PRO V2.1 ✅✅✅✅✅.postman_collection.json';
$existing = file_exists($baseFile) ? (json_decode(file_get_contents($baseFile), true) ?: ['item' => []]) : ['item' => []];

$grrOriginal = null;
foreach ($existing['item'] as $f) {
    if (str_contains($f['name'], 'remisión RM') || str_contains($f['name'], 'remision RM')) {
        $grrOriginal = $f;
        break;
    }
}

$grrGestion = [
    reqJson('GRR envío manual (enviar_automatico=false)', 'POST', 'guias-remision', [
        'serie' => 'T001',
        'fecha_emision' => '2026-04-19',
        'destinatario' => [
            'tipo_doc' => '6', 'num_doc' => '20512345678', 'razon_social' => 'CLIENTE FINAL SAC',
        ],
        'cod_traslado' => '01',
        'mod_traslado' => '02',
        'fecha_traslado' => '2026-04-20',
        'peso_total' => 100.5,
        'und_peso_total' => 'KGM',
        'partida_ubigeo' => '150101',
        'partida_direccion' => 'AV. ALMACEN 100, LIMA',
        'llegada_ubigeo' => '040101',
        'llegada_direccion' => 'AV. DESTINO 200, AREQUIPA',
        'transportista' => [
            'tipo_doc' => '6',
            'num_doc' => '20987654321',
            'razon_social' => 'TRANSPORTES XYZ SAC',
        ],
        'vehiculo' => ['placa' => 'ABC-123'],
        'conductor' => [
            'tipo_doc' => '1', 'num_doc' => '12345678',
            'nombres' => 'JUAN', 'apellidos' => 'PEREZ', 'licencia' => 'Q12345678',
        ],
        'items' => [[
            'codigo' => 'P001', 'descripcion' => 'Cajas de productos',
            'unidad' => 'NIU', 'cantidad' => 10,
        ]],
        'enviar_automatico' => false,
    ]),

    reqSimple('Listar guías de remisión', 'GET', 'guias-remision?por_pagina=15'),
    reqSimple('Filtrar guías por estado', 'GET', 'guias-remision?sunat_status=aceptado'),
    reqJson('Actualizar guía rechazada', 'PUT', 'guias-remision/1', [
        'transportista' => [
            'tipo_doc' => '6', 'num_doc' => '20987654321', 'razon_social' => 'TRANSPORTES CORREGIDO SAC',
        ],
    ]),
    reqSimple('Descargar XML', 'GET', 'guias-remision/1/xml'),
    reqSimple('Descargar PDF', 'GET', 'guias-remision/1/pdf?format=a4'),
    reqSimple('Consultar estado SUNAT (vía ticket)', 'GET', 'guias-remision/1/estado',
        'Las guías usan REST API GRE (no SOAP). Hay que consultar el ticket para saber el resultado.'),
    reqSimple('Enviar a SUNAT (manual)', 'POST', 'guias-remision/1/enviar'),
];

return [
    'name' => '10. Guía Remisión Remitente (Tipo 09)',
    'description' => 'GRR — emite el remitente. Modalidades: 01=transporte público, 02=transporte privado. Cat. 20 motivos traslado. Lee 10-Guia-remision-RM.md.',
    'item' => array_merge($grrOriginal['item'] ?? [], $grrGestion),
];

<?php

/**
 * SECCIÓN 09 — COMUNICACIÓN DE BAJA (RA) — Facturas, NC, ND, Retenciones, Percepciones
 */

declare(strict_types=1);

$baseFile = __DIR__ . '/../../API SUNAT PRO V2.1 ✅✅✅✅✅.postman_collection.json';
$existing = file_exists($baseFile) ? (json_decode(file_get_contents($baseFile), true) ?: ['item' => []]) : ['item' => []];

$anularOriginal = null;
foreach ($existing['item'] as $f) {
    if (str_contains($f['name'], 'Anular')) {
        $anularOriginal = $f;
        break;
    }
}

$existingFlat = [];
foreach ($anularOriginal['item'] ?? [] as $sub) {
    if (isset($sub['item'])) {
        if (!str_contains($sub['name'] ?? '', 'Boletas')) {
            // Las anulaciones de boletas van en Resumen Diario
            foreach ($sub['item'] as $it) $existingFlat[] = $it;
        }
    } else {
        $existingFlat[] = $sub;
    }
}

$anularNuevas = [
    reqJson('Anular factura (RA estándar)', 'POST', 'anulaciones', [
        'fecha_generacion' => '2026-04-19',
        'fecha_comunicacion' => '2026-04-19',
        'detalles' => [
            ['tipo_documento' => '01', 'serie' => 'F001', 'correlativo' => '5', 'motivo' => 'Error en datos del cliente'],
        ],
    ], 'Cat. 01 tipos: 01=factura, 03=boleta, 07=NC, 08=ND. Plazo: 7 días desde emisión.'),

    reqJson('Anular múltiples documentos en lote', 'POST', 'anulaciones', [
        'fecha_generacion' => '2026-04-19',
        'fecha_comunicacion' => '2026-04-19',
        'detalles' => [
            ['tipo_documento' => '01', 'serie' => 'F001', 'correlativo' => '5', 'motivo' => 'Error cliente'],
            ['tipo_documento' => '01', 'serie' => 'F001', 'correlativo' => '6', 'motivo' => 'Anulación solicitada'],
            ['tipo_documento' => '07', 'serie' => 'FC01', 'correlativo' => '2', 'motivo' => 'NC errónea'],
            ['tipo_documento' => '08', 'serie' => 'FD01', 'correlativo' => '1', 'motivo' => 'ND errónea'],
        ],
    ]),

    reqJson('Anular nota de crédito', 'POST', 'anulaciones', [
        'fecha_generacion' => '2026-04-19',
        'detalles' => [
            ['tipo_documento' => '07', 'serie' => 'FC01', 'correlativo' => '3', 'motivo' => 'NC duplicada'],
        ],
    ]),

    reqJson('Anular envío manual (enviar_automatico=false)', 'POST', 'anulaciones', [
        'fecha_generacion' => '2026-04-19',
        'detalles' => [
            ['tipo_documento' => '01', 'serie' => 'F001', 'correlativo' => '7', 'motivo' => 'Error'],
        ],
        'enviar_automatico' => false,
    ]),

    reqSimple('Listar comunicaciones de baja', 'GET', 'anulaciones?por_pagina=15'),
    reqSimple('Filtrar por estado', 'GET', 'anulaciones?estado=aceptado'),
    reqSimple('Ver comunicación por ID', 'GET', 'anulaciones/1'),
    reqSimple('Consultar estado en SUNAT', 'GET', 'anulaciones/1/estado'),
    reqSimple('Enviar a SUNAT (manual)', 'POST', 'anulaciones/1/enviar'),
];

return [
    'name' => '09. Comunicación de Baja (RA / RR)',
    'description' => 'Anula facturas, NC, ND, retenciones y percepciones aceptadas por SUNAT. Plazo: 7 días desde emisión. Para boletas usar resumen diario. Lee 09-Anular.md.',
    'item' => array_merge($existingFlat, $anularNuevas),
];

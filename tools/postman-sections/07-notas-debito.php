<?php

/**
 * SECCIÓN 07 — NOTAS DE DÉBITO (TIPO 08)
 */

declare(strict_types=1);

$baseFile = __DIR__ . '/../../API SUNAT PRO V2.1 ✅✅✅✅✅.postman_collection.json';
$existing = file_exists($baseFile) ? (json_decode(file_get_contents($baseFile), true) ?: ['item' => []]) : ['item' => []];

$ndOriginal = null;
foreach ($existing['item'] as $f) {
    if (str_contains($f['name'], 'Notas de débito')) {
        $ndOriginal = $f;
        break;
    }
}

$ndNuevas = [
    reqJson('ND envío manual', 'POST', 'notas-debito', [
        'serie' => 'FD01',
        'fecha_emision' => '2026-04-19',
        'tipo_moneda' => 'PEN',
        'cliente' => [
            'tipo_doc' => '6', 'num_doc' => '20512345678', 'razon_social' => 'CLIENTE SAC',
        ],
        'doc_afectado_tipo' => '01',
        'doc_afectado_serie' => 'F001',
        'doc_afectado_correlativo' => '1',
        'cod_motivo' => '01',
        'des_motivo' => 'Intereses por mora',
        'items' => [[
            'codigo' => 'INT001', 'descripcion' => 'Intereses por mora 30 días',
            'unidad' => 'ZZ', 'cantidad' => 1, 'precio_unitario' => 118.00,
            'tip_afe_igv' => '10',
        ]],
        'enviar_automatico' => false,
    ]),

    reqSimple('Listar notas de débito', 'GET', 'notas-debito?por_pagina=15'),
    reqSimple('Ver ND por ID', 'GET', 'notas-debito/1'),
    reqJson('Actualizar ND rechazada', 'PUT', 'notas-debito/1', [
        'des_motivo' => 'Intereses corregidos',
    ]),
    reqSimple('Descargar XML', 'GET', 'notas-debito/1/xml'),
    reqSimple('Descargar CDR', 'GET', 'notas-debito/1/cdr'),
    reqSimple('Descargar PDF', 'GET', 'notas-debito/1/pdf?format=a4'),
    reqSimple('Enviar a SUNAT (manual)', 'POST', 'notas-debito/1/enviar'),
    reqSimple('Reenviar a SUNAT', 'POST', 'notas-debito/1/reenviar'),
];

return [
    'name' => '07. Notas de Débito (Tipo 08)',
    'description' => 'ND para intereses, penalidades, aumentos. Cat. 10 motivos: 01=intereses por mora, 02=aumento valor, 03=penalidades. Lee 07-Notas-debito.md.',
    'item' => array_merge($ndOriginal['item'] ?? [], $ndNuevas),
];

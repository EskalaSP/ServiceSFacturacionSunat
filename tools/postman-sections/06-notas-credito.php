<?php

/**
 * SECCIÓN 06 — NOTAS DE CRÉDITO (TIPO 07)
 */

declare(strict_types=1);

$baseFile = __DIR__ . '/../../API SUNAT PRO V2.1 ✅✅✅✅✅.postman_collection.json';
$existing = file_exists($baseFile) ? (json_decode(file_get_contents($baseFile), true) ?: ['item' => []]) : ['item' => []];

$ncOriginal = null;
foreach ($existing['item'] as $f) {
    if (str_contains($f['name'], 'Notas de credito')) {
        $ncOriginal = $f;
        break;
    }
}

$ncNuevas = [
    reqJson('NC envío manual (enviar_automatico=false)', 'POST', 'notas-credito', [
        'serie' => 'FC01',
        'fecha_emision' => '2026-04-19',
        'tipo_moneda' => 'PEN',
        'cliente' => [
            'tipo_doc' => '6', 'num_doc' => '20512345678', 'razon_social' => 'CLIENTE SAC',
        ],
        'doc_afectado_tipo' => '01',
        'doc_afectado_serie' => 'F001',
        'doc_afectado_correlativo' => '1',
        'cod_motivo' => '01',
        'des_motivo' => 'Anulación de la operación',
        'items' => [[
            'codigo' => 'P001', 'descripcion' => 'Producto a anular',
            'unidad' => 'NIU', 'cantidad' => 1, 'precio_unitario' => 118.00,
            'tip_afe_igv' => '10',
        ]],
        'enviar_automatico' => false,
    ]),

    reqSimple('Listar notas de crédito', 'GET', 'notas-credito?por_pagina=15'),
    reqSimple('Filtrar NC por documento afectado', 'GET', 'notas-credito?serie=FC01'),
    reqSimple('Ver NC por ID', 'GET', 'notas-credito/1'),
    reqJson('Actualizar NC rechazada', 'PUT', 'notas-credito/1', [
        'des_motivo' => 'Anulación corregida',
    ]),
    reqSimple('Descargar XML', 'GET', 'notas-credito/1/xml'),
    reqSimple('Descargar CDR', 'GET', 'notas-credito/1/cdr'),
    reqSimple('Descargar PDF', 'GET', 'notas-credito/1/pdf?format=a4'),
    reqSimple('Enviar a SUNAT (manual)', 'POST', 'notas-credito/1/enviar'),
    reqSimple('Reenviar a SUNAT', 'POST', 'notas-credito/1/reenviar'),
];

return [
    'name' => '06. Notas de Crédito (Tipo 07)',
    'description' => 'NC para anular/devolver/descontar. Cat. 09 motivos: 01=anulación, 02=anulación error RUC, 03=corrección descripción, 04=descuento global, 05=descuento por ítem, 06=devolución total, 07=devolución parcial, 08=bonificación, 09=ajuste, 10=otros. Lee 06-Notas-credito.md.',
    'item' => array_merge($ncOriginal['item'] ?? [], $ncNuevas),
];

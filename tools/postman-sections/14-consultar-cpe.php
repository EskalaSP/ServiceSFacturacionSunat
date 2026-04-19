<?php

/**
 * SECCIÓN 14 — CONSULTAR CPE / CDR EN SUNAT
 */

declare(strict_types=1);

$items = [
    reqSimple('Consultar CPE — venta propia',
        'GET',
        'consultar-cpe?ruc_emisor=20100000001&tipo_doc=01&serie=F001&correlativo=1&fecha_emision=2026-04-19&monto_total=118.00',
        'Verifica el estado integrado en SUNAT (más rápido que descargar CDR).'),

    reqSimple('Consultar CPE — factura de proveedor',
        'GET',
        'consultar-cpe?ruc_emisor=20987654321&tipo_doc=01&serie=F001&correlativo=100&fecha_emision=2026-04-15&monto_total=590.00',
        'Caso típico: verificar antes de pagar a un proveedor.'),

    reqSimple('Consultar CPE — boleta',
        'GET',
        'consultar-cpe?ruc_emisor=20100000001&tipo_doc=03&serie=B001&correlativo=10&fecha_emision=2026-04-19&monto_total=35.00'),

    reqSimple('Consultar CPE — nota de crédito',
        'GET',
        'consultar-cpe?ruc_emisor=20100000001&tipo_doc=07&serie=FC01&correlativo=1&fecha_emision=2026-04-19&monto_total=118.00'),

    reqJson('Consultar CDR (estado vía CDR)', 'POST', 'consultar-cdr', [
        'ruc_emisor' => '20100000001',
        'tipo_doc' => '01',
        'serie' => 'F001',
        'correlativo' => '1',
    ], 'Consulta el CDR archivado en SUNAT. Devuelve el ZIP del CDR.'),
];

return [
    'name' => '14. Consultar CPE / CDR',
    'description' => 'Verificar estado de comprobantes en SUNAT. CPE = consulta integrada (rápida). CDR = obtener el archivo de respuesta firmado. Lee 14-Consultar-CPE.md.',
    'item' => $items,
];

<?php

/**
 * SECCIÓN 08 — RESUMEN DIARIO DE BOLETAS (RC)
 */

declare(strict_types=1);

$items = [
    reqJson('Crear resumen diario (envío de boletas pendientes)', 'POST', 'resumenes', [
        'fecha_resumen' => '2026-04-19',
    ], 'Toma TODAS las boletas pendientes de esa fecha y las envía a SUNAT vía RC. Plazo: día de emisión + 7 días.'),

    reqJson('Crear resumen ANULACIÓN (anular boletas aceptadas)', 'POST', 'resumenes', [
        'fecha_resumen' => '2026-04-19',
        'anular' => [
            ['id' => 15, 'motivo' => 'Error en el monto del item'],
            ['id' => 16, 'motivo' => 'Cliente solicita anulación'],
        ],
    ], 'Las boletas aceptadas se anulan vía RC, no via comunicación de baja. Plazo: 7 días desde emisión.'),

    reqJson('Resumen envío manual (enviar_automatico=false)', 'POST', 'resumenes', [
        'fecha_resumen' => '2026-04-19',
        'enviar_automatico' => false,
    ], 'Crea el resumen en estado pendiente. Llamar luego POST /resumenes/{id}/enviar.'),

    reqSimple('Listar resúmenes', 'GET', 'resumenes?por_pagina=15'),
    reqSimple('Filtrar por mes', 'GET', 'resumenes?mes=2026-04'),
    reqSimple('Filtrar solo anulaciones', 'GET', 'resumenes?tipo=anulacion'),
    reqSimple('Consultar estado SUNAT', 'GET', 'resumenes/1/estado',
        'Resumen completo: incluye estado de cada boleta dentro del RC.'),
    reqSimple('Descargar XML', 'GET', 'resumenes/1/xml'),
    reqSimple('Descargar CDR', 'GET', 'resumenes/1/cdr'),
    reqSimple('Enviar a SUNAT (manual)', 'POST', 'resumenes/1/enviar'),
];

return [
    'name' => '08. Resumen Diario (RC)',
    'description' => 'Envío en lote de boletas y anulación de boletas aceptadas. SUNAT acepta RC del mismo día o hasta 7 días después. Lee 08-Resumen-diario.md.',
    'item' => $items,
];

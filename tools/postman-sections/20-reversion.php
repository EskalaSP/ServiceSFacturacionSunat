<?php

/**
 * SECCIÓN 20 — REVERSIÓN (RR) — Anular Retenciones / Percepciones
 */

declare(strict_types=1);

$items = [
    reqJson('Reversión de retención', 'POST', 'reversiones', [
        'fecha_generacion' => '2026-04-19',
        'fecha_comunicacion' => '2026-04-19',
        'detalles' => [
            ['tipo_documento' => '20', 'serie' => 'R001', 'correlativo' => '1', 'motivo' => 'Error en datos del proveedor'],
        ],
    ], 'Anula retenciones aceptadas. Solo tipo_documento 20 (retención) o 40 (percepción).'),

    reqJson('Reversión de percepción', 'POST', 'reversiones', [
        'fecha_generacion' => '2026-04-19',
        'detalles' => [
            ['tipo_documento' => '40', 'serie' => 'P001', 'correlativo' => '1', 'motivo' => 'Cliente solicita anulación'],
        ],
    ]),

    reqJson('Reversión múltiple', 'POST', 'reversiones', [
        'fecha_generacion' => '2026-04-19',
        'detalles' => [
            ['tipo_documento' => '20', 'serie' => 'R001', 'correlativo' => '5', 'motivo' => 'Error monto'],
            ['tipo_documento' => '40', 'serie' => 'P001', 'correlativo' => '3', 'motivo' => 'Cliente anuló compra'],
        ],
    ]),

    reqJson('Reversión envío manual', 'POST', 'reversiones', [
        'fecha_generacion' => '2026-04-19',
        'detalles' => [
            ['tipo_documento' => '20', 'serie' => 'R001', 'correlativo' => '1', 'motivo' => 'Error datos'],
        ],
        'enviar_automatico' => false,
    ], 'Crea la RR en estado pendiente. Usar luego POST /anulaciones/{id}/enviar (la RR se almacena en la misma tabla que las RA).'),
];

return [
    'name' => '20. Reversión (RR)',
    'description' => 'Reversión de Retenciones (20) y Percepciones (40). Equivalente a la Comunicación de Baja pero solo para estos dos tipos. La RR se almacena en la tabla voided_documents con prefijo RR-.',
    'item' => $items,
];

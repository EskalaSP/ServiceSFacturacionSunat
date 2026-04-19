<?php

/**
 * SECCIÓN 12 — ENVÍO MANUAL A SUNAT
 *
 * Sección NUEVA — todos los endpoints POST /xxx/{id}/enviar.
 * Lee 12-Envio-manual.md.
 */

declare(strict_types=1);

$items = [
    reqSimple('POST /facturas/{id}/enviar', 'POST', 'facturas/1/enviar',
        'Funciona para facturas en estado pendiente o rechazado. Rechaza si ya está aceptado.'),
    reqSimple('POST /boletas/{id}/enviar', 'POST', 'boletas/1/enviar'),
    reqSimple('POST /notas-credito/{id}/enviar', 'POST', 'notas-credito/1/enviar'),
    reqSimple('POST /notas-debito/{id}/enviar', 'POST', 'notas-debito/1/enviar'),
    reqSimple('POST /guias-remision/{id}/enviar', 'POST', 'guias-remision/1/enviar',
        'Funciona para GRR (tipo 09) y GRT (tipo 31).'),
    reqSimple('POST /resumenes/{id}/enviar', 'POST', 'resumenes/1/enviar'),
    reqSimple('POST /anulaciones/{id}/enviar', 'POST', 'anulaciones/1/enviar',
        'Funciona para Comunicación de Baja (RA) y Reversión (RR) — detecta automáticamente cuál es por el identifier.'),
    reqSimple('POST /retenciones/{id}/enviar', 'POST', 'retenciones/1/enviar'),
    reqSimple('POST /percepciones/{id}/enviar', 'POST', 'percepciones/1/enviar'),
];

return [
    'name' => '12. Envío Manual a SUNAT',
    'description' => "TODOS los endpoints POST /xxx/{id}/enviar para envío manual.\n\n**Flujo:**\n1. Crear el comprobante con `enviar_automatico: false` → queda en `pendiente`\n2. Llamar al endpoint correspondiente de envío → encola job y pasa a `enviado`\n3. Job procesa y status final: `aceptado` o `rechazado`\n\nLee 12-Envio-manual.md.",
    'item' => $items,
];

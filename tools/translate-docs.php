<?php

/**
 * Traduce los ejemplos JSON de /documentacion/*.md al formato español nuevo.
 *
 * Cambios aplicados:
 * - Wrapper: "success" → "estado" (con valores "exito"/"error"), "message" → "mensaje",
 *   "data" → "datos", "errors" → "errores"
 * - Paginación: current_page → pagina_actual, last_page → ultima_pagina, per_page → por_pagina,
 *   pagination → paginacion
 * - Rutas: /retentions → /retenciones, /perceptions → /percepciones, /reversions → /reversiones
 * - IMPORTANTE: no toca "ciclo_facturacion", "razon_social", etc (campos reales de BD)
 */

$patterns = [
    // Wrapper de respuesta (claves JSON entre comillas)
    '/"success":\s*true/'     => '"estado": "exito"',
    '/"success":\s*false/'    => '"estado": "error"',
    '/"message":/'            => '"mensaje":',
    '/"data":/'               => '"datos":',
    '/"errors":/'             => '"errores":',
    '/"error_code":/'         => '"codigo_error":',

    // Paginación
    '/"pagination":/'         => '"paginacion":',
    '/"current_page":/'       => '"pagina_actual":',
    '/"last_page":/'          => '"ultima_pagina":',
    '/"per_page":/'           => '"por_pagina":',

    // Rutas inglés → español (dentro de texto markdown, no cambiar strings quoteadas técnicas)
    '/\/retentions\b/'        => '/retenciones',
    '/\/perceptions\b/'       => '/percepciones',
    '/\/reversions\b/'        => '/reversiones',
];

$files = glob(__DIR__ . '/../documentacion/*.md');
$summary = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    $original = $content;
    $changes = 0;

    foreach ($patterns as $pat => $rep) {
        $count = 0;
        $content = preg_replace($pat, $rep, $content, -1, $count);
        $changes += $count;
    }

    if ($content !== $original) {
        file_put_contents($file, $content);
        $summary[basename($file)] = $changes;
    }
}

echo "=== MASS-REPLACE DOCS RESUMEN ===" . PHP_EOL;
if (empty($summary)) {
    echo "Sin cambios." . PHP_EOL;
} else {
    foreach ($summary as $file => $count) {
        printf("  %-45s %3d cambios\n", $file, $count);
    }
    echo PHP_EOL . "Total archivos modificados: " . count($summary) . PHP_EOL;
}

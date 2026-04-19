<?php

/**
 * Traduce patrones de respuesta de inglés a español en controllers, middleware,
 * FormRequests y Actions.
 *
 * NO toca:
 * - Modelos (fillable, casts - son columnas de BD)
 * - Migrations (nombres de columnas)
 * - Services internos (formatters)
 *
 * Ajusta:
 * - Wrapper: 'success' => 'estado' ('true'→'exito', 'false'→'error'), 'message' → 'mensaje', 'errors' → 'errores'
 * - Paginación: 'pagination' → 'paginacion', 'current_page' → 'pagina_actual', 'last_page' → 'ultima_pagina', 'per_page' → 'por_pagina'
 * - 'error_code' → 'codigo_error'
 * - 'data' (inside response arrays) → 'datos'
 */

$patterns = [
    // Wrapper
    "/'success'\s*=>\s*false,/"        => "'estado' => 'error',",
    "/'success'\s*=>\s*true,/"         => "'estado' => 'exito',",
    "/'message'\s*=>/"                 => "'mensaje' =>",
    "/'errors'\s*=>/"                  => "'errores' =>",
    "/'error_code'\s*=>/"              => "'codigo_error' =>",

    // Paginación (claves comunes en arrays de response)
    "/'pagination'\s*=>/"              => "'paginacion' =>",
    "/'current_page'\s*=>/"            => "'pagina_actual' =>",
    "/'last_page'\s*=>/"               => "'ultima_pagina' =>",
    "/'per_page'\s*=>/"                => "'por_pagina' =>",

    // 'data' => (en arrays de response — controllers + actions + middleware + FormRequests)
    "/'data'\s*=>/"                    => "'datos' =>",
];

$files = array_merge(
    glob(__DIR__ . '/../app/Http/Requests/Api/V1/*.php'),
    glob(__DIR__ . '/../app/Http/Controllers/Api/V1/*.php'),
    glob(__DIR__ . '/../app/Http/Controllers/Api/V1/Sire/*.php'),
    glob(__DIR__ . '/../app/Http/Middleware/*.php'),
    glob(__DIR__ . '/../app/Actions/Documents/*.php'),
    glob(__DIR__ . '/../app/Actions/Subscription/*.php'),
);

$count = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    $original = $content;

    foreach ($patterns as $pat => $rep) {
        $content = preg_replace($pat, $rep, $content);
    }

    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Updated: " . basename($file) . PHP_EOL;
        $count++;
    }
}
echo PHP_EOL . "Total files updated: {$count}" . PHP_EOL;

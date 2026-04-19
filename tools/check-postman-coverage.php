<?php

// Inventario y verificación de cobertura: rutas API vs colección Postman.

$collFile = __DIR__ . '/../API SUNAT PRO V2.1 ✅✅✅✅✅.postman_collection.json';
$coll = json_decode(file_get_contents($collFile), true);

// 1) Inventario por folder
echo "=== INVENTARIO POR FOLDER ===" . PHP_EOL;

function countDeep(array $items): int {
    $n = 0;
    foreach ($items as $i) {
        $n += isset($i['item']) ? countDeep($i['item']) : 1;
    }
    return $n;
}

foreach ($coll['item'] as $f) {
    $count = isset($f['item']) ? countDeep($f['item']) : 1;
    printf("  %-58s %3d items\n", $f['name'], $count);
}

// 2) Extraer URIs+method de la colección
$collected = [];
function walkExtract(array $items, array &$out): void {
    foreach ($items as $i) {
        if (isset($i['item'])) {
            walkExtract($i['item'], $out);
        } elseif (isset($i['request']['url']['raw'])) {
            $raw = $i['request']['url']['raw'];
            $raw = preg_replace('/\?.*$/', '', $raw);                   // strip query
            $path = str_replace('{{base_url}}/', '', $raw);
            $path = preg_replace('/\/[0-9]+/', '/{id}', $path);         // numeric → {id}
            $path = preg_replace('/\/{{[^}]+}}/', '/{id}', $path);      // {{periodo}} etc → {id}
            $method = strtoupper($i['request']['method'] ?? '?');
            $out[] = $method . ' ' . $path;
        }
    }
}
walkExtract($coll['item'], $collected);
$collected = array_unique($collected);

// 3) Rutas reales de la API
$routesJson = shell_exec('cd ' . __DIR__ . '/.. && php artisan route:list --path=v1 --json');
$routes = json_decode(trim($routesJson), true);

$apiRoutes = [];
foreach ($routes as $r) {
    $rawMethods = is_array($r['method']) ? $r['method'] : explode('|', (string) $r['method']);
    foreach ($rawMethods as $m) {
        $m = strtoupper(trim($m));
        if (in_array($m, ['HEAD', 'PATCH', ''])) continue;
        $uri = $r['uri'];
        $uri = preg_replace('/\{[^}]+\}/', '{id}', $uri);
        $uri = str_replace('api/v1/', '', $uri);
        $apiRoutes[] = $m . ' ' . $uri;
    }
}
$apiRoutes = array_unique($apiRoutes);

// 4) Comparar
echo PHP_EOL . "=== COBERTURA ===" . PHP_EOL;
echo "Rutas API:                " . count($apiRoutes) . PHP_EOL;
echo "Cubiertas en colección:   " . count(array_intersect($apiRoutes, $collected)) . PHP_EOL;
echo "Total requests Postman:   " . count($collected) . PHP_EOL;

$missing = array_diff($apiRoutes, $collected);
echo PHP_EOL . "FALTAN EN LA COLECCIÓN (" . count($missing) . "):" . PHP_EOL;
foreach ($missing as $m) echo "  ❌ $m" . PHP_EOL;

$extra = array_diff($collected, $apiRoutes);
if (!empty($extra)) {
    echo PHP_EOL . "EXTRA EN LA COLECCIÓN (no son rutas reales — " . count($extra) . "):" . PHP_EOL;
    foreach ($extra as $m) echo "  ⚠️  $m" . PHP_EOL;
}

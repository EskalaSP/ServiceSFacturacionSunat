<?php
// Analiza ambas colecciones Postman y muestra su estructura
$files = [
    'V2' => __DIR__ . '/../API SUNAT PRO V2 ✅✅✅✅✅.postman_collection.json',
    'V2.1' => __DIR__ . '/../API SUNAT PRO V2.1 ✅✅✅✅✅.postman_collection.json',
];

function recurse(array $items, int $depth = 0, array &$out = []): void {
    foreach ($items as $it) {
        $name = $it['name'] ?? '?';
        if (isset($it['item'])) {
            $out[] = str_repeat('  ', $depth) . "📁 {$name} (" . count($it['item']) . ")";
            recurse($it['item'], $depth + 1, $out);
        } else {
            $method = $it['request']['method'] ?? '?';
            $url = $it['request']['url']['raw'] ?? '?';
            // Simplificar URL
            $url = preg_replace('/\{\{[^}]+\}\}/', '', $url);
            $out[] = str_repeat('  ', $depth) . "{$method} {$url} → {$name}";
        }
    }
}

foreach ($files as $label => $file) {
    echo "\n\n============ COLECCIÓN {$label} ============\n\n";
    $json = json_decode(file_get_contents($file), true);
    $lines = [];
    recurse($json['item'] ?? [], 0, $lines);
    echo implode("\n", $lines);
    echo "\n\n--- TOTAL items: " . countItems($json['item'] ?? []) . "\n";
}

function countItems(array $items): int {
    $n = 0;
    foreach ($items as $it) {
        if (isset($it['item'])) $n += countItems($it['item']);
        else $n++;
    }
    return $n;
}

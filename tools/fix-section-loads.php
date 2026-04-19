<?php

// Reemplaza el "load obligatorio" de la colección base por un load opcional
// en cada section file. Esto permite regenerar la colección incluso si la
// colección base no existe.

$newLoad = <<<'PHP'
$baseFile = __DIR__ . '/../../API SUNAT PRO V2.1 ✅✅✅✅✅.postman_collection.json';
$existing = file_exists($baseFile) ? (json_decode(file_get_contents($baseFile), true) ?: ['item' => []]) : ['item' => []];
PHP;

$pattern = '/\$existing\s*=\s*json_decode\(\s*file_get_contents\([^)]+\)\s*,\s*true\s*\);/';

$files = glob(__DIR__ . '/postman-sections/*.php');
$count = 0;
foreach ($files as $file) {
    $c = file_get_contents($file);
    $o = $c;
    $c = preg_replace($pattern, $newLoad, $c);
    if ($c !== $o) {
        file_put_contents($file, $c);
        echo "Updated: " . basename($file) . PHP_EOL;
        $count++;
    }
}
echo PHP_EOL . "Total: {$count}" . PHP_EOL;

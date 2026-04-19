<?php

// Reemplaza rutas en inglés por español en los archivos del builder de Postman.

$map = [
    'retentions/'   => 'retenciones/',
    'retentions\'' => 'retenciones\'',
    "retentions\"" => "retenciones\"",
    'retentions?'   => 'retenciones?',
    'perceptions/'  => 'percepciones/',
    'perceptions\'' => 'percepciones\'',
    "perceptions\"" => "percepciones\"",
    'perceptions?'  => 'percepciones?',
    'reversions/'   => 'reversiones/',
    'reversions\''  => 'reversiones\'',
    "reversions\""  => "reversiones\"",
    'reversions?'   => 'reversiones?',
];

$files = glob(__DIR__ . '/postman-sections/*.php');
$count = 0;
foreach ($files as $file) {
    $c = file_get_contents($file);
    $o = $c;
    foreach ($map as $from => $to) {
        $c = str_replace($from, $to, $c);
    }
    if ($c !== $o) {
        file_put_contents($file, $c);
        echo "Updated: " . basename($file) . PHP_EOL;
        $count++;
    }
}
echo PHP_EOL . "Total files updated: {$count}" . PHP_EOL;

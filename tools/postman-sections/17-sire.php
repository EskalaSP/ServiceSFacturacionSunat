<?php

/**
 * SECCIÓN 17 — SIRE (RCE)
 *
 * Reusa la sección existente del SIRE — está bastante completa.
 */

declare(strict_types=1);

$existing = json_decode(file_get_contents(__DIR__ . '/../../API SUNAT PRO V2 ⭐⭐⭐⭐⭐.postman_collection.json'), true);

$sireOriginal = null;
foreach ($existing['item'] as $f) {
    if (str_contains($f['name'], 'SIRE')) {
        $sireOriginal = $f;
        break;
    }
}

return [
    'name' => '17. SIRE (Registro de Compras)',
    'description' => 'SIRE RCE completo: 25 endpoints organizados en 8 sub-secciones (activación, periodos, flujo principal, comprobantes, tickets, uploads TUS, ajustes posteriores, reconciliación). Usar variable {{periodo}} (ej. 202604). Lee 17-Sire.md.',
    'item' => $sireOriginal['item'] ?? [],
];

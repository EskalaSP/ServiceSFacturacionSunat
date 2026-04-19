<?php

/**
 * SECCIÓN 15 — PANEL DE CONTROL (DASHBOARD)
 *
 * Reusa los 11 endpoints existentes — están completos y bien.
 */

declare(strict_types=1);

$existing = json_decode(file_get_contents(__DIR__ . '/../../API SUNAT PRO V2 ⭐⭐⭐⭐⭐.postman_collection.json'), true);

$panelOriginal = null;
foreach ($existing['item'] as $f) {
    if (str_contains($f['name'], 'Panel de control')) {
        $panelOriginal = $f;
        break;
    }
}

return [
    'name' => '15. Panel de Control',
    'description' => '11 endpoints de KPIs, indicadores, ventas mensuales, aging, alertas. Para BI/dashboards visuales. Lee 15-Panel-de-control.md.',
    'item' => $panelOriginal['item'] ?? [],
];

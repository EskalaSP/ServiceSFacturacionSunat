<?php

/**
 * SECCIÓN 16 — REPORTES
 *
 * Sección NUEVA — los 7 endpoints /reportes/*.
 */

declare(strict_types=1);

$items = [
    reqSimple('Registro de Ventas (mensual SUNAT)',
        'GET',
        'reportes/registro-ventas?desde=2026-04-01&hasta=2026-04-30',
        'Reporte oficial de ventas SUNAT. Filtros: desde/hasta (obligatorios).'),

    reqSimple('Ventas consolidado',
        'GET',
        'reportes/ventas-consolidado?desde=2026-04-01&hasta=2026-04-30',
        'Resumen consolidado: facturas + boletas + notas con totales por moneda.'),

    reqSimple('Reporte de notas (NC + ND)',
        'GET',
        'reportes/notas?desde=2026-04-01&hasta=2026-04-30'),

    reqSimple('Cobranzas (aging)',
        'GET',
        'reportes/cobranzas?al_dia=2026-04-19',
        'Documentos por cobrar agrupados por aging: vencidos, próximos a vencer, al día.'),

    reqSimple('Documentos internos (cotizaciones + notas de venta)',
        'GET',
        'reportes/documentos-internos?desde=2026-04-01&hasta=2026-04-30'),

    reqSimple('Por cliente (top clientes)',
        'GET',
        'reportes/por-cliente?desde=2026-04-01&hasta=2026-04-30&limit=20'),

    reqSimple('Por sucursal (ranking)',
        'GET',
        'reportes/por-sucursal?desde=2026-04-01&hasta=2026-04-30'),
];

return [
    'name' => '16. Reportes',
    'description' => 'Reportes de gestión y cumplimiento SUNAT. Todos requieren rango de fechas. Devuelven JSON paginado.',
    'item' => $items,
];

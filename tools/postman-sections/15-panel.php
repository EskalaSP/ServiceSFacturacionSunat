<?php

/**
 * SECCIÓN 15 — PANEL DE CONTROL (DASHBOARD)
 *
 * 11 endpoints de KPIs, indicadores y analítica visual.
 */

declare(strict_types=1);

$items = [
    reqSimple('Panel completo (vista general)', 'GET', 'panel',
        'Vista consolidada del mes: KPIs principales + breakdown por SUNAT + aging + top clientes.'),

    reqSimple('Indicadores (hoy / semana / mes / año)', 'GET', 'panel/indicadores',
        'KPIs: ventas totales, documentos emitidos, porcentaje de crecimiento vs período anterior.'),

    reqSimple('Estado SUNAT (breakdown de aceptados/rechazados)', 'GET', 'panel/estado-sunat',
        'Distribución por estado SUNAT (aceptado, rechazado, pendiente) + los últimos rechazos para revisión rápida.'),

    reqSimple('Cobranzas (aging de cuentas por cobrar)', 'GET', 'panel/cobranzas',
        'Documentos por cobrar agrupados por antigüedad: al día, 1-30 días, 31-60, 61-90, 90+ días.'),

    reqSimple('Ventas mensuales (gráfico 12 meses)', 'GET', 'panel/ventas-mensuales',
        'Serie de 12 meses con comparación vs año anterior. Útil para gráficos de línea.'),

    reqSimple('Ranking por sucursal', 'GET', 'panel/por-sucursal',
        'Ventas agrupadas por sucursal — para comparar rendimiento de locales.'),

    reqSimple('Desglose por moneda (PEN / USD)', 'GET', 'panel/por-moneda'),

    reqSimple('Clientes (top + nuevos + recurrentes)', 'GET', 'panel/clientes',
        'Top 10 clientes del mes + clientes nuevos + clientes recurrentes con % retención.'),

    reqSimple('Productos (top ventas / cantidad)', 'GET', 'panel/productos',
        'Productos más vendidos por monto y por cantidad, con análisis de afectación IGV.'),

    reqSimple('Documentos recientes (feed últimos 20)', 'GET', 'panel/documentos-recientes',
        'Feed en tiempo real de los últimos 20 comprobantes emitidos.'),

    reqSimple('Alertas del sistema', 'GET', 'panel/alertas',
        'Alertas activas: rechazos sin resolver, documentos próximos a vencer, series sin stock, etc.'),
];

return [
    'name' => '15. Panel de Control',
    'description' => '11 endpoints de KPIs, indicadores, ventas mensuales, aging, alertas — pensado para dashboards BI/visuales. Lee 15-Panel-de-control.md.',
    'item' => $items,
];

<?php

/**
 * SECCIÓN 17 — SIRE (Sistema Integrado de Registros Electrónicos / RCE)
 *
 * Integración completa con el SIRE de SUNAT: activación, periodos,
 * flujo principal RCE, comprobantes locales, tickets, uploads TUS,
 * ajustes posteriores y reconciliación.
 *
 * Variable `periodo`: YYYYMM (ej. "202604" = abril 2026).
 */

declare(strict_types=1);

// 0. ACTIVACIÓN ----------------------------------------------------------------
$activacion = [
    reqJson('Activar SIRE', 'POST', 'sire/activar', [
        'sol_user' => 'TU_USUARIO_SOL',
        'sol_pass' => 'TU_CLAVE_SOL',
        'client_id' => 'TU_CLIENT_ID_SIRE',
        'client_secret' => 'TU_CLIENT_SECRET_SIRE',
    ], 'Primero crear app en SUNAT SOL (Menú SIRE → Registro de aplicaciones). Luego guardar las credenciales aquí.'),

    reqSimple('Desactivar SIRE', 'POST', 'sire/desactivar',
        'Limpia credenciales SIRE del tenant. Útil para rotar credenciales o desactivar el módulo.'),
];

// 1. PERIODOS ------------------------------------------------------------------
$periodos = [
    reqSimple('Listar periodos RCE disponibles', 'GET', 'sire/periodos',
        'Periodos abiertos/cerrados en SUNAT (últimos 24 meses).'),
];

// 2. RCE FLUJO PRINCIPAL -------------------------------------------------------
$flujo = [
    reqSimple('Descargar propuesta (5.34)', 'GET', 'sire/rce/{{periodo}}/propuesta',
        'Propuesta inicial de SUNAT basada en comprobantes declarados por los proveedores.'),

    reqSimple('Descargar resumen (5.35)', 'GET', 'sire/rce/{{periodo}}/resumen',
        'Resumen consolidado del periodo con totales y estadísticas.'),

    reqSimple('Descargar constancia PDF (5.49)', 'GET', 'sire/rce/constancia?periodo={{periodo}}',
        'Constancia oficial de presentación en PDF (documento legal).'),

    reqJson('Aceptar propuesta (5.2)', 'POST', 'sire/rce/{{periodo}}/aceptar-propuesta', [],
        'Acepta la propuesta de SUNAT tal como está. Proceso asíncrono — devuelve ticket.'),

    reqJson('Registrar preliminar (5.4)', 'POST', 'sire/rce/{{periodo}}/registrar-preliminar', [],
        'Registra el RCE preliminar antes de la presentación definitiva.'),
];

// 3. COMPROBANTES LOCALES ------------------------------------------------------
$comprobantes = [
    reqSimple('Listar comprobantes del periodo', 'GET', 'sire/rce/{{periodo}}/comprobantes',
        'Comprobantes descargados del SIRE en BD local. Útil para auditoría.'),

    reqSimple('Ver comprobante específico', 'GET', 'sire/rce/{{periodo}}/comprobantes/1'),
];

// 4. TICKETS (SIRE es asíncrono) -----------------------------------------------
$tickets = [
    reqSimple('Listar tickets pendientes', 'GET', 'sire/tickets',
        'Todos los tickets generados por operaciones asíncronas. Estados: pendiente, procesando, completado, error.'),

    reqSimple('Ver ticket específico', 'GET', 'sire/tickets/{{num_ticket}}'),

    reqSimple('Refrescar estado del ticket', 'POST', 'sire/tickets/{{num_ticket}}/refrescar',
        'Fuerza consulta a SUNAT del estado actual del ticket (por si el scheduler no lo ha pooleado).'),

    reqSimple('Descargar archivo del ticket (ZIP)', 'GET', 'sire/tickets/{{num_ticket}}/archivo',
        'Si el ticket generó un archivo (propuesta, resumen, CSV), lo descarga como ZIP.'),
];

// 5. UPLOADS TUS (5.3, 5.5, 5.6) -----------------------------------------------
$uploads = [
    reqJson('Reemplazar propuesta (5.3) — JSON', 'POST', 'sire/rce/{{periodo}}/reemplazar-propuesta', [
        'filas' => [
            // Array de comprobantes en formato TUS (estructura específica SUNAT)
        ],
    ], 'Reemplaza TODA la propuesta por el contenido que envías. Contenido en JSON.'),

    reqFormData('Reemplazar propuesta (5.3) — Multipart (TXT)', 'POST', 'sire/rce/{{periodo}}/reemplazar-propuesta', [
        'archivo' => ['type' => 'file', 'src' => ''],
    ], 'Alternativa: subir el TXT en formato TUS directamente (sin parsear a JSON).'),

    reqJson('Cargar No Domiciliados (5.5)', 'POST', 'sire/rce/{{periodo}}/no-domiciliados', [
        'filas' => [],
    ], 'Agrega comprobantes de proveedores No Domiciliados al RCE.'),

    reqJson('Complementar propuesta (5.6)', 'POST', 'sire/rce/{{periodo}}/complementar-propuesta', [
        'filas' => [],
    ], 'Agrega comprobantes NO devueltos en la propuesta de SUNAT (complementarios).'),
];

// 6. AJUSTES POSTERIORES (5.18-5.29 + 5.45-5.48) -------------------------------
// 4 variantes: impuesto_bruto | no_gravadas | vehiculares | devoluciones
// 4 acciones: cargar | enviar | descargar | eliminar
$ajustes = [
    reqJson('Cargar ajustes (5.18/21/24/27)', 'POST', 'sire/rce/{{periodo}}/ajustes-posteriores/actual/cargar', [
        'filas' => [],
    ], 'variant: actual (periodo actual) | anterior (meses previos). Acción cargar = subir datos.'),

    reqJson('Enviar ajustes a SUNAT (5.19/22/25/28)', 'POST', 'sire/rce/{{periodo}}/ajustes-posteriores/actual/enviar', [],
        'Tras cargar, envía los ajustes a SUNAT. Proceso asíncrono — devuelve ticket.'),

    reqSimple('Descargar ajustes (5.45-5.48)', 'GET', 'sire/rce/{{periodo}}/ajustes-posteriores/actual/descargar',
        'Descarga ajustes registrados previamente para auditoría.'),

    reqJson('Eliminar comprobantes del ajuste (5.20/23/26/29)', 'POST', 'sire/rce/{{periodo}}/ajustes-posteriores/actual/eliminar', [
        'filas' => [],
    ], 'Elimina filas específicas del ajuste cargado.'),
];

// 7. RECONCILIACIÓN (local vs SUNAT) -------------------------------------------
$reconciliacion = [
    reqSimple('Reconciliar (síncrono)', 'GET', 'sire/rce/{{periodo}}/reconciliar',
        'Reporte de diferencias entre comprobantes locales y los declarados en SUNAT. Útil para auditoría.'),

    reqSimple('Reconciliar async (encola job)', 'POST', 'sire/rce/{{periodo}}/reconciliar-async',
        'Versión asíncrona para periodos con mucho volumen. Devuelve ID de reconciliación.'),

    reqSimple('Historial de reconciliaciones', 'GET', 'sire/rce/{{periodo}}/reconciliaciones'),

    reqSimple('Ver reconciliación específica', 'GET', 'sire/rce/reconciliaciones/1'),
];

return [
    'name' => '17. SIRE (Registro de Compras)',
    'description' => "SIRE RCE completo — 25 endpoints organizados en 8 sub-secciones.\n\nVariable requerida:\n- `periodo`: YYYYMM (ej. \"202604\")\n- `num_ticket`: ticket asíncrono (se obtiene al llamar endpoints que retornan ticket)\n\nLee 17-Sire.md para flujo completo.",
    'item' => [
        folder('0. Activación', $activacion),
        folder('1. Periodos (5.33)', $periodos),
        folder('2. RCE Flujo Principal', $flujo),
        folder('3. Comprobantes locales', $comprobantes),
        folder('4. Tickets', $tickets),
        folder('5. Uploads TUS', $uploads),
        folder('6. Ajustes Posteriores', $ajustes),
        folder('7. Reconciliación', $reconciliacion),
    ],
];

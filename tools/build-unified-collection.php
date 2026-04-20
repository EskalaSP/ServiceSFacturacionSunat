<?php
/**
 * Unifica V2 (rica en ejemplos) + V2.1 (cobertura completa) en una sola colección
 * con estructura jerárquica: Módulo → Operación → Request.
 *
 * Operaciones canónicas: Crear / Consultar / Modificar / SUNAT / Descargas / Pagos
 */

declare(strict_types=1);

$SRC_V2   = __DIR__ . '/../API SUNAT PRO V2 ✅✅✅✅✅.postman_collection.json';
$SRC_V21  = __DIR__ . '/../API SUNAT PRO V2.1 ✅✅✅✅✅.postman_collection.json';
$OUT      = __DIR__ . '/../API SUNAT PRO V3 ✅✅✅✅✅.postman_collection.json';

// ---------- 1) Aplanar ambas colecciones ----------
function flatten(array $items, array $trail = []): array {
    $out = [];
    foreach ($items as $it) {
        if (isset($it['item'])) {
            $out = array_merge($out, flatten($it['item'], array_merge($trail, [$it['name']])));
        } else {
            $it['_origin_trail'] = $trail;
            $out[] = $it;
        }
    }
    return $out;
}

$v2  = json_decode(file_get_contents($SRC_V2), true);
$v21 = json_decode(file_get_contents($SRC_V21), true);

$flatV2  = flatten($v2['item']  ?? []);
$flatV21 = flatten($v21['item'] ?? []);

echo "V2  aplanado: " . count($flatV2)  . " items\n";
echo "V2.1 aplanado: " . count($flatV21) . " items\n";

// ---------- 2) Dedup ----------
// Clave: método + path-normalizada + nombre (case-insensitive)
// Ordenar: V2.1 primero, V2 agrega si NO existe (prioriza V2.1 por usar formato español)
function normUrl(array $req): string {
    $raw = $req['request']['url']['raw'] ?? '';
    // Quitar trailing slashes vacíos y query strings
    $raw = preg_replace('~/+$~', '', $raw);
    return strtolower($raw);
}
function itemKey(array $it): string {
    $method = strtoupper($it['request']['method'] ?? 'GET');
    $url    = normUrl($it);
    $name   = strtolower(trim($it['name'] ?? ''));
    return $method . '|' . $url . '|' . $name;
}

$byKey = [];
foreach ($flatV21 as $it) {
    $byKey[itemKey($it)] = $it;
}
// Agregar ejemplos de V2 que no estén en V2.1 (más variedad)
$nuevos = 0;
foreach ($flatV2 as $it) {
    $k = itemKey($it);
    if (!isset($byKey[$k])) {
        $byKey[$k] = $it;
        $nuevos++;
    }
}
echo "Items de V2 que aportan ejemplos extra: $nuevos\n";
echo "TOTAL únicos: " . count($byKey) . "\n\n";

// ---------- 3) Normalizar headers y URL base ----------
function ensureHeaders(array &$req): void {
    $required = [
        'Accept'        => 'application/json',
        'Content-Type'  => 'application/json',
        'X-Api-Key'     => '{{api_key}}',
        'X-Api-Secret'  => '{{api_secret}}',
    ];
    $existing = [];
    foreach ($req['request']['header'] ?? [] as $h) {
        $existing[strtolower($h['key'] ?? '')] = true;
    }
    foreach ($required as $k => $v) {
        // Content-Type lo omitimos en GET/DELETE
        $method = strtoupper($req['request']['method'] ?? 'GET');
        if ($k === 'Content-Type' && in_array($method, ['GET', 'DELETE'], true)) continue;
        // /registro no requiere API Key (es público)
        $url = $req['request']['url']['raw'] ?? '';
        if (in_array($k, ['X-Api-Key', 'X-Api-Secret'], true) &&
            (str_contains($url, '/registro') || str_contains($url, '/planes')) &&
            strtoupper($req['request']['method'] ?? '') !== 'PUT'
        ) continue;

        if (!isset($existing[strtolower($k)])) {
            $req['request']['header'][] = ['key' => $k, 'value' => $v, 'type' => 'text'];
        }
    }
}

foreach ($byKey as &$it) {
    ensureHeaders($it);
    // Forzar base_url variable
    if (isset($it['request']['url']['raw']) && $it['request']['url']['raw']) {
        $raw = $it['request']['url']['raw'];
        if (!str_contains($raw, '{{base_url}}')) {
            $raw = preg_replace('~https?://[^/]+(/[^/]+)?~', '{{base_url}}', $raw, 1);
            $it['request']['url']['raw'] = $raw;
        }
    }
}
unset($it);

// ---------- 4) Categorizar ----------
/**
 * Devuelve [módulo, operación] para cada item.
 * Operación: Crear | Consultar | Modificar | SUNAT | Descargas | Pagos | Estado | (null)
 */
function categorize(array $it): array {
    $method = strtoupper($it['request']['method'] ?? 'GET');
    $url    = $it['request']['url']['raw'] ?? '';
    $name   = $it['name'] ?? '';
    $nameL  = mb_strtolower($name);

    // path base sin {{base_url}}
    $path = preg_replace('~^\{\{base_url\}\}~', '', $url);
    $path = preg_replace('~\?.*$~', '', $path);       // quitar query
    $path = rtrim($path, '/');

    // --- Por path base ---
    $matchModule = function(string $p): ?array {
        $map = [
            '#^/registro#'                       => '01. Configuración inicial',
            '#^/empresa($|/)#'                   => '01. Configuración inicial',
            '#^/buscar-documento#'               => '01. Configuración inicial',
            '#^/sucursales#'                     => '01. Configuración inicial',
            '#^/series#'                         => '01. Configuración inicial',
            '#^/clientes#'                       => '01. Configuración inicial',
            '#^/planes#'                         => '02. Planes y suscripción',
            '#^/suscripcion#'                    => '02. Planes y suscripción',
            '#^/facturas#'                       => '03. Facturas (Tipo 01)',
            '#^/boletas#'                        => '04. Boletas (Tipo 03)',
            '#^/notas-credito#'                  => '05. Notas de Crédito (Tipo 07)',
            '#^/notas-debito#'                   => '06. Notas de Débito (Tipo 08)',
            '#^/resumenes#'                      => '07. Resumen Diario (RC)',
            '#^/anulaciones#'                    => '08. Comunicación de Baja (RA)',
            '#^/guias-remision-transportista#'   => '10. Guía Remisión Transportista (Tipo 31)',
            '#^/guias-remision#'                 => '09. Guía Remisión Remitente (Tipo 09)',
            '#^/retenciones#'                    => '11. Retenciones (Tipo 20)',
            '#^/percepciones#'                   => '12. Percepciones (Tipo 40)',
            '#^/reversiones#'                    => '13. Reversión (RR)',
            '#^/consultar-cpe#'                  => '14. Consultar CPE / CDR',
            '#^/consultar-cdr#'                  => '14. Consultar CPE / CDR',
            '#^/panel#'                          => '15. Panel de Control',
            '#^/reportes#'                       => '16. Reportes',
            '#^/sire#'                           => '17. SIRE (Registro de Compras)',
            '#^/cotizaciones#'                   => '18. Cotizaciones (interno)',
            '#^/notas-venta#'                    => '19. Notas de Venta (interno)',
        ];
        foreach ($map as $re => $mod) {
            if (preg_match($re, $p)) return [$mod];
        }
        return null;
    };

    $mod = $matchModule($path);
    $modulo = $mod ? $mod[0] : '99. Otros';

    // --- Tipo especial guía tipo 31 por tipo_documento=31 ---
    if ($modulo === '09. Guía Remisión Remitente (Tipo 09)' &&
        (str_contains($url, 'tipo_documento=31') || preg_match('~\b(grt|transportista|tipo\s*31)\b~i', $nameL))
    ) {
        $modulo = '10. Guía Remisión Transportista (Tipo 31)';
    }

    // ===== Sub-módulos dentro de "01. Configuración inicial" =====
    $sub01 = function(string $p) use ($method): string {
        if (preg_match('~^/(registro|buscar-documento|empresa)~', $p)) return 'Empresa';
        if (preg_match('~^/sucursales~', $p))                          return 'Sucursales';
        if (preg_match('~^/series~', $p))                              return 'Series';
        if (preg_match('~^/clientes~', $p))                            return 'Clientes';
        return 'Empresa';
    };

    // ===== Panel sub-carpetas =====
    $subPanel = function(string $p) use ($nameL): ?string {
        if (preg_match('~/panel/(ventas|por-sucursal|por-moneda)~', $p)) return 'Ventas';
        if (preg_match('~/panel/(clientes|productos|cobranzas)~', $p))   return 'Comercial';
        if (preg_match('~/panel/(documentos|alertas)~', $p))             return 'Actividad';
        if (preg_match('~/panel/(indicadores|estado-sunat)~', $p) || preg_match('~/panel$~', $p)) return 'Vista general';
        return 'Vista general';
    };

    // ===== SIRE — respeta la sub-carpeta original si viene trail =====
    if ($modulo === '17. SIRE (Registro de Compras)') {
        $trail = $it['_origin_trail'] ?? [];
        $ultima = end($trail);
        if ($ultima && preg_match('~^\d+\.~', $ultima)) {
            return [$modulo, $ultima];
        }
        // Por si no hay trail, deducir:
        if (preg_match('~/sire/(activar|desactivar)~', $path)) return [$modulo, '0. Activación'];
        if (preg_match('~/sire/periodos~', $path))             return [$modulo, '1. Periodos'];
        if (preg_match('~/sire/tickets~', $path))              return [$modulo, '4. Tickets'];
        if (preg_match('~ajustes-posteriores~', $path))        return [$modulo, '6. Ajustes Posteriores'];
        if (preg_match('~reconciliar|reconciliaciones~', $path)) return [$modulo, '7. Reconciliación'];
        if (preg_match('~reemplazar|no-domiciliados|complementar~', $path)) return [$modulo, '5. Uploads TUS'];
        if (preg_match('~/comprobantes~', $path))              return [$modulo, '3. Comprobantes locales'];
        if (preg_match('~propuesta|resumen|constancia|preliminar~', $path)) return [$modulo, '2. RCE Flujo Principal'];
        return [$modulo, '0. Activación'];
    }

    // ===== Configuración inicial =====
    if ($modulo === '01. Configuración inicial') {
        $seccion = $sub01($path);
        // Dentro de cada sección agrupar por operación
        return [$modulo, $seccion . ' — ' . opByMethod($method, $path, $nameL)];
    }

    // ===== Planes y suscripción =====
    if ($modulo === '02. Planes y suscripción') {
        if (str_starts_with($path, '/planes')) return [$modulo, 'Planes (público)'];
        return [$modulo, 'Suscripción — ' . opByMethod($method, $path, $nameL)];
    }

    // ===== Panel y Reportes (solo GETs, sin sub-op) =====
    if ($modulo === '15. Panel de Control') {
        return [$modulo, $subPanel($path)];
    }
    if ($modulo === '16. Reportes') {
        return [$modulo, 'Reportes'];
    }

    // ===== Consultar CPE / CDR =====
    if ($modulo === '14. Consultar CPE / CDR') {
        if (str_contains($path, 'consultar-cdr')) return [$modulo, 'CDR'];
        if (preg_match('~tipo_doc=01~i', $url)) return [$modulo, 'Facturas'];
        if (preg_match('~tipo_doc=03~i', $url)) return [$modulo, 'Boletas'];
        if (preg_match('~tipo_doc=07~i', $url)) return [$modulo, 'Notas de Crédito'];
        if (preg_match('~tipo_doc=08~i', $url)) return [$modulo, 'Notas de Débito'];
        return [$modulo, 'Otros'];
    }

    // ===== Para documentos tributarios (facturas, boletas, NC, ND, guías, retenciones, percepciones, reversiones, anulaciones, resumenes) =====
    $operacion = opByMethod($method, $path, $nameL);

    // Para facturas: sub-categorizar ejemplos por tipo (solo POST /facturas sin segmentos extra)
    if ($modulo === '03. Facturas (Tipo 01)' && $method === 'POST' &&
        preg_match('~^/facturas/?$~', $path)) {
        return [$modulo, 'Crear — ' . categorizarFactura($name)];
    }

    // Para boletas: sub-categorizar ejemplos
    if ($modulo === '04. Boletas (Tipo 03)' && $method === 'POST' &&
        preg_match('~^/boletas/?$~', $path)) {
        return [$modulo, 'Crear — ' . categorizarBoleta($name)];
    }

    // Notas de crédito
    if ($modulo === '05. Notas de Crédito (Tipo 07)' && $method === 'POST' &&
        preg_match('~^/notas-credito/?$~', $path)) {
        return [$modulo, 'Crear — ' . categorizarNC($name)];
    }

    // Notas de débito
    if ($modulo === '06. Notas de Débito (Tipo 08)' && $method === 'POST' &&
        preg_match('~^/notas-debito/?$~', $path)) {
        return [$modulo, 'Crear — ' . categorizarND($name)];
    }

    // Guías remitente
    if ($modulo === '09. Guía Remisión Remitente (Tipo 09)' && $method === 'POST' &&
        preg_match('~^/guias-remision/?$~', $path)) {
        return [$modulo, 'Crear — ' . categorizarGRR($name)];
    }

    // Guías transportista
    if ($modulo === '10. Guía Remisión Transportista (Tipo 31)' && $method === 'POST' &&
        preg_match('~^/guias-remision(-transportista)?/?$~', $path)) {
        return [$modulo, 'Crear — ' . categorizarGRT($name)];
    }

    // Retenciones
    if ($modulo === '11. Retenciones (Tipo 20)' && $method === 'POST' &&
        preg_match('~^/retenciones/?$~', $path)) {
        return [$modulo, 'Crear'];
    }

    // Percepciones
    if ($modulo === '12. Percepciones (Tipo 40)' && $method === 'POST' &&
        preg_match('~^/percepciones/?$~', $path)) {
        return [$modulo, 'Crear'];
    }

    // Anulaciones
    if ($modulo === '08. Comunicación de Baja (RA)' && $method === 'POST' &&
        preg_match('~^/anulaciones/?$~', $path)) {
        return [$modulo, 'Crear'];
    }

    // Resúmenes
    if ($modulo === '07. Resumen Diario (RC)' && $method === 'POST' &&
        preg_match('~^/resumenes/?$~', $path)) {
        return [$modulo, 'Crear'];
    }

    return [$modulo, $operacion];
}

/**
 * Operación canónica a partir del método HTTP + path + nombre.
 */
function opByMethod(string $method, string $path, string $nameL): string {
    // Acciones explícitas por path
    if (preg_match('~/enviar$~', $path) || preg_match('~/reenviar$~', $path)) return 'SUNAT';
    if (preg_match('~/pdf|/xml|/cdr|/archivo|/propuesta$|/resumen$|/constancia~', $path)) return 'Descargas';
    if (preg_match('~/pagos~', $path)) return 'Pagos';
    // /estado$ solo es Consultar en GET (PUT /estado → Modificar)
    if ($method === 'GET' && preg_match('~/estado$~', $path)) return 'Consultar';

    switch ($method) {
        case 'POST':   return 'Crear';
        case 'GET':    return 'Consultar';
        case 'PUT':
        case 'PATCH':  return 'Modificar';
        case 'DELETE': return 'Modificar';
        default:       return 'Otros';
    }
}

/**
 * Agrupa ejemplos de factura según nombre del request.
 */
function categorizarFactura(string $name): string {
    $n = mb_strtolower($name);
    if (preg_match('~percepci(ó|o)n~', $n))                          return 'Percepción';
    if (preg_match('~retenci(ó|o)n~', $n))                           return 'Retención';
    if (preg_match('~detracci(ó|o)n~', $n))                          return 'Detracción';
    if (preg_match('~anticipo~', $n))                                return 'Anticipo';
    if (preg_match('~exportaci(ó|o)n~', $n))                         return 'Exportación';
    if (preg_match('~icbper|isc|ivap|tributo~', $n))                  return 'Tributos especiales (ICBPER/ISC/IVAP)';
    if (preg_match('~descuento~', $n))                                return 'Descuentos';
    if (preg_match('~gratuita~', $n))                                 return 'Gratuita';
    if (preg_match('~exonerad~', $n))                                 return 'Exonerada';
    if (preg_match('~inafect~', $n))                                  return 'Inafecta';
    if (preg_match('~guía|guia~', $n))                                return 'Con guía de remisión';
    if (preg_match('~contingencia|complej~', $n))                     return 'Casos especiales';
    if (preg_match('~envío manual|envio manual|enviar_automatico~', $n)) return 'Envío manual';
    if (preg_match('~cr(é|e)dito|cuotas~', $n))                       return 'Crédito (con cuotas)';
    return 'Básicas';
}

function categorizarBoleta(string $name): string {
    $n = mb_strtolower($name);
    if (preg_match('~nrus~', $n))                       return 'NRUS (0113)';
    if (preg_match('~mype|restaurant~', $n))             return 'MYPE Restaurantes (10.5%)';
    if (preg_match('~consumidor final~', $n))            return 'Consumidor final';
    if (preg_match('~icbper~', $n))                      return 'Con ICBPER';
    if (preg_match('~exonerad~', $n))                    return 'Exonerada';
    if (preg_match('~solo.registro~', $n))               return 'Solo registro (envío por resumen)';
    if (preg_match('~env(í|i)o manual~', $n))            return 'Envío manual';
    return 'Básicas';
}

function categorizarNC(string $name): string {
    $n = mb_strtolower($name);
    if (preg_match('~anulaci(ó|o)n total~', $n))         return 'Anulación total de factura';
    if (preg_match('~anulaci(ó|o)n de boleta~', $n))     return 'Anulación de boleta';
    if (preg_match('~descuento~', $n))                   return 'Descuento parcial';
    if (preg_match('~correcci(ó|o)n|devoluci(ó|o)n~', $n)) return 'Corrección / Devolución';
    if (preg_match('~d(ó|o)lares~', $n))                 return 'Moneda USD';
    if (preg_match('~env(í|i)o manual~', $n))            return 'Envío manual';
    return 'Otras';
}

function categorizarND(string $name): string {
    $n = mb_strtolower($name);
    if (preg_match('~mora|inter(é|e)ses~', $n))          return 'Intereses por mora';
    if (preg_match('~aumento~', $n))                     return 'Aumento de valor';
    if (preg_match('~penalidad~', $n))                   return 'Penalidad';
    if (preg_match('~boleta~', $n))                      return 'Sobre boleta';
    if (preg_match('~env(í|i)o manual~', $n))            return 'Envío manual';
    return 'Otras';
}

function categorizarGRR(string $name): string {
    $n = mb_strtolower($name);
    if (preg_match('~p(ú|u)blico~', $n))                 return 'Transporte Público';
    if (preg_match('~privado~', $n))                     return 'Transporte Privado';
    if (preg_match('~veh(í|i)culo menor|m1|l\b~', $n))   return 'Vehículo Menor M1/L';
    if (preg_match('~traslado entre~', $n))              return 'Traslado entre establecimientos';
    if (preg_match('~m(ú|u)ltiples|varios~', $n))        return 'Múltiples conductores';
    if (preg_match('~env(í|i)o manual~', $n))            return 'Envío manual';
    return 'Estándar';
}

function categorizarGRT(string $name): string {
    $n = mb_strtolower($name);
    if (preg_match('~subcontrat~', $n))                  return 'Subcontratado';
    if (preg_match('~pagador|tercero~', $n))             return 'Pagador tercero';
    if (preg_match('~m(ú|u)ltiples|varios~', $n))        return 'Múltiples conductores/vehículos';
    if (preg_match('~dam|importaci|exportaci~', $n))     return 'DAM (import/export)';
    if (preg_match('~endpoint general|tipo_documento=31~', $n)) return 'Vía endpoint general';
    if (preg_match('~env(í|i)o manual~', $n))            return 'Envío manual';
    return 'Estándar';
}

// ---------- 5) Construir árbol ----------
$tree = [];
foreach ($byKey as $it) {
    [$modulo, $operacion] = categorize($it);
    $tree[$modulo][$operacion][] = $it;
}

// Orden de módulos deseado (por número prefijo)
uksort($tree, fn($a, $b) => strnatcmp($a, $b));

// Orden de operaciones dentro de cada módulo
$opOrder = [
    'Crear' => 1, 'Crear — Básicas' => 2, 'Crear — ' => 3,
    'Consultar' => 10, 'Modificar' => 20, 'SUNAT' => 30,
    'Descargas' => 40, 'Pagos' => 50, 'Estado' => 60,
    'Acciones' => 70, 'Otros' => 99,
];
function opRank(string $op): int {
    // Sub-operación: Crear → Consultar → Modificar → SUNAT → Descargas → Pagos
    $subRank = function(string $op): int {
        if (str_contains($op, 'Crear'))     return 1;
        if (str_contains($op, 'Consultar')) return 2;
        if (str_contains($op, 'Modificar')) return 3;
        if (str_contains($op, 'SUNAT'))     return 4;
        if (str_contains($op, 'Descargas')) return 5;
        if (str_contains($op, 'Pagos'))     return 6;
        if (str_contains($op, 'Estado'))    return 7;
        if (str_contains($op, 'Acciones'))  return 8;
        return 9;
    };

    // === Configuración inicial: Empresa → Sucursales → Series → Clientes ===
    if (str_starts_with($op, 'Empresa'))    return 100 + $subRank($op);
    if (str_starts_with($op, 'Sucursales')) return 110 + $subRank($op);
    if (str_starts_with($op, 'Series'))     return 120 + $subRank($op);
    if (str_starts_with($op, 'Clientes'))   return 130 + $subRank($op);

    // === Planes y suscripción ===
    if ($op === 'Planes (público)')         return 200;
    if (str_starts_with($op, 'Suscripción')) return 210 + $subRank($op);

    // === SIRE: 0. Activación, 1. Periodos, 2. RCE..., 7. Reconciliación ===
    if (preg_match('~^(\d+)\.~', $op, $m)) return 300 + (int)$m[1];

    // === Panel de Control: Vista general → Ventas → Comercial → Actividad ===
    if ($op === 'Vista general') return 400;
    if ($op === 'Ventas')        return 401;
    if ($op === 'Comercial')     return 402;
    if ($op === 'Actividad')     return 403;

    // === Consultar CPE / CDR ===
    if ($op === 'Facturas')           return 500;
    if ($op === 'Boletas')            return 501;
    if ($op === 'Notas de Crédito')   return 502;
    if ($op === 'Notas de Débito')    return 503;
    if ($op === 'CDR')                return 510;
    if ($op === 'Otros')              return 520;

    // === Documentos tributarios: Crear — X → Consultar → Modificar → SUNAT → Descargas → Pagos ===
    if (str_starts_with($op, 'Crear — Básicas'))   return 600;
    if (str_starts_with($op, 'Crear — Estándar')) return 601;
    if (str_starts_with($op, 'Crear — '))          return 650; // resto de subcategorías ordena alfabéticamente
    if ($op === 'Crear')      return 700;
    if ($op === 'Consultar')  return 710;
    if ($op === 'Modificar')  return 720;
    if ($op === 'SUNAT')      return 730;
    if ($op === 'Descargas')  return 740;
    if ($op === 'Pagos')      return 750;
    if ($op === 'Estado')     return 760;
    if ($op === 'Acciones')   return 770;

    // === Reportes (único grupo) ===
    if ($op === 'Reportes') return 800;

    return 900;
}

// ---------- 6) Ordenar y armar estructura Postman ----------
$postmanItems = [];
foreach ($tree as $moduloName => $ops) {
    uksort($ops, fn($a,$b) => opRank($a) <=> opRank($b) ?: strnatcmp($a,$b));

    $moduloFolder = ['name' => $moduloName, 'item' => []];

    foreach ($ops as $opName => $items) {
        // Ordenar items dentro de la operación por método → nombre
        usort($items, function($a, $b){
            $ma = strtoupper($a['request']['method'] ?? '');
            $mb = strtoupper($b['request']['method'] ?? '');
            $rank = ['POST'=>1, 'GET'=>2, 'PUT'=>3, 'PATCH'=>4, 'DELETE'=>5];
            $ra = $rank[$ma] ?? 9;
            $rb = $rank[$mb] ?? 9;
            return $ra <=> $rb ?: strnatcmp($a['name'] ?? '', $b['name'] ?? '');
        });

        $opFolder = ['name' => $opName, 'item' => []];
        foreach ($items as $it) {
            // Limpiar metadata interna
            unset($it['_origin_trail']);
            $opFolder['item'][] = $it;
        }
        $moduloFolder['item'][] = $opFolder;
    }

    $postmanItems[] = $moduloFolder;
}

// ---------- 7) Emitir archivo ----------
$out = [
    'info' => [
        '_postman_id' => 'v3-unified-' . bin2hex(random_bytes(6)),
        'name' => 'API SUNAT PRO V3 ✅✅✅✅✅',
        'description' => <<<'DESC'
Colección UNIFICADA de API SUNAT PRO — facturación electrónica Perú.

Esta V3 fusiona V2 (rica en ejemplos de comprobantes) + V2.1 (cobertura completa) en una sola colección ordenada y sin duplicados.

## Estructura jerárquica

Cada módulo está dividido en subcarpetas por tipo de operación:
- **Crear** — POST (dividido por caso de uso)
- **Consultar** — GET (listar / ver por ID / filtros)
- **Modificar** — PUT / DELETE
- **SUNAT** — POST a /enviar y /reenviar
- **Descargas** — GET a /pdf, /xml, /cdr
- **Pagos** — Gestión de pagos (donde aplica)

## Variables requeridas

- `base_url` → `https://api.kodevo.es/sunat-api/api/v1` (producción) o `http://api-pro.test/api/v1` (local)
- `api_key` → X-Api-Key de la empresa
- `api_secret` → X-Api-Secret de la empresa
- `ruc_empresa` → RUC de la empresa
- `periodo` → YYYYMM (SIRE)

## Formato de respuestas

```json
{
  "estado": "exito" | "error",
  "mensaje": "texto",
  "datos": {...} | [...],
  "meta": {...},        // paginación (opcional)
  "errores": {...}      // validaciones (solo errores)
}
```

**Documentación completa**: `documentacion/README.md`
DESC,
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'item' => $postmanItems,
    'variable' => [
        ['key' => 'base_url',    'value' => 'https://api.kodevo.es/sunat-api/api/v1'],
        ['key' => 'api_key',     'value' => ''],
        ['key' => 'api_secret',  'value' => ''],
        ['key' => 'ruc_empresa', 'value' => '20100000001'],
        ['key' => 'periodo',     'value' => '202604'],
        ['key' => 'num_ticket',  'value' => ''],
    ],
];

file_put_contents($OUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "\n✅ Generado: {$OUT}\n";
echo "   Tamaño: " . number_format(filesize($OUT)) . " bytes\n";

// Resumen en consola
echo "\n==== ÁRBOL FINAL ====\n";
function countTree(array $items): int {
    $n = 0;
    foreach ($items as $it) {
        if (isset($it['item'])) $n += countTree($it['item']);
        else $n++;
    }
    return $n;
}
foreach ($postmanItems as $m) {
    $nm = countTree($m['item']);
    echo "📁 " . str_pad($m['name'], 50) . " ($nm items)\n";
    foreach ($m['item'] as $sub) {
        $ns = count($sub['item']);
        echo "   └─ " . str_pad($sub['name'], 50) . " ($ns)\n";
    }
}
echo "\nTOTAL: " . countTree($postmanItems) . " requests\n";

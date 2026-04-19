<?php

/**
 * Builder de la colección Postman "API SUNAT PRO".
 *
 * Toma como base la colección actual y genera la versión v2.1 con cobertura
 * completa de TODAS las rutas de la API + ejemplos realistas.
 *
 * Uso:
 *   php tools/build-postman.php
 *
 * Output: API SUNAT PRO V2.1 ⭐⭐⭐⭐⭐.postman_collection.json
 */

declare(strict_types=1);

const COLLECTION_BASE = __DIR__ . '/../API SUNAT PRO V2.1 ✅✅✅✅✅.postman_collection.json';
const COLLECTION_OUT  = __DIR__ . '/../API SUNAT PRO V2.1 ✅✅✅✅✅.postman_collection.json';

// ============================================================================
// HELPERS
// ============================================================================

function headerJson(): array {
    return [
        ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
        ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'],
        ['key' => 'X-Api-Key', 'value' => '{{api_key}}', 'type' => 'text'],
        ['key' => 'X-Api-Secret', 'value' => '{{api_secret}}', 'type' => 'text'],
    ];
}

function headerAuth(): array {
    return [
        ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
        ['key' => 'X-Api-Key', 'value' => '{{api_key}}', 'type' => 'text'],
        ['key' => 'X-Api-Secret', 'value' => '{{api_secret}}', 'type' => 'text'],
    ];
}

/**
 * Construye una request POST/PUT con body JSON.
 */
function reqJson(string $name, string $method, string $path, $body, ?string $note = null): array {
    $jsonBody = is_string($body) ? $body : json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return [
        'name' => $name,
        'request' => [
            'method' => $method,
            'header' => headerJson(),
            'body' => [
                'mode' => 'raw',
                'raw' => $jsonBody,
                'options' => ['raw' => ['language' => 'json']],
            ],
            'url' => buildUrl($path),
            'description' => $note,
        ],
        'response' => [],
    ];
}

/**
 * Construye una request GET / DELETE (sin body).
 */
function reqSimple(string $name, string $method, string $path, ?string $note = null): array {
    return [
        'name' => $name,
        'request' => [
            'method' => $method,
            'header' => headerAuth(),
            'url' => buildUrl($path),
            'description' => $note,
        ],
        'response' => [],
    ];
}

/**
 * Construye una request POST multipart/form-data (uploads).
 */
function reqFormData(string $name, string $method, string $path, array $fields, ?string $note = null): array {
    $formdata = [];
    foreach ($fields as $key => $value) {
        if (is_array($value) && ($value['type'] ?? null) === 'file') {
            $formdata[] = ['key' => $key, 'type' => 'file', 'src' => $value['src'] ?? ''];
        } else {
            $formdata[] = ['key' => $key, 'value' => (string) $value, 'type' => 'text'];
        }
    }
    return [
        'name' => $name,
        'request' => [
            'method' => $method,
            'header' => headerAuth(),
            'body' => ['mode' => 'formdata', 'formdata' => $formdata],
            'url' => buildUrl($path),
            'description' => $note,
        ],
        'response' => [],
    ];
}

function buildUrl(string $path): array {
    // Soportar query string en path: "facturas?serie=F001"
    $parts = explode('?', $path, 2);
    $clean = trim($parts[0], '/');
    $query = [];
    if (isset($parts[1])) {
        parse_str($parts[1], $kv);
        foreach ($kv as $k => $v) {
            $query[] = ['key' => $k, 'value' => (string) $v];
        }
    }

    $url = [
        'raw' => '{{base_url}}/' . $path,
        'host' => ['{{base_url}}'],
        'path' => array_values(array_filter(explode('/', $clean), fn ($p) => $p !== '')),
    ];
    if (!empty($query)) {
        $url['query'] = $query;
    }
    return $url;
}

function folder(string $name, array $items, ?string $description = null): array {
    $f = ['name' => $name, 'item' => $items];
    if ($description) $f['description'] = $description;
    return $f;
}

/**
 * Encuentra una folder existente en la colección base por nombre (busca substring).
 */
function findExistingFolder(array $existing, string $nameSubstring): ?array {
    foreach ($existing['item'] ?? [] as $f) {
        if (str_contains($f['name'] ?? '', $nameSubstring)) {
            return $f;
        }
    }
    return null;
}

// ============================================================================
// COLECCIÓN BASE (opcional — si no existe, arrancamos desde cero)
// ============================================================================

$existing = ['item' => []];
if (file_exists(COLLECTION_BASE)) {
    $loaded = json_decode(file_get_contents(COLLECTION_BASE), true);
    if (is_array($loaded)) {
        $existing = $loaded;
    }
}

// ============================================================================
// SECCIÓN 01 — SETUP / CONFIGURACIÓN INICIAL
// ============================================================================

$setupItems = [
    // Registro inicial (público)
    reqFormData('Registrar empresa (cert PFX)', 'POST', 'registro', [
        'ruc' => '20100000001',
        'razon_social' => 'MI EMPRESA SAC',
        'direccion' => 'AV. PRINCIPAL 123',
        'ubigeo' => '150101',
        'sol_user' => 'MODDATOS',
        'sol_pass' => 'MODDATOS',
        'certificado' => ['type' => 'file', 'src' => ''],
        'contrasena_certificado' => 'secreto',
        'tax_regime' => 'general',
    ], 'POST público. Devuelve api_key + api_secret. Guárdalos: el secret NO se puede recuperar.'),

    reqSimple('Listar planes (público)', 'GET', 'planes',
        'Lista los planes de suscripción disponibles (free, basic, premium, business...).'),

    // Empresa (perfil)
    reqSimple('GET Empresa (perfil)', 'GET', 'empresa',
        'Datos completos de tu empresa: régimen, plan, contadores de uso, certificado.'),

    reqJson('PUT Empresa (actualizar datos)', 'PUT', 'empresa', [
        'razon_social' => 'MI EMPRESA SAC',
        'nombre_comercial' => 'MI EMPRESA',
        'direccion' => 'AV. PRINCIPAL 456',
        'ubigeo' => '150101',
        'telefonos' => ['+51 999 888 777'],
        'emails' => ['contacto@miempresa.pe'],
        'mensaje_agradecimiento' => 'Gracias por su compra',
        'webhook_url' => 'https://miempresa.pe/sunat-webhook',
    ], 'Actualiza datos comerciales y de contacto. NO usar para cambiar régimen tributario.'),

    reqFormData('Subir logo de empresa', 'POST', 'empresa/logo', [
        'logo' => ['type' => 'file', 'src' => ''],
    ], 'Sube logo PNG/JPG. Aparecerá en los PDFs de comprobantes.'),

    reqFormData('Subir / actualizar certificado SUNAT', 'POST', 'empresa/certificado', [
        'certificado' => ['type' => 'file', 'src' => ''],
        'contrasena_certificado' => 'secreto',
    ], 'Reemplaza el certificado .pfx. Útil al renovar el cert anual.'),

    // Buscar documento (RUC/DNI)
    reqSimple('Buscar RUC / DNI', 'GET', 'buscar-documento?tipo=6&numero=20512345678',
        'Consulta local + SUNAT/RENIEC. Si existe en BD lo devuelve; si no, llama a SUNAT/RENIEC y lo guarda. Params: tipo (1=DNI, 4=CE, 6=RUC, 7=Pasaporte) + numero.'),
];

// ============================================================================
// SECCIÓN — SUCURSALES (CRUD)
// ============================================================================

$sucursalesItems = [
    reqJson('Crear sucursal', 'POST', 'sucursales', [
        'nombre' => 'Sucursal Principal',
        'cod_local' => '0000',
        'direccion' => 'AV. PRINCIPAL 123, LIMA',
        'ubigeo' => '150101',
        'es_principal' => true,
        'telefono' => '+51 999 888 777',
        'email' => 'principal@miempresa.pe',
    ], 'cod_local debe ser único. SUNAT requiere "0000" para el establecimiento principal.'),

    reqJson('Crear sucursal secundaria', 'POST', 'sucursales', [
        'nombre' => 'Sucursal Norte',
        'cod_local' => '0001',
        'direccion' => 'AV. ARGENTINA 1234, LIMA',
        'ubigeo' => '150101',
        'es_principal' => false,
    ]),

    reqSimple('Listar sucursales', 'GET', 'sucursales'),
    reqSimple('Ver sucursal {id}', 'GET', 'sucursales/1'),
    reqJson('Actualizar sucursal', 'PUT', 'sucursales/1', [
        'nombre' => 'Sucursal Principal Lima Centro',
        'telefono' => '+51 999 111 222',
    ]),
    reqSimple('Eliminar sucursal', 'DELETE', 'sucursales/2'),
];

// ============================================================================
// SECCIÓN — SERIES (CRUD por tipo de comprobante)
// ============================================================================

$seriesItems = [
    reqJson('Crear series (todos los tipos)', 'POST', 'series', [
        'series' => [
            ['tipo' => 'factura',         'serie' => 'F001', 'sucursal_id' => 1],
            ['tipo' => 'boleta',          'serie' => 'B001', 'sucursal_id' => 1],
            ['tipo' => 'nota_credito',    'serie' => 'FC01', 'sucursal_id' => 1],
            ['tipo' => 'nota_debito',     'serie' => 'FD01', 'sucursal_id' => 1],
            ['tipo' => 'guia_remision',   'serie' => 'T001', 'sucursal_id' => 1],
            ['tipo' => 'guia_transportista', 'serie' => 'V001', 'sucursal_id' => 1],
            ['tipo' => 'retencion',       'serie' => 'R001', 'sucursal_id' => 1],
            ['tipo' => 'percepcion',      'serie' => 'P001', 'sucursal_id' => 1],
        ],
    ], 'Crea múltiples series en una sola request. Prefijos: F (factura), B (boleta), FC/FD (notas), T/V (guías), R/P (retención/percepción).'),

    reqSimple('Listar series', 'GET', 'series'),
    reqSimple('Ver serie {id}', 'GET', 'series/1'),
    reqJson('Activar/desactivar serie', 'PUT', 'series/1', [
        'is_active' => false,
    ], 'Útil para "retirar" una serie sin borrarla. Las series no se eliminan, solo se desactivan.'),
];

// ============================================================================
// SECCIÓN — CLIENTES (CRUD)
// ============================================================================

$clientesItems = [
    reqJson('Crear cliente (RUC)', 'POST', 'clientes', [
        'tipo_documento' => '6',
        'numero_documento' => '20512345678',
        'razon_social' => 'EMPRESA DEMO SAC',
        'nombre_comercial' => 'DEMO',
        'direccion' => 'AV. AREQUIPA 1234, LIMA',
        'email' => 'contacto@demo.pe',
        'telefono' => '+51 999 111 222',
    ], 'Cat. 06 tipo_documento: 0=Otros, 1=DNI, 4=Carnet ext., 6=RUC, 7=Pasaporte, A=Cédula diplomática.'),

    reqJson('Crear cliente persona natural (DNI)', 'POST', 'clientes', [
        'tipo_documento' => '1',
        'numero_documento' => '12345678',
        'razon_social' => 'JUAN PEREZ LOPEZ',
        'direccion' => 'JR. AYACUCHO 456, LIMA',
        'email' => 'juan@gmail.com',
    ]),

    reqSimple('Listar clientes', 'GET', 'clientes'),
    reqSimple('Buscar cliente por nombre/documento', 'GET', 'clientes?buscar=demo'),
    reqSimple('Ver cliente por id', 'GET', 'clientes/1'),
    reqJson('Actualizar cliente', 'PUT', 'clientes/1', [
        'razon_social' => 'EMPRESA DEMO SAC ACTUALIZADA',
        'email' => 'nuevo@demo.pe',
    ]),
    reqSimple('Eliminar cliente', 'DELETE', 'clientes/1'),
];

// ============================================================================
// SECCIÓN — RÉGIMEN TRIBUTARIO (general / mype_restaurantes / nrus)
// ============================================================================

$regimenItems = [
    reqJson('Cambiar a régimen GENERAL (IGV 18%)', 'PUT', 'empresa', [
        'tax_regime' => 'general',
    ], 'Régimen estándar — todas las operaciones gravadas con 18% IGV.'),

    reqJson('Cambiar a MYPE Restaurantes (Ley 31556)', 'PUT', 'empresa', [
        'tax_regime' => 'mype_restaurantes',
    ], 'Aplica tasa reducida 8% (2022-2024) o 10.5% (2026-2029) automáticamente según fecha de emisión. Solo restaurantes/hoteles.'),

    reqJson('Cambiar a NRUS Categoría 1 (S/ 20)', 'PUT', 'empresa', [
        'tax_regime' => 'nrus',
        'nrus_categoria' => '1',
    ], 'NRUS = Nuevo RUS. Solo emite boletas (NO facturas, NO notas). IGV=0. tipo_operacion auto = 0113.'),

    reqJson('Cambiar a NRUS Categoría 2 (S/ 50)', 'PUT', 'empresa', [
        'tax_regime' => 'nrus',
        'nrus_categoria' => '2',
    ]),

    reqJson('Override manual de tasa IGV (avanzado)', 'PUT', 'empresa', [
        'igv_rate_override' => 18,
    ], 'Solo usar para casos especiales. Ignora el régimen y fuerza la tasa.'),

    reqSimple('Ver régimen y tasa vigente', 'GET', 'empresa',
        'Revisa "tax_regime" y "tasa_igv_vigente" en la respuesta.'),
];

// ============================================================================
// SECCIÓN — SUSCRIPCIONES Y BILLING
// ============================================================================

$suscripcionItems = [
    reqSimple('Ver suscripción actual', 'GET', 'suscripcion'),

    reqJson('Crear / activar suscripción (con trial de 14 días)', 'POST', 'suscripcion', [
        'plan_slug' => 'business',
        'ciclo_facturacion' => 'monthly',
        'prueba' => true,
    ], 'plan_slug: free | pro | business. ciclo_facturacion: monthly | yearly. prueba=true activa trial 14 días gratis.'),

    reqJson('Crear suscripción pagada', 'POST', 'suscripcion', [
        'plan_slug' => 'pro',
        'ciclo_facturacion' => 'yearly',
        'token' => 'tok_xxx_del_gateway_de_pagos',
    ]),

    reqJson('Cambiar de plan', 'PUT', 'suscripcion/cambiar-plan', [
        'plan_slug' => 'business',
        'ciclo_facturacion' => 'monthly',
    ]),

    reqJson('Cancelar suscripción', 'PUT', 'suscripcion/cancelar', []),

    reqSimple('Historial de pagos', 'GET', 'suscripcion/pagos'),
    reqSimple('Uso del mes (límites del plan)', 'GET', 'suscripcion/uso',
        'Contadores: documentos emitidos vs límite del plan.'),
];

// ============================================================================
// (continuará: facturas, boletas, NC, ND, etc.)
// ============================================================================

$config = folder('01. Setup inicial', $setupItems,
    'Registro de empresa + perfil + certificado + sucursales + series + clientes. Lee primero 01-Configuracion.md.');

$sucursales = folder('   - Sucursales (CRUD)', $sucursalesItems);
$series     = folder('   - Series (CRUD)', $seriesItems);
$clientes   = folder('   - Clientes (CRUD)', $clientesItems);
$regimen    = folder('02. Régimen tributario', $regimenItems,
    'Configurar régimen: general / mype_restaurantes / nrus. Lee 02-Tasas-IGV.md y 03-NRUS.md.');
$suscripcion = folder('03. Suscripción y planes', $suscripcionItems);

// ----------------------------------------------------------------------------
// Salida temporal (irá creciendo)
// ----------------------------------------------------------------------------

$collection = [
    'info' => [
        '_postman_id' => '697a279b-e275-41bc-ad44-5e3e948ff26f',
        'name' => 'API SUNAT PRO V2.1 ✅✅✅✅✅',
        'description' => "Colección completa en español para API SUNAT PRO — facturación electrónica Perú.\n\n**Cobertura**: todas las rutas /api/v1 con ejemplos realistas, agrupado por flujo de uso.\n\n**Formato de respuestas**:\n```json\n{\n  \"estado\": \"exito\" | \"error\",\n  \"mensaje\": \"texto\",\n  \"datos\": {...} | [...],\n  \"meta\": {...}         (paginación — opcional),\n  \"errores\": {...}      (detalles de validación — solo en errores)\n}\n```\n\n**Variables de entorno requeridas**:\n- `base_url` → `https://api.kodevo.es/sunat-api/api/v1` (producción) o `http://api-pro.test/api/v1` (local)\n- `api_key` → X-Api-Key de la empresa\n- `api_secret` → X-Api-Secret de la empresa\n\n**Documentación completa**: `documentacion/README.md`",
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        '_exporter_id' => '20633485',
    ],
    'item' => [
        $config,
        $sucursales,
        $series,
        $clientes,
        $regimen,
        $suscripcion,
        // ↓ aquí se inyectan dinámicamente todas las demás secciones
    ],
    'variable' => [
        ['key' => 'base_url', 'value' => 'https://api.kodevo.es/sunat-api/api/v1', 'type' => 'string'],
        ['key' => 'api_key', 'value' => '', 'type' => 'string'],
        ['key' => 'api_secret', 'value' => '', 'type' => 'string'],
        ['key' => 'ruc_empresa', 'value' => '20100000001', 'type' => 'string'],
        ['key' => 'periodo', 'value' => '202604', 'type' => 'string'],
        ['key' => 'num_ticket', 'value' => '', 'type' => 'string'],
    ],
];

// Inyectar secciones adicionales declaradas en otros archivos del builder
foreach (glob(__DIR__ . '/postman-sections/*.php') as $sectionFile) {
    $section = require $sectionFile;
    if (is_array($section)) {
        $collection['item'][] = $section;
    }
}

file_put_contents(
    COLLECTION_OUT,
    json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

// Reportar inventario
function countItems(array $items): int {
    $n = 0;
    foreach ($items as $item) {
        if (isset($item['item'])) $n += countItems($item['item']);
        else $n++;
    }
    return $n;
}

$total = countItems($collection['item']);
echo "✅ Colección generada: " . COLLECTION_OUT . PHP_EOL;
echo "   Folders top-level: " . count($collection['item']) . PHP_EOL;
echo "   Total requests:    {$total}" . PHP_EOL;

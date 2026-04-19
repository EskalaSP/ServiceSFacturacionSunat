# Plan de Implementación — Módulo SIRE (RCE) — VERSIÓN FINAL

> **Objetivo:** Integrar SIRE Compras (Registro de Compras Electrónico) de SUNAT en la API existente **reusando** toda la infraestructura multi-tenant, auth y credenciales que ya tienes — **sin tocar ni acoplarse** al módulo de facturación electrónica.
>
> **Principio rector:** SIRE vive como un módulo aislado bajo su propio namespace `App\Sire\*`, pero **consume** el modelo `Tenant`, middleware de auth y sistema de planes que ya existen. La facturación electrónica (invoices, boletas, notas, guías) sigue operando exactamente igual. Cero cambios en esas clases.

---

## 1. Principios de diseño

| Principio | Qué significa | Por qué importa |
|-----------|---------------|-----------------|
| **Reuso de infraestructura** | SIRE consume `Tenant`, `resolve.tenant`, planes, X-Api-Key ya existentes | No duplica onboarding ni credenciales |
| **Aislamiento por namespace** | Todo código nuevo bajo `App\Sire\*` | El código de facturación no sabe que SIRE existe |
| **Multi-tenant nativo** | Cada tabla SIRE tiene `tenant_id` FK | Escala a N empresas sin rework |
| **Asíncrono por default** | Toda operación devuelve un ticket → polling via job | SUNAT es lento y nada debe bloquear request HTTP |
| **Idempotente** | Constraints únicos (`num_ticket`, `car_sunat`) | Reintentos seguros, no duplica data |
| **Plan gating** | SIRE detrás de middleware `check.plan:sire` | Monetización desde día 1 |
| **Observable** | Log estructurado + métricas por tenant | Soporte puede debuggear sin acceso a prod |
| **Reconciliable** | Cruza propuesta SUNAT vs emitidos locales | Valor real: detectar diferencias antes de que sea multa |

---

## 1.1. ♻️ Qué se REUSA del proyecto existente (no se reinventa)

Cuando un cliente se registra via `POST /v1/registro`, ya quedan cargados en `tenants` todos los datos que SIRE necesita. **No se pide nada extra en registro.** El tenant solo debe asegurarse de haber seleccionado la URI "MIGE RCE y RVIE – SIRE" al crear su app en Menú SOL (lo cual ya documentamos).

### Datos del `Tenant` reusados directamente

| Campo de `tenants` | Uso en SIRE |
|---------------------|-------------|
| `id` | FK `tenant_id` en todas las tablas SIRE |
| `ruc` | Auth SUNAT: primera parte del `username` (`{RUC} {SOL_USER}`) |
| `sol_user` | Auth SUNAT: segunda parte del `username` |
| `sol_pass` | Auth SUNAT: `password` del grant |
| `client_id` | Auth SUNAT: parámetro `client_id` + path `/clientessol/{client_id}/oauth2/token/` |
| `client_secret` | Auth SUNAT: parámetro `client_secret` |
| `razon_social` | Logs, reportes, webhooks |
| `environment` | Decide host beta/producción (aunque SIRE solo tiene producción) |
| `api_key` / `api_secret` | Auth del cliente hacia NUESTRA API (middleware `resolve.tenant`) |
| `plan` | Gating con middleware `check.plan:sire` |
| `is_active` | Si el tenant está desactivado, SIRE tampoco funciona |
| `webhook_url` | Notificar fin de operaciones async |

### Infraestructura de Laravel reusada

| Qué | Origen | Uso en SIRE |
|-----|--------|-------------|
| Middleware `resolve.tenant` | Ya existe | Agarra tenant desde X-Api-Key (igual que facturación) |
| Middleware `throttle:api` | Ya existe | Rate limit por IP |
| Middleware `log.api` | Ya existe | Registra request en `api_logs` |
| Middleware `usage.headers` | Ya existe | Devuelve headers de cuota |
| Trait `ApiResponse` | Ya existe | Mismo formato `{success, message, data}` |
| Encriptación de credenciales | Ya existe (`casts encrypted`) | `sol_pass`, `client_secret` ya encriptados |
| Storage por tenant | `DocumentStorageService` | SIRE extiende con método `storeSireFile()` |
| Sistema de planes | `config/facturacion.php` | SIRE agrega feature flag, no cambia estructura |
| Queue Redis | `config/queue.php` | SIRE agrega 3 colas nuevas, no toca las existentes |

### Lo que SIRE **NO** reusa (tiene lo suyo)

- Modelos de comprobantes locales (`Invoice`, `Boleta`, etc.) — SIRE crea `SireComprobante` aparte porque el formato SUNAT es diferente al interno
- Jobs de emisión (`SendDocumentToSunat`) — SIRE tiene su propio flujo por tickets
- Tablas de pago, series, sucursales — SIRE no las necesita

---

## 2. Arquitectura de módulos

```
app/
├── Http/
│   └── Controllers/
│       └── Api/V1/
│           ├── InvoiceController.php        ← NO SE TOCA
│           ├── BoletaController.php         ← NO SE TOCA
│           ├── ...                           ← NO SE TOCA
│           └── Sire/                         ← NUEVO (aislado)
│               ├── SirePeriodoController.php
│               ├── SirePropuestaController.php
│               ├── SirePreliminarController.php
│               ├── SireTicketController.php
│               ├── SireResumenController.php
│               ├── SireReconciliationController.php
│               └── SireAjustesController.php   (Fase 6)
│
├── Sire/                                     ← NUEVO namespace aislado
│   ├── Services/
│   │   ├── Auth/
│   │   │   ├── SireAuthService.php          ← Token OAuth password grant
│   │   │   └── SireTokenCache.php           ← Cache por tenant
│   │   ├── Http/
│   │   │   ├── SireHttpClient.php           ← Guzzle + retry + Bearer
│   │   │   └── SireRateLimiter.php          ← Rate-limit por tenant
│   │   ├── Tickets/
│   │   │   ├── TicketService.php            ← 5.31 / 5.32
│   │   │   └── TicketPoller.php             ← loop con backoff
│   │   ├── Propuesta/
│   │   │   ├── DownloadPropuestaService.php ← 5.34
│   │   │   ├── AcceptPropuestaService.php   ← 5.2
│   │   │   ├── ReplacePropuestaService.php  ← 5.3 (TUS)
│   │   │   └── PropuestaParser.php          ← Parse TXT → rows
│   │   ├── Preliminar/
│   │   │   ├── RegisterPreliminarService.php ← 5.4
│   │   │   ├── NoDomiciliadosService.php     ← 5.5 (TUS)
│   │   │   └── DeletePreliminarService.php   ← 5.16 / 5.17
│   │   ├── Resumen/
│   │   │   └── ResumenService.php           ← 5.35 / 5.36
│   │   ├── Periodos/
│   │   │   └── PeriodoService.php           ← 5.33
│   │   ├── Reconciliation/
│   │   │   ├── ReconciliationService.php    ← cruza con invoices locales
│   │   │   └── ReconciliationReport.php
│   │   ├── Upload/
│   │   │   ├── TusUploader.php              ← cliente TUS.io
│   │   │   └── ZipBuilder.php               ← arma .zip según formato SUNAT
│   │   └── Ajustes/                          ← Fase 6
│   │       ├── CargarAjustesService.php
│   │       └── EnviarAjustesService.php
│   │
│   ├── Jobs/
│   │   ├── PollTicketJob.php                ← queue: sire-poll
│   │   ├── DownloadTicketFileJob.php        ← queue: sire-heavy
│   │   ├── ProcessPropuestaJob.php          ← queue: sire-process
│   │   ├── ReconcilePeriodoJob.php          ← queue: sire-process
│   │   └── NotifyWebhookJob.php             ← queue: default
│   │
│   ├── Models/
│   │   ├── SirePeriodo.php
│   │   ├── SireTicket.php
│   │   ├── SireComprobante.php
│   │   ├── SireUploadFile.php
│   │   └── SireReconciliationLog.php
│   │
│   ├── Enums/
│   │   ├── CodProceso.php                   ← catálogo Anexo I
│   │   ├── CodTipoArchivo.php               ← Anexo III
│   │   ├── CodTipoResumen.php
│   │   ├── EstadoTicket.php                 ← PENDIENTE|PROCESANDO|TERMINADO|ERROR
│   │   └── FasePeriodo.php                  ← PROPUESTA|PRELIMINAR|GENERADO
│   │
│   ├── Exceptions/
│   │   ├── SireException.php                ← base
│   │   ├── SireAuthException.php            ← credenciales rechazadas
│   │   ├── SireValidationException.php      ← errores 1001-2278
│   │   ├── SireTicketFailedException.php
│   │   └── SireErrorCatalog.php             ← mapa cod → mensaje legible
│   │
│   ├── DTO/
│   │   ├── TicketDTO.php
│   │   ├── PropuestaLineDTO.php
│   │   └── ResumenDTO.php
│   │
│   └── Support/
│       ├── PeriodoTributario.php            ← validador yyyymm
│       ├── NombreArchivoBuilder.php         ← genera nombre válido SUNAT
│       └── Base64MetadataEncoder.php        ← headers TUS
│
├── Http/Middleware/
│   └── EnsureSireAccess.php                 ← valida plan + credenciales SIRE
│
├── Console/Commands/Sire/
│   ├── SirePollPendingCommand.php           ← schedule: everyMinute
│   ├── SireRetryFailedCommand.php           ← schedule: everyFiveMinutes
│   └── SireReconcileAllCommand.php          ← schedule: dailyAt('03:00')
│
config/
├── sire.php                                 ← config aislado del módulo
└── facturacion.php                          ← SE EXTIENDE plans (añade 'sire')

database/migrations/
└── 2026_XX_XX_XXXXXX_sire_*.php             ← todas las tablas prefix sire_
```

### 2.1. Regla del namespace

- **Todo lo de SIRE** vive bajo `App\Sire\*` (nuevo namespace).
- **Excepción:** Controllers siguen la convención del proyecto (`App\Http\Controllers\Api\V1\Sire\*`) pero delegan TODO a los services bajo `App\Sire\Services\*`.
- **Prohibido:** ningún controller/service/job de facturación puede importar `App\Sire\*`. Si en algún momento se necesita cruce de datos, la integración va en `App\Sire\Services\Reconciliation\*` (SIRE lee facturación, no al revés).

### 2.2. Base de datos — prefijo `sire_`

Todas las tablas nuevas arrancan con `sire_`. Esto:
- Deja claro visualmente qué pertenece al módulo
- Permite backup/restore selectivo
- Evita colisiones de nombres genéricos (`periods`, `tickets`)

---

## 3. Esquema de base de datos

### 3.1. `sire_periodos`
Un registro por `(tenant, periodo)` representando el ciclo mensual del RCE.

```php
$table->id();
$table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
$table->string('per_tributario', 6);            // 202604
$table->string('cod_libro', 6)->default('080000');
$table->string('fase', 20)->default('propuesta'); // propuesta|preliminar|generado
$table->string('cod_estado', 10)->nullable();     // del padrón 5.33
$table->string('des_estado', 100)->nullable();
$table->timestamp('fec_cierre')->nullable();
$table->timestamps();
$table->unique(['tenant_id', 'per_tributario', 'cod_libro']);
$table->index(['tenant_id', 'fase']);
```

### 3.2. `sire_tickets`
Cada operación async de SUNAT. Fuente de verdad del estado de cualquier operación.

```php
$table->id();
$table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
$table->string('num_ticket', 20);                 // AAAA99999999
$table->string('per_tributario', 6);
$table->string('cod_proceso', 10);                // Anexo I
$table->string('des_proceso', 200)->nullable();
$table->string('cod_estado_proceso', 10);         // 01 pendiente, 03 procesando, 05 terminado, 07 error
$table->string('des_estado_proceso', 200)->nullable();
$table->string('nom_archivo_importacion', 200)->nullable();
$table->string('nom_archivo_reporte', 200)->nullable();
$table->string('cod_tipo_archivo_reporte', 10)->nullable();
$table->unsignedInteger('cnt_filas_validadas')->nullable();
$table->unsignedInteger('cnt_cp_informados')->nullable();
$table->unsignedInteger('cnt_cp_error')->nullable();
$table->string('archivo_local_path', 500)->nullable(); // S3 path
$table->unsignedInteger('poll_attempts')->default(0);
$table->timestamp('last_polled_at')->nullable();
$table->timestamp('finished_at')->nullable();
$table->json('sunat_request_payload')->nullable();
$table->json('sunat_last_response')->nullable();
$table->timestamps();
$table->unique(['tenant_id', 'num_ticket']);
$table->index(['tenant_id', 'cod_estado_proceso']);
$table->index(['tenant_id', 'per_tributario', 'cod_proceso']);
```

### 3.3. `sire_comprobantes`
Comprobantes parseados del TXT descargado. La unidad atómica de la reconciliación.

```php
$table->id();
$table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
$table->foreignId('sire_periodo_id')->constrained('sire_periodos');
$table->foreignId('origen_ticket_id')->nullable()->constrained('sire_tickets');
$table->string('fase', 20);                       // propuesta|preliminar|registrado
$table->string('car_sunat', 30)->nullable();      // identificador SUNAT
$table->date('fec_emision');
$table->date('fec_vencimiento')->nullable();
$table->string('cod_tipo_cdp', 2);                // 01, 03, 07, 08
$table->string('num_serie_cdp', 20);
$table->string('num_cdp', 20);
$table->string('num_doc_proveedor', 20);
$table->string('tipo_doc_proveedor', 2);
$table->string('razon_social_proveedor', 255);
$table->string('cod_moneda', 3)->default('PEN');
$table->decimal('mto_bi_gravada', 15, 2)->default(0);
$table->decimal('mto_igv', 15, 2)->default(0);
$table->decimal('mto_bi_no_gravada', 15, 2)->default(0);
$table->decimal('mto_total', 15, 2);
$table->decimal('tipo_cambio', 10, 4)->nullable();
$table->string('cod_inconsistencia', 10)->nullable();
$table->boolean('incluido')->default(true);
$table->text('raw_line')->nullable();             // línea original TXT
$table->timestamps();
$table->unique(['tenant_id', 'per_tributario', 'car_sunat'], 'sire_cp_unique'); // usar index nombre corto
$table->index(['tenant_id', 'per_tributario', 'fase']);
$table->index(['tenant_id', 'num_doc_proveedor']);
```

### 3.4. `sire_upload_files`
Historial de archivos .zip subidos vía TUS.

```php
$table->id();
$table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
$table->foreignId('sire_ticket_id')->nullable()->constrained();
$table->string('per_tributario', 6);
$table->string('cod_proceso', 10);
$table->string('nom_archivo', 200);
$table->string('local_path', 500);
$table->unsignedBigInteger('size_bytes');
$table->string('sha256', 64);
$table->timestamp('uploaded_at');
$table->timestamps();
$table->index(['tenant_id', 'per_tributario']);
```

### 3.5. `sire_reconciliation_logs`
Resultado histórico de cada reconciliación.

```php
$table->id();
$table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
$table->string('per_tributario', 6);
$table->unsignedInteger('total_sunat');
$table->unsignedInteger('total_local');
$table->unsignedInteger('match_count');
$table->unsignedInteger('only_sunat_count');      // SUNAT tiene, local no
$table->unsignedInteger('only_local_count');      // local tiene, SUNAT no
$table->unsignedInteger('diff_amount_count');
$table->decimal('diff_total_monto', 15, 2)->default(0);
$table->json('details')->nullable();
$table->timestamp('run_at');
$table->timestamps();
$table->index(['tenant_id', 'per_tributario']);
```

### 3.6. Extensión `tenants` (mínima, no invasiva)

**Regla:** solo columnas **nullable** y con default — cero impacto en tenants existentes.

```php
// 2026_XX_XX_add_sire_flags_to_tenants.php
Schema::table('tenants', function (Blueprint $table) {
    // Estado de habilitación (se activa cuando el tenant confirma que su app SOL tiene la URI SIRE seleccionada)
    $table->boolean('sire_enabled')->default(false);

    // Audit de última reconciliación automática
    $table->string('sire_last_period_synced', 6)->nullable();
    $table->timestamp('sire_last_reconciliation_at')->nullable();

    // (Opcional, solo si el tenant tiene una app SOL separada para SIRE distinta a la de CPE)
    // Si quedan NULL, SIRE usa client_id/client_secret globales del tenant.
    $table->string('sire_client_id', 100)->nullable();
    $table->string('sire_client_secret', 255)->nullable();
});
```

**Lógica de resolución de credenciales en `SireAuthService`:**

```php
$clientId     = $tenant->sire_client_id     ?? $tenant->client_id;
$clientSecret = $tenant->sire_client_secret ?? $tenant->client_secret;
```

**Lo que NO se modifica:** `ruc`, `sol_user`, `sol_pass`, `client_id`, `client_secret`, `razon_social`, `plan`, `api_key`, `api_secret`, etc. — se leen directamente sin cambiarles nada.

### 3.7. Flujo de onboarding sin fricciones

El cliente actual NO necesita re-registrar nada:

1. Cliente se registra con `POST /v1/registro` (ya existe) — pasa `ruc`, `sol_user`, `sol_pass`, `client_id`, `client_secret`, certificado, etc.
2. Cliente sube de plan a `business` (donde incluimos feature `sire`)
3. Cliente llama `POST /v1/sire/activar` — verifica credenciales contra SUNAT (hace un `getToken` de prueba), si OK marca `sire_enabled=true`
4. A partir de ahí puede consumir los endpoints SIRE

Si un cliente QUIERE credenciales separadas para SIRE (caso raro, solo si su contador gestiona SIRE aparte), puede actualizar `PUT /v1/empresa/sire-credenciales` pasando `sire_client_id` y `sire_client_secret`.

---

## 4. Configuración aislada

### 4.1. `config/sire.php` (nuevo)

```php
return [
    'hosts' => [
        'auth' => env('SIRE_AUTH_HOST', 'https://api-seguridad.sunat.gob.pe/v1'),
        'api'  => env('SIRE_API_HOST',  'https://api-sire.sunat.gob.pe/v1'),
    ],

    'scope' => 'https://api-sire.sunat.gob.pe',

    'cod_libro' => [
        'rce'  => '080000',
        'rvie' => '140000',
    ],

    'cod_origen_envio' => 2, // 2 = API (fijo)

    'token' => [
        'safety_margin_seconds' => 60,
        'cache_prefix' => 'sire_token:',
    ],

    'polling' => [
        'interval_seconds'  => 10,
        'max_attempts'      => 180, // 30 min
        'backoff_multiplier' => 1.5,
        'max_backoff'       => 60,
    ],

    'rate_limit' => [
        'per_tenant_per_minute' => 30,
    ],

    'storage' => [
        'disk' => env('SIRE_STORAGE_DISK', 'local'),
        'base_path' => 'sire',
    ],

    'queues' => [
        'poll'    => 'sire-poll',
        'heavy'   => 'sire-heavy',
        'process' => 'sire-process',
    ],
];
```

### 4.2. `config/facturacion.php` — extensión de planes

```php
'plans' => [
    'free' => [
        'features' => ['facturacion'],
    ],
    'pro' => [
        'features' => ['facturacion', 'consulta_cpe'],
    ],
    'business' => [
        'features' => ['facturacion', 'consulta_cpe', 'sire'],
    ],
],
```

El middleware `check.plan:sire` bloquea el acceso si el plan no incluye `sire`.

---

## 4.3. 🔐 Flujo multi-tenant completo (punta a punta)

```
┌──────────────────────────────────────────────────────────────────┐
│ CLIENTE (frontend, Postman, app propia)                          │
│ → GET /v1/sire/rce/202604/propuesta                              │
│   Headers: X-Api-Key: xxx, X-Api-Secret: yyy                     │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│ MIDDLEWARE resolve.tenant  (YA EXISTE, se reusa)                 │
│ → Busca tenant por api_key + api_secret                          │
│ → Inyecta $request->tenant = Tenant instance                     │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│ MIDDLEWARE check.plan:sire  (NUEVO, mínimo)                      │
│ → if (!in_array('sire', config('facturacion.plans')[$plan])) 403 │
│ → if (!$tenant->sire_enabled) 403                                │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│ SirePropuestaController@descargar($tenant, $periodo)             │
│ → delega a DownloadPropuestaService                              │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│ DownloadPropuestaService                                         │
│  1. $token = SireAuthService::getToken($tenant)                  │
│      ├─ Cache::get("sire_token:{$tenant->id}") || solicita nuevo │
│      └─ Usa $tenant->ruc, sol_user, sol_pass, client_id/secret   │
│  2. $response = SireHttpClient::get(url 5.34, ['Bearer $token']) │
│  3. $ticket = SireTicket::create([tenant_id, num_ticket, ...])   │
│  4. PollTicketJob::dispatch($ticket)                             │
│  5. return $ticket->num_ticket (HTTP 202 Accepted)               │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                (async, fuera del request)
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│ PollTicketJob (queue: sire-poll)                                 │
│  → every 10s: llama 5.31 hasta "TERMINADO"                       │
│  → al terminar: dispatch DownloadTicketFileJob                   │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│ DownloadTicketFileJob (queue: sire-heavy)                        │
│  → llama 5.32, descarga ZIP                                      │
│  → lo guarda en storage/sire/{tenant_id}/{periodo}/              │
│  → dispatch ProcessPropuestaJob                                  │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│ ProcessPropuestaJob (queue: sire-process)                        │
│  → descomprime, parsea TXT                                       │
│  → inserta en sire_comprobantes (bulk)                           │
│  → notifica webhook del tenant                                   │
└──────────────────────────────────────────────────────────────────┘
```

**Puntos críticos del aislamiento multi-tenant:**

1. Cada ticket y comprobante lleva `tenant_id` — queries siempre filtran por él
2. Cache de token tiene prefix `sire_token:{tenant_id}` — imposible cruzar tokens
3. Storage separado por `{tenant_id}/{periodo}` — imposible leer archivos ajenos
4. Rate limiter usa `by('sire:' . $tenant->id)` — imposible que un tenant sature a otro
5. Webhooks van solo al `tenant->webhook_url` dueño del ticket

---

## 5. Fases de implementación (orden sugerido)

### Fase 0 — Fundamentos (1 día)
**Entregable:** base sin lógica, pero todo compila y pasa migrations.

- [ ] Crear `config/sire.php`
- [ ] Crear directorio `app/Sire/` con subcarpetas vacías
- [ ] Agregar `"App\\Sire\\": "app/Sire/"` al autoload de `composer.json`
- [ ] `composer dump-autoload`
- [ ] Migration 1: `create_sire_periodos_table`
- [ ] Migration 2: `create_sire_tickets_table`
- [ ] Migration 3: `create_sire_comprobantes_table`
- [ ] Migration 4: `create_sire_upload_files_table`
- [ ] Migration 5: `create_sire_reconciliation_logs_table`
- [ ] Migration 6: `add_sire_flags_to_tenants` (solo columnas nullable)
- [ ] Extender `Tenant` model: agregar relaciones `sirePeriodos()`, `sireTickets()` y añadir `sire_enabled`, `sire_last_period_synced`, `sire_last_reconciliation_at`, `sire_client_id`, `sire_client_secret` al `$fillable` + `sire_client_secret` a `$casts = 'encrypted'`
- [ ] Correr `php artisan migrate` en staging
- [ ] Crear enums + DTOs + `SireException` base
- [ ] Crear modelos Eloquent con `belongsTo(Tenant::class)` ya definido

**Test gate:** `php artisan migrate:fresh` sin errores. Un tenant existente sigue funcionando en facturación sin cambios visibles.

### Fase 1 — Auth + HTTP client (1 día)
**Entregable:** poder sacar token de SUNAT para cualquier tenant.

- [ ] `SireAuthService::getToken(Tenant $t): string`
- [ ] Cache del token usando `expires_in` real (como hicimos con CPE)
- [ ] `SireHttpClient` envuelve Guzzle con Bearer + User-Agent + timeout
- [ ] `SireErrorCatalog` con tabla de errores 1001-2278
- [ ] Test Feature: `SireAuthServiceTest::test_returns_valid_token_for_active_tenant`

**Test gate:** llamar a `5.33 consultar años/meses` con un RUC real y recibir JSON válido.

### Fase 2 — Sistema de tickets + polling (2 días) — ⚠️ **crítico**
**Entregable:** cualquier operación async queda registrada, pooleada y descargada sin intervención manual.

- [ ] `TicketService::register(...)` guarda fila en `sire_tickets`
- [ ] `TicketService::fetchStatus($ticket)` llama 5.31
- [ ] `TicketService::downloadFile($ticket)` llama 5.32 y guarda en storage
- [ ] `PollTicketJob` — re-despacha a sí mismo con `release()` y backoff hasta terminar o fallar
- [ ] `DownloadTicketFileJob` — se dispara cuando ticket termina OK
- [ ] Comando `sire:poll-pending` que encola polls de tickets en estado "procesando" con más de X segundos sin poll
- [ ] Registrar comando en `Kernel::schedule`
- [ ] Test Feature con `Http::fake` simulando respuestas de SUNAT (pendiente → terminado)

**Test gate:** crear ticket manualmente en BD con `cod_estado_proceso = 03` → correr `sire:poll-pending` → ver que Job se ejecuta y actualiza.

### Fase 3 — Endpoints RCE felices (2-3 días)
**Entregable:** cliente puede descargar propuesta, aceptarla y registrar preliminar vía HTTP.

- [ ] `GET  /v1/sire/periodos`
- [ ] `GET  /v1/sire/rce/{periodo}/propuesta` → dispara 5.34, devuelve `202` + `num_ticket`
- [ ] `GET  /v1/sire/rce/{periodo}/resumen` → 5.35
- [ ] `POST /v1/sire/rce/{periodo}/aceptar-propuesta` → 5.2
- [ ] `POST /v1/sire/rce/{periodo}/registrar-preliminar` → 5.4
- [ ] `GET  /v1/sire/rce/{periodo}/constancia` → 5.49
- [ ] `GET  /v1/sire/tickets` (listar por tenant, filtros: periodo, estado)
- [ ] `GET  /v1/sire/tickets/{num}` (ver uno)
- [ ] `GET  /v1/sire/tickets/{num}/archivo` (descarga el ZIP local)
- [ ] `GET  /v1/sire/rce/{periodo}/comprobantes` (ya parseados, paginados)
- [ ] `PropuestaParser` que lee el TXT → crea rows en `sire_comprobantes`
- [ ] Middleware `check.plan:sire`

**Test gate:** con un RUC beta real, correr flujo completo: pedir propuesta → esperar ticket → ver comprobantes listados localmente.

### Fase 4 — Reconciliación local (1-2 días) — **gran diferenciador**
**Entregable:** detectar diferencias entre lo que SUNAT ve y lo que tu BD tiene.

- [ ] `ReconciliationService::run($tenant, $periodo)` compara `sire_comprobantes` vs `invoices` emitidas **por proveedores** (tablas de compra — ajustar si aún no las tienes)
- [ ] Reporte JSON con 4 categorías: match, only_sunat, only_local, diff_amount
- [ ] `GET /v1/sire/rce/{periodo}/reconciliar`
- [ ] Cron diario que reconcila el último periodo cerrado
- [ ] Webhook al tenant si hay diferencias > umbral

> **Nota:** si aún no tienes tabla de "compras registradas" localmente, esta fase puede reducirse a "resumen de propuesta vs ventas locales reflejadas" o postergarse hasta tener esa tabla.

### Fase 5 — Uploads TUS (2 días)
**Entregable:** reemplazar propuesta / cargar no domiciliados / complementar.

- [ ] `composer require ankitpokhrel/tus-php`
- [ ] `TusUploader::upload(file, metadata): numTicket`
- [ ] `ZipBuilder` que genera archivo según formato nombre SUNAT (posicional)
- [ ] `Base64MetadataEncoder`
- [ ] `POST /v1/sire/rce/{periodo}/reemplazar-propuesta` (multipart)
- [ ] `POST /v1/sire/rce/{periodo}/no-domiciliados`
- [ ] `POST /v1/sire/rce/{periodo}/complementar`

### Fase 6 — Ajustes posteriores (on-demand, cuando un cliente lo pida)
Servicios 5.18-5.29. Mismo patrón TUS + ticket. Se agregan cuando haya caso de uso real.

### Fase 7 — Reportes estadísticos (on-demand)
Servicios 5.50-5.58. Dashboard en el panel.

---

## 6. Infraestructura & operación

### 6.1. Colas y workers

```bash
# systemd / supervisor
[sire-poll]    php artisan queue:work redis --queue=sire-poll    --tries=30 --backoff=10 --timeout=60
[sire-heavy]   php artisan queue:work redis --queue=sire-heavy   --tries=3  --timeout=600
[sire-process] php artisan queue:work redis --queue=sire-process --tries=3  --timeout=300
[default]      php artisan queue:work redis --queue=default,sunat
```

### 6.2. Scheduler (`app/Console/Kernel.php`)

```php
$schedule->command('sire:poll-pending')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

$schedule->command('sire:retry-failed')
    ->everyFiveMinutes()
    ->withoutOverlapping();

$schedule->command('sire:reconcile-all')
    ->dailyAt('03:00')
    ->withoutOverlapping();
```

### 6.3. Rate limiting hacia SUNAT

```php
RateLimiter::for('sunat-sire', function ($job) {
    return Limit::perMinute(config('sire.rate_limit.per_tenant_per_minute'))
        ->by('sire:' . $job->tenantId);
});
```

Aplicar en cada Job:
```php
public $middleware = [new RateLimited('sunat-sire')];
```

### 6.4. Almacenamiento

- **Desarrollo:** `storage/app/sire/{tenant_id}/{periodo}/...`
- **Producción:** S3 bucket dedicado con lifecycle:
  - Archivos recientes: standard
  - >90 días: glacier
  - >3 años: delete (obligación legal = 4 años, pero SUNAT ya los tiene)

### 6.5. Observabilidad

- Log channel `sire` (`config/logging.php`) — archivos separados
- Cada Job loguea: `tenant_id`, `num_ticket`, `cod_proceso`, `duration_ms`
- Métricas Prometheus (si está disponible) con labels `{module='sire', operation, tenant_id}`
- Alertas:
  - >5% tickets en estado "error" por hora
  - Token auth fallando repetidamente para un tenant
  - Queue `sire-poll` con >1000 jobs pendientes

### 6.6. Seguridad

- `client_id`, `client_secret`, `sol_pass` ya están encriptados en `tenants` (cast `encrypted`) — reusar
- Endpoints SIRE bajo `middleware(['resolve.tenant', 'check.plan:sire', 'throttle:60,1'])`
- Logs NO deben incluir tokens ni credenciales (usar `Log::withContext` con mascarado)
- Archivos TXT/ZIP en storage privado, descarga solo con api_key del tenant dueño

---

## 7. Testing

### 7.1. Unit
- `SireAuthService` con HTTP mock
- `PropuestaParser` con fixtures de TXT reales
- `Base64MetadataEncoder` — coincide con ejemplo del PDF
- `NombreArchivoBuilder` — genera nombre válido

### 7.2. Feature
- Flujo descargar propuesta → ticket → parse → listar
- Flujo aceptar propuesta
- Flujo registrar preliminar
- Error: credenciales inválidas → 401 claro
- Error: periodo inválido → 422 con código SUNAT mapeado

### 7.3. Integración (con RUC beta)
- Al menos 1 test que corre contra SUNAT real (marcado `@group sunat-live`, solo CI manual)

---

## 8. Documentación al terminar cada fase

Cada fase completa agrega a `documentacion/17-Sire.md`:
- Endpoints expuestos (método, URL, params, ejemplo)
- Códigos de error mapeados
- Flujo de uso típico con ejemplos JSON

---

## 9. Checklist "no tocar facturación"

Antes de cada PR verificar:

- [ ] Ningún archivo bajo `app/Http/Controllers/Api/V1/*.php` (excepto el subdirectorio `Sire/`) fue modificado
- [ ] Ningún archivo bajo `app/Models/Invoice*.php`, `Boleta*.php`, `CreditNote*.php`, `DebitNote*.php`, `DispatchGuide*.php`, `InternalDocument*.php` fue modificado
- [ ] Ningún middleware de facturación (`resolve.tenant`, `check.limit`, etc.) fue modificado — solo se **consumen**
- [ ] Los nuevos endpoints usan solo middleware nuevo o los ya existentes **sin cambiarlos**
- [ ] `config/facturacion.php` solo **agrega** key `features` al array de planes, no modifica las existentes
- [ ] Migrations SIRE solo crean tablas nuevas o agregan columnas **NULLABLE** a `tenants`
- [ ] `composer.json` solo agrega dependencias, no remueve ni downgrada
- [ ] El modelo `Tenant` solo agrega relaciones y campos al `$fillable` — no altera los existentes, no cambia `$hidden`, `$casts` (excepto agregar `sire_client_secret` a encrypted)
- [ ] `RegisterController` (registro de empresas) NO se toca — los campos SIRE quedan con default y se configuran luego vía `POST /v1/sire/activar`

---

## 9.1. Regla de oro del aislamiento

> **La facturación no sabe que SIRE existe. SIRE sabe que la facturación existe y la consume como dato, nunca la modifica.**

Dirección permitida de dependencias:

```
┌──────────────────────┐
│   SIRE (App\Sire\*)  │ ──lee──► │ Tenant, Invoice, Boleta │  ✅
└──────────────────────┘                  (solo SELECT)

┌──────────────────────┐
│     FACTURACIÓN      │ ──X──► │     App\Sire\*          │  ❌ PROHIBIDO
└──────────────────────┘
```

Si en algún momento la facturación "necesita" saber algo de SIRE (caso raro), se agrega un evento en facturación que SIRE escucha — nunca una llamada directa.

---

## 10. Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|------------|
| TUS.io complejo en PHP | Alta | Alto | Postponer a Fase 5; empezar con endpoints que no requieren upload |
| SUNAT beta caído | Media | Medio | Mocks + env switch; reintentos con backoff exponencial |
| Formato TXT cambia | Baja | Alto | Parser con tests por versión; log de línea original |
| Tickets quedan "colgados" | Media | Medio | Comando `sire:cleanup-stale` que marca como expirados >30min |
| Credenciales SOL expiran / cambian | Media | Alto | Detectar 401 repetidos → desactivar `sire_enabled` del tenant + alerta |
| Archivos ZIP gigantes (>1GB) | Baja | Medio | Streaming en vez de cargar en memoria; timeout largo en worker |

---

## 11. Definición de "terminado" (MVP)

El MVP está listo cuando un tenant en plan `business`, con credenciales SIRE configuradas, puede:

1. `GET /v1/sire/periodos` → ver periodos disponibles
2. `GET /v1/sire/rce/202604/propuesta` → ticket creado, polling automático
3. Minutos después: `GET /v1/sire/rce/202604/comprobantes` → lista local paginada
4. `POST /v1/sire/rce/202604/aceptar-propuesta` → ticket creado
5. `POST /v1/sire/rce/202604/registrar-preliminar` → ticket creado
6. `GET /v1/sire/rce/202604/constancia` → PDF descargable
7. `GET /v1/sire/rce/202604/reconciliar` → JSON con diferencias vs local

Sin un solo cambio en tablas/controllers/services de facturación electrónica.

---

## 12. Próximo paso

Comenzar **Fase 0**: crear estructura de carpetas, migrations, enums, config. Sin lógica todavía — solo esqueleto que compile y pase migrations. Luego Fase 1 (auth) y Fase 2 (tickets). Esas tres fases juntas son el fundamento; sin ellas el resto no funciona.

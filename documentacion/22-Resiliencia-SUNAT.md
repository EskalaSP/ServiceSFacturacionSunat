# Resiliencia ante caídas de SUNAT

Guía técnica para el programador que despliega en staging o producción. Explica el sistema de reintentos, circuit breaker y cómo operar ante una caída de SUNAT.

---

## El problema que resuelve esto

SUNAT cae con frecuencia: mantenimientos nocturnos, sobrecargas en fechas de vencimiento, actualizaciones de certificados. Sin un mecanismo de resiliencia, una caída de 4 horas en producción con 1000+ clientes activos produce:

- Miles de facturas marcadas como rechazadas permanentemente
- Workers del queue consumiendo CPU intentando requests que siempre fallan
- Efecto manada al recuperarse: todos los jobs reintentan a la vez → SUNAT vuelve a caerse

Este sistema resuelve los tres problemas.

---

## Componentes implementados

### 1. `SendDocumentToSunat` — Reintentos extendidos

**Archivo:** `app/Jobs/SendDocumentToSunat.php`

| Parámetro | Valor | Significado |
|---|---|---|
| `$tries` | 20 | Intentos máximos antes de rendirse |
| `$timeout` | 90 seg | Tiempo máximo por intento |
| Backoff total | ~15 horas | Ventana de reintento antes del estado `SUNAT_TIMEOUT` |
| Backoff final | 1 hora | Los últimos 10 reintentos esperan 1 hora cada uno |

**Comportamiento por tipo de error:**

```
SoapFault (red/timeout)    → circuit breaker + reintento con backoff
Código SUNAT retryable     → reintento con backoff (0, 100, 109, 500, 1033, 2800)
Código SUNAT permanente    → falla inmediata, estado "rechazado"
Certificado inválido       → falla inmediata, estado "rechazado"
```

**Después de 20 intentos sin éxito:**
- Estado: `pendiente` (NO rechazado — se puede reenviar manualmente)
- Código: `SUNAT_TIMEOUT`
- Webhook: `document.timeout_sunat` (distinguible de `document.rejected`)

> **Por qué `pendiente` y no `rechazado`:** El documento es válido — SUNAT simplemente no estaba disponible. Marcarlo como rechazado permanente sería incorrecto y confundiría a los clientes.

---

### 2. `SunatCircuitBreaker` — Freno automático

**Archivo:** `app/Services/Sunat/SunatCircuitBreaker.php`

**Diagrama de estados:**

```
                  5 SoapFaults
                  en 60 segundos
                       ↓
┌──────────┐      ┌──────────┐      ┌───────────┐
│  CLOSED  │─────▶│   OPEN   │─────▶│ HALF_OPEN │
│ (normal) │      │ (pausado)│ TTL  │  (prueba) │
└──────────┘      └──────────┘ exp. └───────────┘
      ▲                                    │
      │         prueba OK                  │
      └────────────────────────────────────┘
                              │ prueba falla
                              ▼
                         OPEN (TTL x2)
```

**Qué hace cada estado:**

| Estado | Comportamiento del job |
|---|---|
| `closed` | Envía normalmente a SUNAT |
| `open` | Job no toca SUNAT, se libera en `release(300 + jitter)` |
| `half_open` | Solo 1 job pasa como "probe". El resto espera |

**Jitter:** cuando el circuit está `open`, los jobs se liberan con `300 + random(0, 120)` segundos de espera. Esto evita que millones de jobs reinvadan SUNAT exactamente al mismo segundo cuando el circuito se cierra.

**Almacenamiento:** Laravel Cache (funciona con `file`, `database`, `redis`). En producción usar Redis (ver sección de configuración).

---

### 3. Endpoint `GET /api/v1/sunat/estado`

Permite a clientes integradores consultar el estado de SUNAT antes de enviar facturas masivas.

**Request:**
```http
GET /api/v1/sunat/estado
X-Api-Key: ...
X-Api-Secret: ...
```

**Response cuando todo OK:**
```json
{
    "estado": "exito",
    "sunat": {
        "disponible": true,
        "circuit": "closed",
        "fallas": 0,
        "entorno": "produccion"
    }
}
```

**Response cuando SUNAT está caído:**
```json
{
    "estado": "exito",
    "sunat": {
        "disponible": false,
        "circuit": "open",
        "fallas": 7,
        "entorno": "produccion"
    }
}
```

**Uso recomendado para clientes que envían masivos:**
```javascript
const status = await fetch('/api/v1/sunat/estado', { headers });
if (!status.sunat.disponible) {
    // No enviar ahora, SUNAT está caído
    // Mostrar aviso al usuario
}
```

---

### 4. Comando `sunat:circuit`

Herramienta de operaciones para incidentes en tiempo real.

```bash
# Ver estado actual (beta y producción)
php artisan sunat:circuit status

# Salida:
# [beta]       Estado: closed     | Fallas: 0 | Endpoint: https://...
# [produccion] Estado: open       | Fallas: 6 | Endpoint: https://...

# Forzar cierre manual (después de confirmar que SUNAT está OK)
php artisan sunat:circuit close
php artisan sunat:circuit close --entorno=produccion

# Pausar envíos antes de un mantenimiento programado
php artisan sunat:circuit open --entorno=produccion
```

---

## Configuración por entorno

### Desarrollo / Staging (configuración mínima)

```dotenv
QUEUE_CONNECTION=database
CACHE_DRIVER=file
```

Funciona sin Redis. El circuit breaker usa archivos para el estado. Suficiente para pruebas.

### Producción con hasta ~10.000 clientes

```dotenv
QUEUE_CONNECTION=database
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=tu_password_seguro
```

Queue en base de datos, cache en Redis. El circuit breaker ya es rápido.

### Producción con 100.000+ clientes (recomendado)

```dotenv
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=tu_password_seguro
```

Queue y cache en Redis. Sin este cambio, la tabla `jobs` se convierte en un cuello de botella con alto tráfico.

**Extensión PHP requerida para Redis:**
```bash
apt install php8.3-redis
# o con pecl:
pecl install redis
```

**Verificar que Redis está activo:**
```bash
redis-cli ping   # debe responder PONG
php artisan tinker
>>> Cache::put('test', 1, 5); Cache::get('test');  // debe devolver 1
```

---

## Workers del queue

### Configuración mínima (1 worker)

```bash
php artisan queue:work --sleep=3 --tries=1 --timeout=120
```

> `--tries=1` porque el job ya maneja sus propios reintentos internamente con `$this->release()`.

### Producción con Supervisor (recomendado)

Crea `/etc/supervisor/conf.d/api-pro-worker.conf`:

```ini
[program:api-pro-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/api-pro/artisan queue:work redis --sleep=3 --tries=1 --timeout=120 --max-jobs=500
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/api-pro-worker.log
stopwaitsecs=3600
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start api-pro-worker:*
```

**`numprocs=4`:** 4 workers paralelos. Ajusta según CPU disponible y volumen esperado. Con Redis queue, puedes escalar a 20+ workers sin problemas.

**`--max-jobs=500`:** Cada worker se reinicia limpiamente después de 500 jobs. Evita memory leaks en procesos de larga duración.

### Reinicio después de deploy

```bash
# Enviar señal de parada limpia (los workers terminan el job actual y paran)
php artisan queue:restart

# Supervisor los relanza automáticamente con el código nuevo
supervisorctl status api-pro-worker:*
```

---

## Webhook: diferenciar errores

Los clientes que usan webhooks deben manejar dos eventos distintos:

| Evento | Significado | Acción recomendada para el cliente |
|---|---|---|
| `document.sent` | Aceptado por SUNAT | Guardar hash, generar PDF |
| `document.rejected` | Rechazado definitivamente (XML inválido, datos incorrectos) | Corregir y reenviar con datos nuevos |
| `document.timeout_sunat` | SUNAT no disponible tras 20 intentos | Esperar y llamar `POST /reenviar` cuando SUNAT vuelva |

**Ejemplo de handler en el cliente:**

```php
// En el webhook handler del cliente integrador:
switch ($payload['evento']) {
    case 'document.timeout_sunat':
        // SUNAT estaba caído. El documento está OK, solo hay que reenviar.
        // Guardamos para reintento manual o automático.
        $this->scheduleResend($payload['datos']['id']);
        break;

    case 'document.rejected':
        // Error real: datos incorretos, XML inválido, etc.
        // Hay que revisar y corregir.
        $this->notifyUser($payload['datos']['id'], 'error');
        break;
}
```

**Para reenviar documentos en estado `SUNAT_TIMEOUT`:**
```http
POST /api/v1/facturas/{id}/reenviar
X-Api-Key: ...
X-Api-Secret: ...
```

---

## Playbook de incidentes: SUNAT cae en producción

### Paso 1 — Detectar (automático)

El circuit breaker abre solo después de 5 SoapFaults en 60 segundos. Todos los jobs nuevos se pausan sin tocar SUNAT.

Para confirmarlo:
```bash
php artisan sunat:circuit status
# [produccion] Estado: open | Fallas: 8
```

### Paso 2 — Esperar o monitorear

Durante la caída no hay nada que hacer. Los jobs esperan en la cola con backoff.

Verifica cuántos documentos están pendientes:
```sql
SELECT sunat_status, COUNT(*) FROM invoices
WHERE created_at > NOW() - INTERVAL 4 HOUR
GROUP BY sunat_status;
```

### Paso 3 — SUNAT vuelve

El circuit pasa a `half_open` automáticamente después de 5 minutos. Un job de prueba pasa. Si tiene éxito, el circuit vuelve a `closed` y todos los jobs pendientes se procesan gradualmente (con jitter).

Si quieres acelerar la recuperación manualmente:
```bash
php artisan sunat:circuit close --entorno=produccion
```

### Paso 4 — Documentos con SUNAT_TIMEOUT

Si la caída duró más de 15 horas, algunos documentos quedarán en `pendiente` con código `SUNAT_TIMEOUT`. Para reenviarlos masivamente:

```bash
php artisan tinker
>>> \App\Models\Invoice::where('sunat_code', 'SUNAT_TIMEOUT')->each(function($inv) {
...     \App\Jobs\SendDocumentToSunat::dispatch(\App\Models\Invoice::class, $inv->id);
... });
```

> Ejecuta esto con SUNAT ya estable. Los jobs se distribuirán con jitter automáticamente.

---

## Ajuste de parámetros del circuit breaker

Los valores por defecto cubren el 95% de los casos. Para ajustar, modifica la instanciación en `SendDocumentToSunat::handle()`:

```php
$cb = new SunatCircuitBreaker(
    failureThreshold:     5,   // Fallas para abrir el circuito
    failureWindowSeconds: 60,  // Ventana de tiempo para contar fallas
    openTtlSeconds:       300, // Segundos que permanece OPEN (5 min)
    halfOpenTtlSeconds:   30,  // Segundos para el estado de prueba
);
```

| Escenario | Ajuste sugerido |
|---|---|
| SUNAT con mucha inestabilidad | `failureThreshold: 10`, `openTtlSeconds: 600` |
| Ambiente de pruebas | `failureThreshold: 2`, `openTtlSeconds: 60` |
| Alta tolerancia a errores | `failureThreshold: 20`, `failureWindowSeconds: 120` |

---

## Tests para verificar el sistema

```bash
# Simular caída de SUNAT (abre el circuit manualmente)
php artisan sunat:circuit open --entorno=beta

# Enviar una factura (debería encolarse pero NO llegar a SUNAT)
curl -X POST /api/v1/facturas ...

# Verificar que el estado es "enviado" (en queue, esperando)
curl GET /api/v1/facturas/{id}
# sunat.estado = "enviado", no "rechazado"

# Restaurar circuit
php artisan sunat:circuit close --entorno=beta

# El worker procesa el job pendiente y llega a SUNAT
php artisan queue:work --once
```

---

## Archivos modificados / creados en esta implementación

| Archivo | Cambio |
|---|---|
| `app/Jobs/SendDocumentToSunat.php` | `$tries=20`, backoff extendido a 15h, integración con circuit breaker, jitter, estado `SUNAT_TIMEOUT` |
| `app/Services/Sunat/SunatCircuitBreaker.php` | **Nuevo.** Circuit breaker con estados CLOSED/OPEN/HALF_OPEN usando Cache |
| `app/Console/Commands/SunatCircuitCommand.php` | **Nuevo.** Comando `sunat:circuit status\|open\|close` |
| `routes/api.php` | **Nuevo endpoint** `GET /api/v1/sunat/estado` |
| `vendor/greenter/xml/.../invoice2.1.xml.twig` | `languageLocaleID` restaurado (correcto para producción) |

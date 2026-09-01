# Procedimientos del Sistema — Facturación SUNAT

> Documento de referencia interna. Basado 100 % en el código real del proyecto.
> Última revisión: 2026-08-31

---

## ÍNDICE

1. [Estados de un documento — la guía definitiva](#1-estados-de-un-documento)
2. [¿Cuándo se envía a SUNAT? — modos de creación](#2-modos-de-creación-y-envío)
3. [Proceso: Crear Boleta](#3-proceso-crear-boleta)
4. [Proceso: Crear Factura](#4-proceso-crear-factura)
5. [Proceso: Envío a SUNAT (Job)](#5-proceso-envío-a-sunat-job)
6. [Proceso: Reintentos automáticos (Cron)](#6-proceso-reintentos-automáticos-cron)
7. [Proceso: Anular Boleta (Resumen Diario RC)](#7-proceso-anular-boleta)
8. [Proceso: Anular Factura (Comunicación de Baja RA)](#8-proceso-anular-factura)
9. [Cron Jobs — tabla completa](#9-cron-jobs)
10. [Diagnóstico: ¿Qué pasó con este documento?](#10-diagnóstico-de-estados)
11. [Consultas SQL útiles para monitoreo](#11-consultas-sql)

---

## 1. Estados de un documento

Cada documento (boleta, factura, nota de crédito, nota de débito) tiene un campo
`sunat_status` con **6 valores posibles**. Estos son los únicos que existen en la base
de datos:

| Estado | ¿Quién lo asigna? | ¿Qué significa exactamente? |
|--------|------------------|-----------------------------|
| `pendiente` | Action de creación | Guardado en DB. Aún no entró a la cola de envío. |
| `enviado` | Controller / Action | Entró a la cola de SUNAT. El worker aún no terminó de procesarlo. |
| `aceptado` | Job SendDocumentToSunat | SUNAT aceptó el documento. Tiene CDR. No se puede editar. |
| `rechazado` | Job SendDocumentToSunat | SUNAT rechazó por error de validación permanente (ej: RUC inválido). |
| `anulado` | Job de anulación | Anulación completada. El documento ya no tiene validez tributaria. |
| `anulacion_en_proceso` | VoidedService / SummaryService | Se inició el proceso de anulación pero SUNAT aún no confirmó. |

### Reglas importantes

- Un documento en `aceptado` **no se puede editar**. Hay que emitir Nota de Crédito.
- Un documento en `rechazado` se puede **reenviar** con `POST /boletas/{id}/enviar`.
- Un documento en `anulado` es **definitivo**. No se puede reactivar.
- `pendiente` con `sunat_code = 'SUNAT_TIMEOUT'` significa que el sistema intentó
  20 veces pero SUNAT no respondió. Se puede reenviar manualmente.

---

## 2. Modos de creación y envío

**Respuesta directa a la pregunta: ¿al crear un documento se envía o espera cola?**

Al crear un documento hay **3 modos**, controlados por parámetros del request:

```
POST /api/v1/boletas
POST /api/v1/facturas
```

### Modo A — Automático (por defecto)

```json
{ "enviar_automatico": true }   ← este es el valor por defecto si no lo mandas
```

| Paso | Qué ocurre | Estado resultante |
|------|-----------|-------------------|
| 1 | Se guarda en DB | `pendiente` |
| 2 | Se pone en la COLA de SUNAT (no espera) | `pendiente` todavía |
| 3 | El controller cambia el estado ANTES de responder | `enviado` |
| 4 | El API responde al cliente con el documento en `enviado` | — |
| 5 | En paralelo, el worker procesa la cola | — |
| 6 | SUNAT responde OK | `aceptado` |
| 6b | SUNAT responde error permanente | `rechazado` |
| 6c | SUNAT no responde (error de red) | sigue en `enviado`, reintenta con backoff |
| 7 | Si agota 20 intentos | vuelve a `pendiente` con code `SUNAT_TIMEOUT` |

> **Importante**: El API responde inmediatamente en paso 4. NO espera a que SUNAT
> responda. El estado final (`aceptado`/`rechazado`) llega minutos después, en background.

### Modo B — Solo guardar, enviar luego

```json
{ "enviar_automatico": false }
```

| Paso | Qué ocurre | Estado resultante |
|------|-----------|-------------------|
| 1 | Se guarda en DB | `pendiente` |
| 2 | NO se encola nada | `pendiente` |
| 3 | El API responde | — |
| — | El cron `sunat:reintentar-pendientes` lo detecta en 15 min | `enviado` |
| — | ...o el usuario llama manualmente `POST /boletas/{id}/enviar` | `enviado` |

### Modo C — Solo registro (solo boletas, para Resumen Diario)

```json
{ "solo_registro": true }   ← solo aplica a boletas
```

| Paso | Qué ocurre | Estado resultante |
|------|-----------|-------------------|
| 1 | Se guarda en DB | `pendiente` |
| 2 | NO se encola nada nunca de forma automática | `pendiente` |
| 3 | Hay que crear un Resumen Diario RC manualmente | — |
| — | El Resumen Diario envía todas las boletas `pendiente` de esa fecha | en proceso |

> Usar esto solo cuando integrás por lotes y querés acumular boletas del día
> para enviarlas juntas en un solo Resumen.

---

## 3. Proceso: Crear Boleta

**Archivo principal:** `app/Actions/Documents/CreateBoletaAction.php`

### Datos de entrada (request JSON)

```json
{
  "serie": "B001",
  "fecha_emision": "2026-08-31",
  "tipo_moneda": "PEN",
  "forma_pago": "Contado",
  "enviar_automatico": true,
  "cliente": {
    "tipo_doc": "1",
    "num_doc": "12345678",
    "razon_social": "Cliente Genérico",
    "direccion": "Av. Lima 123"
  },
  "items": [
    {
      "codigo": "P001",
      "descripcion": "Producto",
      "unidad": "NIU",
      "cantidad": 1,
      "mto_precio_unitario": 118.00,
      "tip_afe_igv": "10"
    }
  ],
  "pagos": [
    { "metodo_pago": "Efectivo", "monto": 118.00 }
  ]
}
```

### Flujo interno paso a paso

```
1. StoreBoletaRequest::validate()
   → Valida formato, campos requeridos, catálogos SUNAT

2. CreateBoletaAction::execute() [dentro de DB::transaction]
   → Serie::lockForUpdate() busca serie tipo "03" activa
   → serie.correlativo++ (atómico, evita duplicados)
   → ClientResolverService::resolve() → upsert en tabla clients
   → DocumentCalculationService::calculateItems() → calcula IGV/ISC/ICBPER por ítem
   → DocumentCalculationService::calculateTotals() → totales de la boleta
   → Boleta::create([sunat_status => 'pendiente'])
   → BoletaItem::create() por cada ítem
   → event(DocumentCreated)
   → PlanService::incrementUsage() → suma 1 al contador mensual del tenant

3. Si enviar_automatico = true:
   → SendDocumentToSunat::dispatch(Boleta::class, $id)  ← pone en COLA, no ejecuta ya
   → $boleta->update([sunat_status => 'enviado'])        ← cambia estado AHORA

4. Si hay pagos:
   → RegisterPaymentAction::execute() → tabla payments
   → payment_status actualizado (pendiente / parcial / pagado)

5. Retorna boleta con items, payments, client cargados
```

### Estado al salir del proceso de creación

| Condición | `sunat_status` al responder |
|-----------|---------------------------|
| `enviar_automatico=true` (default) | `enviado` |
| `enviar_automatico=false` | `pendiente` |
| `solo_registro=true` | `pendiente` |

### Tablas modificadas

| Tabla | Operación |
|-------|-----------|
| `series` | UPDATE correlativo + 1 |
| `clients` | INSERT o UPDATE (upsert) |
| `boletas` | INSERT |
| `boleta_items` | INSERT (uno por ítem) |
| `payments` | INSERT (si hay pagos) |

---

## 4. Proceso: Crear Factura

**Archivo principal:** `app/Actions/Documents/CreateInvoiceAction.php`

### Diferencias con Boleta

| Aspecto | Boleta | Factura |
|---------|--------|---------|
| Serie | B001, BC01 | F001, FC01 |
| Cliente tipo_doc | 0, 1, 4, 6 | Solo `6` (RUC obligatorio) |
| `solo_registro` | Sí | No (no existe ese modo) |
| Detracción | No | Sí (campo JSON `detraccion`) |
| Anulación | Resumen Diario RC | Comunicación de Baja RA |
| Tablas | `boletas` / `boleta_items` | `invoices` / `invoice_items` |

El flujo interno es idéntico. Las mismas tablas modificadas, mismo Job de envío.

---

## 5. Proceso: Envío a SUNAT (Job)

**Archivo:** `app/Jobs/SendDocumentToSunat.php`

Este Job se ejecuta en el worker de colas (no en el request HTTP). Es el que
realmente habla con SUNAT.

### Configuración del Job

```
Cola:       sunat  (prioridad máxima)
Intentos:   20 máximo
Timeout:    90 segundos por intento
Backoff:    15s, 30s, 1m, 2m, 5m, 10m, 20m, 30m, 1h, 1h, 1h... (≈15h total)
Rate limit: máx 120 documentos/minuto por RUC (configurable)
```

### Flujo interno

```
1. SunatCircuitBreaker::isAvailable()
   → Si SUNAT estuvo fallando mucho, el circuit está ABIERTO
   → Si está abierto: release(300 + jitter) y sale SIN contar como intento
   → El documento queda en 'enviado', el job se reencola 5 min después

2. GreenterService::buildInvoice() o buildNote()
   → Arma el objeto PHP de Greenter con todos los datos

3. GreenterService::send()
   → Construye XML UBL 2.1 sin firma
   → Firma el XML con el certificado digital del tenant
   → Envía por SOAP al endpoint de SUNAT (beta o producción según tenant.environment)

4. Analiza la respuesta de SUNAT:
```

### Tabla de respuestas posibles y acción tomada

| Código SUNAT | Tipo | Acción del Job | Estado final |
|-------------|------|---------------|-------------|
| `0` (CDR OK) | Aceptado | Guarda XML + CDR + PDF, dispara webhook | `aceptado` |
| `3xxx` | Aceptado con observación | Igual que código 0 | `aceptado` |
| `0` vía BillResult exitoso | Aceptado | Mismo | `aceptado` |
| `100` | Error temporal (timeout SUNAT) | Reintenta con backoff | `enviado` (sigue) |
| `109` | Servicio caído | Reintenta con backoff | `enviado` (sigue) |
| `500` | Error interno SUNAT | Reintenta con backoff | `enviado` (sigue) |
| `1033` | Error transitorio SUNAT | Reintenta con backoff | `enviado` (sigue) |
| `2800` | Correlativo duplicado (concurrencia) | Reintenta con backoff | `enviado` (sigue) |
| `HTTP` / `NETWORK_ERROR` | Red caída | Circuit breaker + reintenta | `enviado` (sigue) |
| `SoapFault` | SUNAT no responde | Circuit breaker + reintenta | `enviado` (sigue) |
| `2xxx` (salvo 2800) | Error de validación permanente | NO reintenta | `rechazado` |
| `CERT_ERROR` | Certificado inválido | NO reintenta | `rechazado` |
| Agotó 20 intentos | Cualquier error retryable | Deja en pendiente para reenvío manual | `pendiente` (SUNAT_TIMEOUT) |

### Almacenamiento de archivos

```
storage/app/public/{ruc}/{cod_local}/{fecha}/xml/  ← XML firmado
storage/app/public/{ruc}/{cod_local}/{fecha}/cdr/  ← CDR ZIP de SUNAT
storage/app/public/{ruc}/{cod_local}/{fecha}/pdf/  ← PDF generado automáticamente
```

### ¿Qué dispara después del éxito?

```
→ DocumentSent event
→ NotifyWebhookJob::dispatch() si tenant tiene webhook_url configurado
→ PdfGeneratorService::generateAndStore() (en background, fallo silencioso)
```

---

## 6. Proceso: Reintentos automáticos (Cron)

### El problema que resuelve

Cuando se crea un documento con `enviar_automatico=true`, el sistema pone el job
en la cola y cambia el estado a `enviado`. Pero si el **worker de colas no está
corriendo** (o se cayó), el job está en la cola pero nadie lo procesa. El documento
queda en `enviado` para siempre.

Para recuperar esos documentos existe el comando `sunat:reintentar-pendientes`.

### Comando: `sunat:reintentar-pendientes`

**Archivo:** `app/Console/Commands/ReintentarComprobantesPendientes.php`
**Frecuencia:** cada 15 minutos (vía `routes/console.php`)
**Tablas que lee:** `invoices`, `boletas`

```
Condiciones para reencolar un documento:
✓ sunat_status IN ('pendiente', 'enviado')
✓ updated_at <= hace 15 minutos  ← no toca lo que el worker puede estar procesando
✓ created_at >= hace 7 días      ← no toca documentos muy viejos
✗ NO toca los 'rechazado'        ← esos necesitan corrección manual
✗ NO toca los 'aceptado'         ← ya está bien
✗ NO toca los 'anulado'          ← ya está bien
```

**Resultado:** Despacha `SendDocumentToSunat::dispatch()` y deja el estado en `enviado`.

### ¿Cómo saber si el cron se detuvo?

| Señal | Diagnóstico |
|-------|-------------|
| Documentos en `enviado` por más de 20 min sin cambiar | El worker de colas no está corriendo |
| Documentos en `pendiente` que tienen >15 min de `updated_at` | El cron no está corriendo (o el worker tampoco) |
| Muchos docs en `enviado` con `sunat_code` vacío | Job en cola pero no procesado |
| `sunat_code = 'SUNAT_TIMEOUT'` en `pendiente` | SUNAT estuvo caído 15h, necesita reenvío manual |
| `sunat_code` empieza con `1` o es `HTTP` | SUNAT tiene problemas técnicos temporales |
| `sunat_code` empieza con `2` | Error de validación del documento, no hay reintento |

**Consulta rápida para ver documentos atascados:**
```sql
-- Documentos que llevan más de 30 minutos en 'enviado' (worker probablemente caído)
SELECT 'invoice' tipo, id, serie, correlativo, sunat_status, sunat_code, updated_at
FROM invoices
WHERE sunat_status = 'enviado'
  AND updated_at < NOW() - INTERVAL 30 MINUTE
UNION ALL
SELECT 'boleta' tipo, id, serie, correlativo, sunat_status, sunat_code, updated_at
FROM boletas
WHERE sunat_status = 'enviado'
  AND updated_at < NOW() - INTERVAL 30 MINUTE
ORDER BY updated_at;
```

---

## 7. Proceso: Anular Boleta

**Mecanismo oficial SUNAT:** Resumen Diario con tipo anulación **(RC-)**
**Archivo principal:** `app/Services/Documents/SummaryService.php`
**Tabla afectada:** `summaries`

### Regla operativa importante

Cuando se anula una boleta, el sistema **no anula dos veces la boleta original**.
Lo que existe como entidad que viaja a SUNAT es el **Resumen Diario RC**. La boleta
original pasa primero a `anulacion_en_proceso` y solo cambia a `anulado` cuando SUNAT
acepta el RC. Si el RC falla o es rechazado, la boleta vuelve a `aceptado`.

En otras palabras:

- **Se reintenta el RC**, no la boleta original.
- Si el error es transitorio, el sistema reintenta solo.
- Si el RC queda en `rechazado`, hay que reenviar el mismo resumen desde el endpoint
   de envío, no volver a marcar manualmente la boleta como anulada.

### Restricciones

- Solo boletas con `sunat_status = 'aceptado'`
- Máximo 7 días calendario desde la fecha de emisión
- No se puede si ya tiene una Nota de Crédito asociada

### Flujo

```
1. POST /api/v1/resumen-diario  con  anular: [{id: X, motivo: "..."}]
   ↓ SummaryController::store()
   ↓ SummaryService::crear(tenant, fecha, anular=[...])

2. Valida: boleta existe, está aceptada, dentro de 7 días

3. Crea registro en summaries:
   identifier = "RC-YYYYMMDD-NNN"
   tipo = 'anulacion'
   sunat_status = 'enviado'
   document_ids = [id_boleta1, id_boleta2, ...]

4. Boleta cambia a: sunat_status = 'anulacion_en_proceso'

5. GreenterService::buildSummary() → XML RC firmado
6. SUNAT responde con TICKET (no da CDR inmediato)
7. summaries.ticket = "el ticket de SUNAT"

8. [ASÍNCRONO] summaries:poll-pending cada 5 min
   → CheckSummaryTicketStatus Job consulta el ticket
   → Primeros 6 intentos: cada 10 minutos
   → Siguientes intentos: cada 4 horas
   → Máximo 15 días intentando

9. SUNAT confirma:
   → boleta.sunat_status = 'anulado'
   → summaries.sunat_status = 'aceptado'

   SUNAT rechaza:
   → boleta.sunat_status = 'aceptado' (se revierte)
   → summaries.sunat_status = 'rechazado'
```

### Qué pasa según el resultado real

| Resultado del RC | Estado del resumen | Estado de la boleta original | ¿Qué hacer? |
|------------------|-------------------|-------------------------------|-------------|
| SUNAT acepta | `aceptado` | `anulado` | Nada más. |
| SUNAT rechaza por validación | `rechazado` | `aceptado` | Corregir y reenviar el mismo RC. |
| SUNAT sigue procesando | `enviado` | `anulacion_en_proceso` | Esperar el polling automático. |
| Error de red / timeout transitorio | `enviado` | `anulacion_en_proceso` | El sistema reintenta solo. |

### Qué se reintenta automáticamente

El job `CheckSummaryTicketStatus` consulta el ticket de SUNAT cuando el resumen
está en `enviado` y todavía no llegó a estado final.

- Si SUNAT responde `0` o `187`, significa que sigue procesando.
- Si la llamada falla por red o SOAP, el job se vuelve a programar con backoff.
- Si SUNAT responde un error numérico definitivo, el resumen pasa a `rechazado`.

Eso quiere decir que **no necesitas volver a crear la boleta para insistir**.
Solo reenvías el RC si el resumen ya quedó rechazado.

### Estados de la tabla `summaries`

| Estado | Significado |
|--------|-------------|
| `enviado` | XML RC enviado, esperando ticket de SUNAT |
| `pendiente` | Error al enviar, el XML no llegó a SUNAT |
| `aceptado` | SUNAT aceptó el resumen. Las boletas quedan `anulado` |
| `rechazado` | SUNAT rechazó. Las boletas vuelven a `aceptado` |

---

## 8. Proceso: Anular Factura

**Mecanismo oficial SUNAT:** Comunicación de Baja **(RA-)**
**Archivo principal:** `app/Services/Documents/VoidedService.php`
**Tabla afectada:** `voided_documents`

También aplica para: Notas de Crédito (07) y Notas de Débito (08).

### Regla operativa importante

La factura, nota de crédito o nota de débito original **no se vuelve a anular desde
cero** cada vez que falla SUNAT. El sistema crea una **Comunicación de Baja RA** y
trabaja sobre esa comunicación.

El documento original pasa a `anulacion_en_proceso` mientras el RA está vivo. Si SUNAT
acepta, el original termina en `anulado`. Si SUNAT rechaza, el original vuelve a
`aceptado`.

En otras palabras:

- **Se reintenta el RA**, no el documento original.
- Si el error es transitorio, el sistema reintenta solo.
- Si el RA queda en `rechazado`, hay que reenviar el mismo RA desde el endpoint de
   envío.

### Restricciones

- Solo facturas/NC/ND con `sunat_status = 'aceptado'`
- Máximo 7 días calendario desde la fecha de emisión

### Flujo

```
1. POST /api/v1/anulaciones  con  detalles: [{tipo_documento, serie, correlativo, motivo}]
   ↓ VoidedController::store()
   ↓ VoidedService::crear(tenant, fecha_generacion, fecha_comunicacion, detalles)

2. Valida: factura existe, está aceptada, dentro de 7 días

3. Crea registro en voided_documents:
   identifier = "RA-YYYYMMDD-NNN"
   sunat_status = 'enviado'
   detalles = [{tipo_documento, serie, correlativo, motivo}, ...]

4. Factura cambia a: sunat_status = 'anulacion_en_proceso'

5. GreenterService::buildVoided() → XML RA firmado
6. SUNAT responde con TICKET

7. [ASÍNCRONO] summaries:poll-pending cada 5 min (maneja también RA)
   → Consulta el ticket
   → Misma lógica de polling que RC

8. SUNAT confirma:
   → VoidedController::updateOriginalDocuments()
   → factura.sunat_status = 'anulado'
   → voided_documents.sunat_status = 'aceptado'
```

### Qué pasa según el resultado real

| Resultado del RA | Estado de la comunicación | Estado del documento original | ¿Qué hacer? |
|------------------|---------------------------|-------------------------------|-------------|
| SUNAT acepta | `aceptado` | `anulado` | Nada más. |
| SUNAT rechaza por validación | `rechazado` | `aceptado` | Corregir y reenviar el mismo RA. |
| SUNAT sigue procesando | `enviado` | `anulacion_en_proceso` | Esperar el polling automático. |
| Error de red / timeout transitorio | `enviado` | `anulacion_en_proceso` | El sistema reintenta solo. |

### Qué se reintenta automáticamente

El job `CheckVoidedTicketStatus` consulta el ticket de SUNAT cuando la comunicación
está en `enviado` y todavía no llegó a estado final.

- Si SUNAT responde `0` o `187`, significa que sigue procesando.
- Si la llamada falla por red o SOAP, el job se vuelve a programar con backoff.
- Si SUNAT responde un error numérico definitivo, la comunicación pasa a `rechazado`.

Eso significa que **no debes volver a anular el documento original**. Debes reintentar
la comunicación RA.

### Reenvío manual

Si el resumen o la comunicación ya quedaron en `rechazado`, el flujo correcto es:

1. Corregir el motivo, datos o consistencia detectada.
2. Reenviar el mismo registro con:
   - `POST /api/v1/resumenes/{id}/enviar` para RC.
   - `POST /api/v1/anulaciones/{id}/enviar` para RA.
3. Esperar el polling automático de estado.

No se debe recrear el documento original si solo falló la anulación.

---

## 9. Cron Jobs

El archivo `cron-jobs.php` se ejecuta **cada minuto** desde el servidor.
En cada ejecución hace DOS cosas:
1. `schedule:run` — dispara las tareas programadas
2. `queue:work --stop-when-empty` — procesa los jobs de la cola hasta vaciarla

### Tareas programadas (schedule)

| Comando | Frecuencia | Qué hace |
|---------|-----------|---------|
| `sunat:reintentar-pendientes` | Cada 15 min | Reencola boletas/facturas en `pendiente`/`enviado` atascadas |
| `summaries:poll-pending` | Cada 5 min | Consulta tickets RC/RA en SUNAT (anulaciones y resúmenes) |
| `sire:poll-pending` | Cada 1 min | Consulta tickets SIRE pendientes |
| `sire:reconcile-all` | Diario 03:00 | Reconcilia SIRE para todos los tenants |
| `ProcessRecurringPayments` | Diario 06:00 | Cobra suscripciones vencidas |
| `CheckTrialExpiration` | Diario 07:00 | Verifica trials vencidos |
| `ResetMonthlyUsage` | Mensual día 1 00:05 | Reinicia contador de documentos del mes |
| `partitions:create` | Mensual día 1 02:00 | Crea particiones MySQL próximos 3 meses |
| `logs:purge` | Semanal domingo 03:00 | Borra api_logs con más de 90 días |

### Colas de jobs (prioridad)

```
sunat     ← envíos a SUNAT (prioridad 1)
webhooks  ← notificaciones al webhook del cliente
mail      ← correos
default   ← todo lo demás
```

---

## 10. Diagnóstico de estados

### Mapa rápido: ¿qué pasó con este documento?

```
sunat_status = 'pendiente' y sunat_code IS NULL
   → Recién creado, el worker aún no lo procesó
   → Acción: esperar unos minutos, el cron lo levanta

sunat_status = 'pendiente' y sunat_code = 'SUNAT_TIMEOUT'
   → Agotó 20 intentos (≈15 horas). SUNAT no respondió.
   → Acción: verificar SUNAT, luego POST /boletas/{id}/enviar

sunat_status = 'enviado' (más de 30 minutos)
   → Job en cola pero el worker no está procesando
   → Acción: verificar que queue:work esté corriendo

sunat_status = 'rechazado' y sunat_code empieza con '2'
   → Error de validación de SUNAT (datos incorrectos)
   → Acción: revisar sunat_description, corregir datos, reenviar

sunat_status = 'rechazado' y sunat_code = 'CERT_ERROR'
   → Certificado digital vencido o inválido
   → Acción: actualizar certificado en configuración del tenant

sunat_status = 'anulacion_en_proceso'
   → Anulación enviada, esperando respuesta de SUNAT (puede tardar horas)
   → Acción: esperar, el cron lo monitorea cada 5 min

sunat_status = 'aceptado'
   → Todo correcto. Para corregir: emitir Nota de Crédito
   → Para anular: POST /api/v1/anulaciones (plazo 7 días)

sunat_status = 'anulado'
   → Definitivo. No hay acción posible.
```

### ¿El cron está corriendo?

Para saber si el cron job está activo, verificar:

1. **Logs:** `storage/logs/cron-jobs.log` — tiene timestamp de cada ejecución
2. **Lock file:** `storage/framework/cron-jobs.lock` — si existe y tiene más de 70 segundos, el cron anterior quedó colgado
3. **Documentos atascados:** si hay facturas en `enviado` por más de 30 min, el worker no está corriendo
4. **Circuit breaker:** `GET /api/v1/sunat/estado` — devuelve si SUNAT está disponible

### ¿Se pierde la cola si reinicio el cron o el worker?

No, con esta configuración la cola no se pierde de forma automática.

- La conexión por defecto es `database`, así que los jobs quedan guardados en la
   tabla `jobs`.
- Si el worker se detiene y luego vuelve a arrancar, los jobs siguen ahí.
- Lo que sí puede pasar es que queden atrasados, duplicados por reintento o marcados
   como fallidos si exceden los intentos permitidos.

La idea práctica es esta:

- Si el proceso se cayó, los jobs pendientes **siguen persistidos**.
- Si el job ya agotó reintentos, el documento puede quedar en `pendiente` con
   `SUNAT_TIMEOUT` y requerir reenvío manual.
- Para decir “este sistema sigue trabajando”, lo más útil es revisar:
   - logs de cron,
   - la tabla `jobs`,
   - documentos que avanzan de `enviado` a `aceptado` o `rechazado`.

---

## 11. Consultas SQL

### Ver todos los documentos por estado (resumen)

```sql
SELECT
    'factura'    tipo,
    sunat_status,
    COUNT(*)     total,
    MAX(updated_at) ultimo_cambio
FROM invoices
WHERE deleted_at IS NULL
GROUP BY sunat_status

UNION ALL

SELECT
    'boleta'     tipo,
    sunat_status,
    COUNT(*)     total,
    MAX(updated_at) ultimo_cambio
FROM boletas
WHERE deleted_at IS NULL
GROUP BY sunat_status
ORDER BY tipo, sunat_status;
```

### Documentos atascados en enviado (worker caído)

```sql
SELECT 'invoice' tipo, id, tenant_id, serie, correlativo, sunat_status, sunat_code, updated_at
FROM invoices
WHERE sunat_status = 'enviado'
  AND updated_at < NOW() - INTERVAL 30 MINUTE
  AND deleted_at IS NULL
UNION ALL
SELECT 'boleta' tipo, id, tenant_id, serie, correlativo, sunat_status, sunat_code, updated_at
FROM boletas
WHERE sunat_status = 'enviado'
  AND updated_at < NOW() - INTERVAL 30 MINUTE
  AND deleted_at IS NULL
ORDER BY updated_at;
```

### Documentos que el cron puede rescatar (pendiente > 15 min)

```sql
SELECT 'invoice' tipo, id, serie, correlativo, sunat_code, created_at, updated_at
FROM invoices
WHERE sunat_status IN ('pendiente', 'enviado')
  AND updated_at <= NOW() - INTERVAL 15 MINUTE
  AND created_at >= NOW() - INTERVAL 7 DAY
  AND deleted_at IS NULL
UNION ALL
SELECT 'boleta' tipo, id, serie, correlativo, sunat_code, created_at, updated_at
FROM boletas
WHERE sunat_status IN ('pendiente', 'enviado')
  AND updated_at <= NOW() - INTERVAL 15 MINUTE
  AND created_at >= NOW() - INTERVAL 7 DAY
  AND deleted_at IS NULL
ORDER BY updated_at;
```

### Resúmenes diarios sin resolver (posible problema con cron de polling)

```sql
SELECT id, tenant_id, identifier, tipo, ticket, sunat_status, poll_attempts, last_polled_at, created_at
FROM summaries
WHERE sunat_status NOT IN ('aceptado', 'rechazado')
ORDER BY created_at;
```

### Documentos con SUNAT_TIMEOUT (necesitan reenvío manual)

```sql
SELECT 'invoice' tipo, id, serie, correlativo, sunat_code, sunat_description, updated_at
FROM invoices
WHERE sunat_status = 'pendiente' AND sunat_code = 'SUNAT_TIMEOUT'
UNION ALL
SELECT 'boleta' tipo, id, serie, correlativo, sunat_code, sunat_description, updated_at
FROM boletas
WHERE sunat_status = 'pendiente' AND sunat_code = 'SUNAT_TIMEOUT'
ORDER BY updated_at;
```

### Seguimiento de anulaciones en curso

```sql
SELECT 'summary' tipo, id, identifier, sunat_status, ticket, sunat_code, updated_at
FROM summaries
WHERE sunat_status IN ('pendiente', 'enviado', 'rechazado')
UNION ALL
SELECT 'voided' tipo, id, identifier, sunat_status, ticket, sunat_code, updated_at
FROM voided_documents
WHERE sunat_status IN ('pendiente', 'enviado', 'rechazado')
ORDER BY updated_at DESC;
```

### Documentos originales en anulación

```sql
SELECT 'boleta' tipo, id, serie, correlativo, sunat_status, updated_at
FROM boletas
WHERE sunat_status = 'anulacion_en_proceso'
UNION ALL
SELECT 'invoice' tipo, id, serie, correlativo, sunat_status, updated_at
FROM invoices
WHERE sunat_status = 'anulacion_en_proceso'
ORDER BY updated_at DESC;
```

---

## Flujo visual completo (resumen)

```
Usuario / API
    │
    ▼
POST /api/v1/boletas
    │
    ▼
CreateBoletaAction::execute()  ←── DB::transaction
    ├── Serie::lockForUpdate() → correlativo++
    ├── Boleta::create([sunat_status: 'pendiente'])
    ├── BoletaItem::create() × N
    │
    ├── enviar_automatico = true?
    │       YES → SendDocumentToSunat::dispatch()  ← COLA (no bloquea)
    │             Boleta::update([sunat_status: 'enviado'])
    │       NO  → queda en 'pendiente'
    │
    └── Retorna boleta al cliente
            │
            ▼
    API responde 201 (inmediato, NO espera SUNAT)

[En paralelo, en background...]

Worker de colas procesa SendDocumentToSunat
    │
    ├── Circuit Breaker abierto? → release(305s), no cuenta intento
    ├── buildInvoice() → objeto Greenter
    ├── sign XML → firma digital
    ├── SOAP → SUNAT
    │
    ├── SUNAT OK (0 / 3xxx)
    │       → sunat_status = 'aceptado'
    │       → guarda XML + CDR + PDF
    │       → dispara webhook
    │
    ├── SUNAT error permanente (2xxx / CERT_ERROR)
    │       → sunat_status = 'rechazado'
    │       → dispara webhook
    │
    ├── SUNAT error temporal (100 / 109 / SoapFault...)
    │       → release(backoff) → reintenta hasta 20 veces
    │
    └── 20 intentos agotados
            → sunat_status = 'pendiente' (code: SUNAT_TIMEOUT)
            → dispara webhook document.timeout_sunat

[Cada 15 minutos, el cron hace...]

sunat:reintentar-pendientes
    → busca 'pendiente'/'enviado' con updated_at > 15 min
    → SendDocumentToSunat::dispatch() para cada uno
    → NO toca rechazados ni aceptados
```

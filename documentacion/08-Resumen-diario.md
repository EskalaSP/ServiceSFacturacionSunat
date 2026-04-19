# 📑 Resumen Diario de Boletas

> Base URL: `https://tu-api.com/api/v1`
> El **Resumen Diario** es el mecanismo SUNAT para enviar **boletas en lote** (todas las del día) o **anularlas** después de aceptadas.

---

## 🎯 ¿Qué es un Resumen Diario?

SUNAT permite emitir boletas de **dos formas**:
1. **Una por una** (envío individual) — `POST /boletas` envía cada boleta directo a SUNAT
2. **En lote diario** (resumen) — emites las boletas localmente y las envías agrupadas con un solo resumen

El **Resumen Diario** tiene **2 modos**:

| Modo | Cuándo usar | Endpoint |
|------|-------------|----------|
| **Envío** | Boletas del día en estado `pendiente` que aún no van a SUNAT | `POST /resumenes` con solo `fecha_resumen` |
| **Anulación** | Boletas ya **aceptadas** por SUNAT que necesitas anular | `POST /resumenes` con `fecha_resumen` + `anular[]` |

> 💡 **Importante:** las **facturas** se anulan con `/anulaciones` (Comunicación de Baja). Las **boletas** se anulan con `/resumenes` modo anulación.

### Plazos SUNAT

- **Envío:** desde el día de emisión hasta **7 días calendario** después
- **Anulación:** misma ventana — solo dentro de los **7 días** de emitida la boleta

---

## 📑 Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/resumenes` | Crear resumen (envío o anulación) |
| `GET` | `/resumenes` | Listar resúmenes del tenant |
| `GET` | `/resumenes/{id}/estado` | Ver estado actual + boletas incluidas |
| `GET` | `/resumenes/{id}/xml` | Descargar XML firmado del resumen |
| `GET` | `/resumenes/{id}/cdr` | Descargar CDR de SUNAT |

---

## 1. `POST /resumenes` — Crear resumen

### A) Modo Envío (boletas del día)

**Body mínimo:**
```json
{
  "fecha_resumen": "2026-04-18"
}
```

**Comportamiento:**
1. Busca todas las boletas del tenant con `fecha_emision = 2026-04-18` y `sunat_status = 'pendiente'`
2. Las agrupa en un Resumen RC-yyyymmdd-NNN
3. Encola el envío a SUNAT
4. Cuando SUNAT acepta el resumen → todas las boletas pasan a `aceptado`

### Ejemplo

```bash
curl -X POST https://tu-api.com/api/v1/resumenes \
  -H "X-Api-Key: {api_key}" \
  -H "X-Api-Secret: {api_secret}" \
  -H "Content-Type: application/json" \
  -d '{
    "fecha_resumen": "2026-04-18"
  }'
```

### Respuesta (201)

```json
{
  "estado": "exito",
  "mensaje": "Resumen diario encolado para envío a SUNAT.",
  "datos": {
    "id_resumen": 5,
    "identifier": "RC-20260418-001",
    "fecha_envio": "2026-04-18",
    "fecha_documentos": "2026-04-18",
    "correlativo": "001",
    "accion": "envio",
    "total_documentos": 25,
    "estado_sunat": "enviado",
    "documentos": [
      { "id": 100, "numero": "B001-100", "total": 50.00 },
      { "id": 101, "numero": "B001-101", "total": 120.50 }
    ],
    "consulta_estado": "https://tu-api.com/api/v1/resumenes/5/estado"
  }
}
```

### Errores comunes (envío)

| Código | Mensaje | Causa |
|--------|---------|-------|
| 422 | "No hay boletas pendientes para la fecha 2026-04-18" | Ya enviaste todas o no hay boletas en esa fecha |
| 422 | "SUNAT solo permite resumen diario hasta 7 días después..." | Fuera del plazo de 7 días |

---

### B) Modo Anulación (boletas aceptadas)

**Body con array `anular`:**
```json
{
  "fecha_resumen": "2026-04-18",
  "anular": [
    { "id": 100, "motivo": "Anulación por error en datos del cliente", "tipo_documento": "03" },
    { "id": 101, "motivo": "Cliente devolvió el producto", "tipo_documento": "03" }
  ]
}
```

| Campo | Tipo | Obligatorio | Notas |
|-------|------|-------------|-------|
| `fecha_resumen` | date | ✅ | Fecha de la comunicación |
| `anular[].id` | integer | ✅ | ID de la boleta en tu API |
| `anular[].motivo` | string(max 255) | ✅ | Razón clara |
| `anular[].tipo_documento` | string | ❌ | `03` (Boleta) — default |

### Ejemplo

```bash
curl -X POST https://tu-api.com/api/v1/resumenes \
  -H "X-Api-Key: {api_key}" \
  -H "X-Api-Secret: {api_secret}" \
  -H "Content-Type: application/json" \
  -d '{
    "fecha_resumen": "2026-04-18",
    "anular": [
      { "id": 100, "motivo": "Error en datos del cliente" },
      { "id": 102, "motivo": "Producto devuelto" }
    ]
  }'
```

### Respuesta (201)

```json
{
  "estado": "exito",
  "mensaje": "Resumen de anulación encolado para envío a SUNAT.",
  "datos": {
    "id_resumen": 6,
    "identifier": "RC-20260418-002",
    "accion": "anulacion",
    "total_documentos": 2,
    "estado_sunat": "enviado",
    "documentos": [
      { "id": 100, "numero": "B001-100", "total": 50.00 },
      { "id": 102, "numero": "B001-102", "total": 75.00 }
    ]
  }
}
```

> Las boletas pasan a estado `anulacion_en_proceso` mientras SUNAT procesa. Cuando se acepta, pasan a `anulado`.

### Errores comunes (anulación)

| Mensaje | Causa |
|---------|-------|
| "No se encontraron boletas aceptadas por SUNAT para anular" | Las boletas no están en estado `aceptado` |
| "Boleta B001-100 ya pasó el plazo de 7 días para anulación" | Pasó la ventana SUNAT |
| "La boleta B001-100 tiene una nota de crédito asociada. Usa la NC en vez de anularla" | Ya hay NC, no puedes duplicar la operación |
| "Ya existe un resumen de anulación para la boleta B001-100" | Ya está siendo anulada en otro resumen |

---

## 2. `GET /resumenes` — Listar resúmenes

### Query params

| Param | Descripción |
|-------|-------------|
| `mes` | Filtrar por mes en formato `yyyy-mm` (ej: `2026-04`) |
| `tipo` | `envio` \| `anulacion` |
| `por_pagina` | Default 15 |

```bash
curl "https://tu-api.com/api/v1/resumenes?mes=2026-04&tipo=envio" \
  -H "X-Api-Key: {api_key}" \
  -H "X-Api-Secret: {api_secret}"
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": [
    {
      "id": 5,
      "identifier": "RC-20260418-001",
      "tipo": "envio",
      "fecha_referencia": "2026-04-18",
      "fecha_envio": "2026-04-18",
      "total_documentos": 25,
      "ticket": "1717182000",
      "estado_sunat": "aceptado",
      "codigo_sunat": "0",
      "descripcion_sunat": "El Resumen ha sido aceptado",
      "creado_en": "2026-04-18T14:30:00-05:00"
    }
  ],
  "paginacion": {
    "total": 12,
    "pagina_actual": 1,
    "ultima_pagina": 1
  }
}
```

---

## 3. `GET /resumenes/{id}/estado` — Ver estado completo

```bash
curl "https://tu-api.com/api/v1/resumenes/5/estado" \
  -H "X-Api-Key: {api_key}" \
  -H "X-Api-Secret: {api_secret}"
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": {
    "id_resumen": 5,
    "identifier": "RC-20260418-001",
    "ticket": "1717182000",
    "estado_sunat": "aceptado",
    "codigo_sunat": "0",
    "descripcion_sunat": "El Resumen ha sido aceptado",
    "notas_sunat": [],
    "boletas": [
      {
        "id": 100,
        "numero": "B001-100",
        "total": 50.00,
        "estado": "aceptado",
        "codigo_sunat": "0",
        "descripcion_sunat": "Aceptada"
      }
    ]
  }
}
```

### Estados posibles del resumen

| Estado | Significado |
|--------|-------------|
| `pendiente` | Encolado, no enviado |
| `enviado` | Enviado a SUNAT, esperando ticket |
| `procesando` | SUNAT está procesando |
| `aceptado` | ✅ Resumen aceptado — boletas pasaron a aceptado/anulado según el modo |
| `rechazado` | ❌ SUNAT rechazó — boletas vuelven a pendiente |

---

## 4. `GET /resumenes/{id}/xml` y `/cdr`

Descarga directa (binarios) — formato igual a facturas/boletas.

```bash
# XML firmado
curl -o resumen.xml "https://tu-api.com/api/v1/resumenes/5/xml" \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"

# CDR de SUNAT
curl -o cdr.zip "https://tu-api.com/api/v1/resumenes/5/cdr" \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"
```

---

## 🎯 Flujos típicos

### Flujo A — Negocio con muchas boletas al día

```
[Mañana - tarde] POST /boletas (varias veces durante el día)
                 → cada boleta queda en estado "pendiente"

[Final del día]  POST /resumenes
                 { "fecha_resumen": "2026-04-18" }
                 → agrupa todas las pendientes y las envía a SUNAT

[Después]        GET /resumenes/5/estado
                 → cuando "aceptado", todas las boletas pasan a "aceptado"
```

**Ventaja:** menos llamadas a SUNAT, más rápido para alto volumen.

### Flujo B — Anular una boleta aceptada

```
1. Boleta B001-100 ya está ACEPTADA en SUNAT
2. Cliente devuelve el producto
3. POST /resumenes
   { "fecha_resumen": "2026-04-18", "anular": [{"id": 100, "motivo": "..."}] }
4. La boleta pasa a "anulacion_en_proceso"
5. GET /resumenes/{id}/estado → cuando "aceptado", la boleta pasa a "anulado"
```

### Flujo C — Anular varias boletas a la vez

```
POST /resumenes
{
  "fecha_resumen": "2026-04-18",
  "anular": [
    { "id": 100, "motivo": "Error en cliente" },
    { "id": 102, "motivo": "Devolución" },
    { "id": 105, "motivo": "Pago no confirmado" }
  ]
}
→ Un solo resumen anula las 3 boletas
```

---

## ⚖️ Cuándo usar Resumen vs alternativas

| Caso | Solución correcta |
|------|-------------------|
| Quiero anular **boleta aceptada** | `POST /resumenes` modo anulación |
| Quiero anular **factura aceptada** | `POST /anulaciones` (Comunicación de Baja) |
| Quiero **revertir el valor** de una venta (con efectos contables) | Emitir **Nota de Crédito** (`POST /notas-credito`) |
| Quiero enviar muchas boletas eficientemente | `POST /resumenes` modo envío (todas las del día) |
| Quiero enviar **una boleta específica** ya | Solo `POST /boletas` (envío individual automático) |
| La boleta tiene **>7 días** | NO se puede anular con resumen — usa **Nota de Crédito** |

---

## 📋 Catálogos referenciados

- **Cat. 01** — Tipo documento: `03` (Boleta de venta)
- **Estados SUNAT:** `pendiente`, `enviado`, `aceptado`, `rechazado`, `anulacion_en_proceso`, `anulado`

---

## ⚙️ Reglas importantes

- ❌ **No se puede** crear un resumen para una boleta con NC asociada (usa la NC)
- ❌ **No se puede** crear dos resúmenes de anulación para la misma boleta
- ✅ El resumen se envía **asíncronamente** — usar `/estado` para verificar
- ✅ El correlativo `001`, `002`, etc se genera automáticamente por día
- ✅ Identifier formato: `RC-yyyymmdd-NNN` (ej: `RC-20260418-001`)
- ✅ Plazo **estricto**: 7 días desde la emisión de las boletas

---

## 🔗 Relacionados

- [`05-Boletas.md`](./05-Boletas.md) — emitir boletas individuales
- [`09-Anular.md`](./09-Anular.md) — comunicación de baja para facturas y NC/ND
- [`06-Notas-credito.md`](./06-Notas-credito.md) — alternativa para revertir valor (sin plazo de 7 días)

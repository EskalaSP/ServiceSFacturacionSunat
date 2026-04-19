# 🗑️ Anulaciones y Resúmenes Diarios

> Base URL: `https://tu-api.com/api/v1`
> Incluye: Comunicaciones de Baja (Facturas/NC/ND/Retención/Percepción) y Resúmenes Diarios (Boletas).

## 📑 Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| **Anulaciones** | | |
| `POST` | `/anulaciones` | Comunicación de baja |
| `GET` | `/anulaciones` | Listar |
| `GET` | `/anulaciones/{id}` | Ver |
| `GET` | `/anulaciones/{id}/estado` | Consultar estado en SUNAT |
| **Resúmenes diarios** | | |
| `POST` | `/resumenes` | Crear resumen diario (boletas) |
| `GET` | `/resumenes` | Listar |
| `GET` | `/resumenes/{id}/estado` | Consultar estado |
| `GET` | `/resumenes/{id}/xml` | XML |
| `GET` | `/resumenes/{id}/cdr` | CDR |

---

## 🎯 ¿Cuándo usar cada uno?

| Caso | Usar |
|------|------|
| Anular una **factura** aceptada | Comunicación de Baja (`/anulaciones`) |
| Anular una **nota de crédito/débito** aceptada | Comunicación de Baja (`/anulaciones`) |
| Anular una **boleta** aceptada | Resumen Diario modo anulación (`/resumenes` con `anular[]`) |
| Anular un comprobante **retención/percepción** | Comunicación de Baja (`/anulaciones`) |
| Emitir **varias boletas del día** en un solo envío | Resumen Diario regular |

---

## 1. Anulaciones (Comunicaciones de Baja)

### `POST /anulaciones`

Anula en SUNAT comprobantes ya emitidos (estado `aceptado`).

### Body

```json
{
  "fecha_generacion": "2026-04-15",
  "fecha_comunicacion": "2026-04-18",
  "detalles": [
    {
      "tipo_documento": "01",
      "serie": "F001",
      "correlativo": "00000123",
      "motivo": "Error en datos del cliente"
    },
    {
      "tipo_documento": "07",
      "serie": "FC01",
      "correlativo": "00000010",
      "motivo": "Error en el motivo de la NC"
    }
  ]
}
```

### Campos

| Campo | Tipo | Obligatorio | Notas |
|-------|------|-------------|-------|
| `fecha_generacion` | date | ✅ | Fecha emisión del doc a anular |
| `fecha_comunicacion` | date | ❌ | Fecha de la comunicación (default hoy) |
| `detalles` | array | ✅ min 1 | Documentos a anular |
| `detalles[].tipo_documento` | string | ✅ | `01` Factura, `07` NC, `08` ND, `20` Retención, `40` Percepción |
| `detalles[].serie` | string(4) | ✅ | |
| `detalles[].correlativo` | string | ✅ | |
| `detalles[].motivo` | string(255) | ✅ | Explicación libre |

> ❌ **NO incluye `03` (Boleta)** — boletas se anulan con Resumen Diario (sección abajo).

### Plazo SUNAT

Puedes anular:
- **Facturas/NC/ND:** dentro de los 7 días calendario de emitidas
- **Retenciones/Percepciones:** dentro del mismo mes de emisión

### Ejemplo — Anular 1 factura

```bash
curl -X POST https://tu-api.com/api/v1/anulaciones \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}" \
  -H "Content-Type: application/json" \
  -d '{
    "fecha_generacion": "2026-04-18",
    "detalles": [
      {
        "tipo_documento": "01",
        "serie": "F001",
        "correlativo": "123",
        "motivo": "Error en razón social del cliente"
      }
    ]
  }'
```

### Respuesta (201)

```json
{
  "estado": "exito",
  "mensaje": "Comunicación de baja creada y encolada.",
  "datos": {
    "id": 7,
    "identificador": "RA-20260418-001",
    "fecha_generacion": "2026-04-18",
    "fecha_comunicacion": "2026-04-18",
    "cantidad_docs": 1,
    "sunat_status": "pendiente",
    "sunat_ticket": null,
    "detalles": [
      {
        "tipo_documento": "01",
        "serie": "F001",
        "correlativo": "123",
        "motivo": "Error en razón social del cliente"
      }
    ]
  }
}
```

### 2. `GET /anulaciones` — Listar

Filtros query:
- `fecha_generacion_desde`, `fecha_generacion_hasta`
- `estado` — `pendiente`, `enviado`, `aceptado`, `rechazado`
- `por_pagina`

```bash
curl "https://tu-api.com/api/v1/anulaciones?estado=aceptado" \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

### 3. `GET /anulaciones/{id}` — Ver

### 4. `GET /anulaciones/{id}/estado` — Consultar estado en SUNAT

SUNAT procesa anulaciones asíncronamente. Este endpoint consulta el ticket.

```bash
curl https://tu-api.com/api/v1/anulaciones/7/estado \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

**Respuesta:**
```json
{
  "estado": "exito",
  "datos": {
    "id": 7,
    "sunat_ticket": "1604394234123",
    "sunat_status": "aceptado",
    "sunat_code": "0",
    "sunat_description": "La Comunicación de baja ha sido aceptada",
    "documentos_afectados": [
      {
        "tipo": "01",
        "serie": "F001",
        "correlativo": "123",
        "estado_actual": "anulado"
      }
    ]
  }
}
```

> Cuando `sunat_status=aceptado`, los documentos referenciados pasan automáticamente a estado `anulado`.

---

## 5. Resúmenes Diarios (Boletas)

### Dos modos de uso:

**Modo A — Envío masivo regular** (envía todas las boletas del día en lote)
**Modo B — Anulación** (anula boletas previamente aceptadas)

### `POST /resumenes` — Modo regular

```json
{
  "fecha_resumen": "2026-04-18"
}
```

Agrupa automáticamente todas las boletas del día indicado que aún no fueron enviadas y las envía como resumen.

```bash
curl -X POST https://tu-api.com/api/v1/resumenes \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}" \
  -H "Content-Type: application/json" \
  -d '{ "fecha_resumen": "2026-04-18" }'
```

**Respuesta:**
```json
{
  "estado": "exito",
  "datos": {
    "id": 5,
    "identificador": "RC-20260418-001",
    "fecha_resumen": "2026-04-18",
    "cantidad_docs": 25,
    "sunat_status": "pendiente",
    "sunat_ticket": null
  }
}
```

### `POST /resumenes` — Modo anulación

Anula boletas (y opcionalmente NC/ND de boletas) ya aceptadas.

```json
{
  "fecha_resumen": "2026-04-18",
  "anular": [
    {
      "id": 345,
      "motivo": "Anulación por error en cliente",
      "tipo_documento": "03"
    },
    {
      "id": 100,
      "motivo": "Error en la descripción",
      "tipo_documento": "07"
    }
  ]
}
```

| Campo | Descripción |
|-------|-------------|
| `anular[].id` | ID de la boleta/NC/ND en tu API |
| `anular[].motivo` | Explicación |
| `anular[].tipo_documento` | `03` (Boleta), `07` (NC), `08` (ND) |

### 6. `GET /resumenes` — Listar

Filtros: `fecha_desde`, `fecha_hasta`, `estado`.

### 7. `GET /resumenes/{id}/estado` — Consultar estado

SUNAT procesa resúmenes asíncronamente. Devuelve estado actual.

```bash
curl https://tu-api.com/api/v1/resumenes/5/estado \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

**Respuesta:**
```json
{
  "estado": "exito",
  "datos": {
    "id": 5,
    "identificador": "RC-20260418-001",
    "fecha_resumen": "2026-04-18",
    "sunat_ticket": "1604394234456",
    "sunat_status": "aceptado",
    "sunat_code": "0",
    "sunat_description": "Resumen aceptado",
    "boletas_procesadas": 25,
    "boletas_con_error": 0
  }
}
```

### 8. `GET /resumenes/{id}/xml` · `/cdr`

Idéntico a facturas.

---

## 🎯 Flujos típicos

### Flujo 1 — Anular una factura aceptada

```
1. Tu factura F001-123 fue aceptada ayer
2. Detectas un error crítico (RUC incorrecto)
3. POST /anulaciones
   { "fecha_generacion": "2026-04-17", "detalles": [
       { "tipo_documento":"01", "serie":"F001", "correlativo":"123", "motivo":"..." }
     ]
   }
4. GET /anulaciones/{id}/estado → esperar que sea "aceptado"
5. Ahora F001-123.sunat_status = "anulado"
6. Emitir factura nueva con datos correctos
```

> **Alternativa:** emitir **Nota de Crédito** (no necesita plazo de 7 días, tiene alcance más amplio).

### Flujo 2 — Anular boletas del día

```
1. Emitiste 50 boletas hoy, todas aceptadas
2. Detectas que 3 están mal
3. POST /resumenes con "anular": [ {id:10, motivo:"..."}, {id:15, ...}, {id:20, ...} ]
4. GET /resumenes/{id}/estado → esperar aceptación
5. Las 3 boletas pasan a estado "anulado"
```

### Flujo 3 — Envío diario por resumen (boletas)

```
Durante el día: POST /boletas (varias veces, estado "pendiente")
Al final del día o al día siguiente:
  POST /resumenes { "fecha_resumen": "2026-04-18" }
  → agrupa todas las boletas pendientes del día
  → las envía a SUNAT como resumen
GET /resumenes/{id}/estado → cuando aceptado, todas las boletas pasan a "aceptado"
```

---

## ⚙️ Reglas importantes

- **No puedes anular** un documento `rechazado` — corregir con PUT
- **No puedes anular** un documento `anulado` dos veces
- La **comunicación de baja** genera un CDR propio de SUNAT
- El **resumen diario** es el método recomendado para alto volumen de boletas
- Una misma comunicación de baja puede incluir varios documentos de distintos tipos (01/07/08/20/40), pero **no boletas**
- Las **anulaciones son asíncronas** — requieren consultar el estado después

---

## 📋 Estados

| `sunat_status` | Significado |
|----------------|-------------|
| `pendiente` | Encolado, no enviado |
| `enviado` | Enviado a SUNAT, esperando ticket |
| `procesando` | SUNAT está procesando |
| `aceptado` | ✅ Anulación/Resumen aceptado |
| `rechazado` | ❌ SUNAT rechazó |

---

## 🔗 Relacionados

- Ver [`04-Facturas.md`](./04-Facturas.md) — estado `sunat_status` de documentos
- Ver [`05-Boletas.md`](./05-Boletas.md) — flujo de envío por resumen
- Ver [`06-Notas-credito.md`](./06-Notas-credito.md) — alternativa a la anulación

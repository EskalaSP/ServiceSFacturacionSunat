# Envío manual a SUNAT

Por defecto, todos los comprobantes se envían a SUNAT **automáticamente** apenas se crean (vía jobs en cola). Sin embargo, algunos clientes prefieren **controlar manualmente** cuándo se hace el envío (por ejemplo: revisión visual previa, agrupar lotes, esperar confirmación del cliente, etc.).

La API soporta ambos modos en **todos los comprobantes SUNAT**:

- Facturas (01)
- Boletas (03)
- Notas de Crédito (07)
- Notas de Débito (08)
- Guías de Remisión Remitente / Transportista (09 / 31)
- Resúmenes Diarios (RC)
- Comunicaciones de Baja (RA)
- Reversiones (RR)
- Retenciones (20)
- Percepciones (40)

---

## 1. Modo automático (por defecto)

Si no envías el campo `enviar_automatico`, o lo envías como `true`, el comportamiento es el de siempre:

```http
POST /api/v1/facturas
Content-Type: application/json

{
  "serie": "F001",
  "fecha_emision": "2026-04-19",
  "cliente": { ... },
  "items": [ ... ]
}
```

**Respuesta:**

```json
{
  "estado": "exito",
  "mensaje": "Factura creada y encolada para envío a SUNAT.",
  "datos": {
    "id": 123,
    "numero": "F001-1234",
    "sunat_status": "enviado",
    ...
  }
}
```

El job `SendDocumentToSunat` se encola y procesa el envío en segundo plano. Cuando termine, el `sunat_status` cambiará a `aceptado` (o `rechazado`).

---

## 2. Modo manual

Pasa `enviar_automatico: false` en el body de la creación. El comprobante se guarda en estado **`pendiente`** y **NO** se encola el envío.

```http
POST /api/v1/facturas
Content-Type: application/json

{
  "serie": "F001",
  "fecha_emision": "2026-04-19",
  "cliente": { ... },
  "items": [ ... ],
  "enviar_automatico": false
}
```

**Respuesta:**

```json
{
  "estado": "exito",
  "mensaje": "Factura creada en estado pendiente. Use POST /facturas/{id}/enviar para enviarla a SUNAT.",
  "datos": {
    "id": 124,
    "numero": "F001-1235",
    "sunat_status": "pendiente",
    ...
  }
}
```

Luego, cuando estés listo para enviar a SUNAT:

```http
POST /api/v1/facturas/124/enviar
```

**Respuesta:**

```json
{
  "estado": "exito",
  "mensaje": "Factura enviada a SUNAT.",
  "datos": {
    "id": 124,
    "sunat_status": "enviado",
    ...
  }
}
```

Internamente el endpoint `/enviar` encola el job `SendDocumentToSunat`. El comprobante pasará por el mismo flujo de retries y validaciones que el modo automático.

---

## 3. Endpoints `/enviar` por tipo de comprobante

| Comprobante | Endpoint manual |
|---|---|
| Factura | `POST /api/v1/facturas/{id}/enviar` |
| Boleta | `POST /api/v1/boletas/{id}/enviar` |
| Nota de Crédito | `POST /api/v1/notas-credito/{id}/enviar` |
| Nota de Débito | `POST /api/v1/notas-debito/{id}/enviar` |
| Guía Remisión (GRR/GRT) | `POST /api/v1/guias-remision/{id}/enviar` |
| Resumen Diario | `POST /api/v1/resumenes/{id}/enviar` |
| Comunicación de Baja | `POST /api/v1/anulaciones/{id}/enviar` |
| Reversión (RR) | `POST /api/v1/anulaciones/{id}/enviar` |
| Retención | `POST /api/v1/retenciones/{id}/enviar` |
| Percepción | `POST /api/v1/percepciones/{id}/enviar` |

> **Nota:** los endpoints `/reenviar` ya existentes (facturas, boletas, NC, ND) siguen funcionando como **alias** de `/enviar` para retro-compatibilidad. Internamente hacen lo mismo: marcan el doc como `pendiente` y encolan el envío.

---

## 4. Reglas y validaciones

- `/enviar` acepta documentos en estado `pendiente` o `rechazado`. Si el documento ya fue **`aceptado`** por SUNAT, devuelve **422**.
- `/enviar` resetea `sunat_code` y `sunat_description` antes de re-encolar (limpia errores anteriores).
- El job sigue las mismas reglas de **retries** (10 intentos con backoff exponencial 15s → 600s) que el envío automático.
- El **límite del plan** (`check.limit:sunat`) se cuenta en la **creación**, no en el envío. Crear un documento manual NO consume un envío adicional cuando finalmente lo envías.

---

## 5. Casos de uso típicos

**Caso 1 — Revisión visual antes de enviar:**

```http
POST /api/v1/boletas
{ ..., "enviar_automatico": false }     # Crear boleta
GET  /api/v1/boletas/45/pdf              # Verificar PDF
POST /api/v1/boletas/45/enviar           # Confirmar envío
```

**Caso 2 — Reintentar un rechazo (siempre fue así):**

```http
GET  /api/v1/facturas/12                 # status='rechazado', code='2017'
PUT  /api/v1/facturas/12                 # corregir datos (tipo doc, etc.)
POST /api/v1/facturas/12/enviar          # reenviar a SUNAT
```

**Caso 3 — Lote de boletas para resumen diario (no envío individual):**

Las boletas tienen un caso especial — usar `solo_registro: true` (existente, NO usar `enviar_automatico`):

```http
POST /api/v1/boletas
{ ..., "solo_registro": true }           # Solo registrar, NO enviar
# ... varias boletas más durante el día ...
POST /api/v1/resumenes
{ "fecha_resumen": "2026-04-19" }        # Enviar todas vía RC
```

> `solo_registro` y `enviar_automatico` son **flags distintos**:
> - `solo_registro`: la boleta queda pendiente para ir en un resumen RC (no se envía individualmente nunca).
> - `enviar_automatico: false`: el documento queda pendiente para envío individual manual.

---

## 6. Compatibilidad

- **Por defecto, todo sigue funcionando como antes**: si tu cliente no envía `enviar_automatico`, el flujo es el de siempre (envío automático vía jobs).
- Los endpoints `/reenviar` **NO se eliminan**, son alias de `/enviar`.
- Los webhooks (`document.sent`, `document.rejected`) siguen disparándose normalmente cuando el job procesa el envío.

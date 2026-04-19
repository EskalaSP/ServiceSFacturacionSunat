# 📕 Notas de Crédito (Tipo 07)

> Base URL: `https://tu-api.com/api/v1`
> Documento que modifica **a la baja** un comprobante ya emitido (factura/boleta).

## 📑 Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/notas-credito` | Crear nota de crédito |
| `GET` | `/notas-credito` | Listar |
| `GET` | `/notas-credito/{id}` | Ver |
| `PUT` | `/notas-credito/{id}` | Actualizar (si NO aceptada) |
| `GET` | `/notas-credito/{id}/xml` | XML |
| `GET` | `/notas-credito/{id}/cdr` | CDR |
| `GET` | `/notas-credito/{id}/pdf` | PDF |
| `POST` | `/notas-credito/{id}/reenviar` | Reenviar |

---

## 🎯 Cuándo emitir Nota de Crédito

**Casos más comunes:**
- Anular una factura/boleta ya aceptada
- Devolución total o parcial de mercadería
- Descuento otorgado posteriormente
- Corrección de error en el RUC del cliente
- Bonificación posterior

---

## 1. `POST /notas-credito` — Crear

### Body completo

```json
{
  "serie": "FC01",
  "fecha_emision": "2026-04-18",
  "tipo_moneda": "PEN",

  "cliente": {
    "tipo_doc": "6",
    "num_doc": "20555666777",
    "razon_social": "ACME CORP SAC",
    "direccion": "JR. ACME 456"
  },

  "doc_afectado_tipo": "01",
  "doc_afectado_serie": "F001",
  "doc_afectado_correlativo": "00000123",
  "cod_motivo": "06",
  "des_motivo": "Devolución total por defecto de fábrica",

  "items": [
    {
      "codigo": "P001",
      "descripcion": "LAPTOP HP PAVILION 15",
      "unidad": "NIU",
      "cantidad": 2,
      "precio_unitario": 2950.00,
      "tip_afe_igv": "10"
    }
  ]
}
```

### Campos obligatorios del documento afectado

| Campo | Descripción |
|-------|-------------|
| `doc_afectado_tipo` | `01` (Factura), `03` (Boleta), `12` (Ticket) |
| `doc_afectado_serie` | Serie del doc. original |
| `doc_afectado_correlativo` | Correlativo original (con ceros) |
| `cod_motivo` | Cat. 09 (ver abajo) |
| `des_motivo` | Descripción libre, max 250 |

### Serie

- Para notas de crédito de **facturas**: usar prefijo `FC` o `F` (`FC01`, `FC02`, etc.)
- Para notas de crédito de **boletas**: usar prefijo `BC` o `B` (`BC01`, `BC02`, etc.)

### Catálogo 09 — Tipos de Nota de Crédito

| Código | Descripción | Uso típico |
|--------|-------------|------------|
| `01` | Anulación de la operación | Anular factura/boleta completa |
| `02` | Anulación por error en el RUC | Corrección de RUC |
| `03` | Corrección por error en la descripción | Cambio de descripción del ítem |
| `04` | Descuento global | Descuento total posterior |
| `05` | Descuento por ítem | Descuento específico |
| `06` | Devolución total/parcial | Devolución de mercadería |
| `07` | Bonificación | Bonificación posterior |
| `08` | Disminución en el valor | Ajuste a la baja |
| `09` | Otros | Caso no cubierto |

### Items

**Mismos campos que factura** (ver `04-Facturas.md`). Los items representan lo que se está **revertiendo** (devolviendo, anulando, descontando). Los montos siempre se expresan en positivo.

### Ejemplo — Anulación total (cod_motivo=01)

```json
{
  "serie": "FC01",
  "fecha_emision": "2026-04-18",
  "cliente": { "tipo_doc":"6", "num_doc":"20555666777", "razon_social":"ACME SAC" },
  "doc_afectado_tipo": "01",
  "doc_afectado_serie": "F001",
  "doc_afectado_correlativo": "123",
  "cod_motivo": "01",
  "des_motivo": "Anulación por error en la emisión",
  "items": [
    {
      "codigo": "P001",
      "descripcion": "LAPTOP HP PAVILION 15",
      "unidad": "NIU",
      "cantidad": 2,
      "precio_unitario": 2950.00,
      "tip_afe_igv": "10"
    }
  ]
}
```

### Ejemplo — Devolución parcial (cod_motivo=06)

```json
{
  "serie": "FC01",
  "fecha_emision": "2026-04-18",
  "cliente": { "tipo_doc":"6", "num_doc":"20555666777", "razon_social":"ACME SAC" },
  "doc_afectado_tipo": "01",
  "doc_afectado_serie": "F001",
  "doc_afectado_correlativo": "123",
  "cod_motivo": "06",
  "des_motivo": "Devolución de 1 de 2 laptops",
  "items": [
    {
      "codigo": "P001",
      "descripcion": "LAPTOP HP PAVILION 15",
      "unidad": "NIU",
      "cantidad": 1,
      "precio_unitario": 2950.00,
      "tip_afe_igv": "10"
    }
  ]
}
```

### Ejemplo — Descuento global (cod_motivo=04)

```json
{
  "serie": "FC01",
  "fecha_emision": "2026-04-18",
  "cliente": { "tipo_doc":"6", "num_doc":"20555666777", "razon_social":"ACME SAC" },
  "doc_afectado_tipo": "01",
  "doc_afectado_serie": "F001",
  "doc_afectado_correlativo": "123",
  "cod_motivo": "04",
  "des_motivo": "Descuento comercial 10%",
  "items": [
    {
      "descripcion": "DESCUENTO POR VOLUMEN DE COMPRAS",
      "unidad": "ZZ",
      "cantidad": 1,
      "precio_unitario": 707.80,
      "tip_afe_igv": "10"
    }
  ]
}
```

### Ejemplo — Corrección RUC (cod_motivo=02)

```json
{
  "serie": "FC01",
  "fecha_emision": "2026-04-18",
  "cliente": {
    "tipo_doc": "6",
    "num_doc": "20555666777",
    "razon_social": "ACME CORP SAC"
  },
  "doc_afectado_tipo": "01",
  "doc_afectado_serie": "F001",
  "doc_afectado_correlativo": "123",
  "cod_motivo": "02",
  "des_motivo": "Anulación por error en RUC del cliente",
  "items": [
    {
      "descripcion": "LAPTOP HP PAVILION 15",
      "unidad": "NIU",
      "cantidad": 2,
      "precio_unitario": 2950.00,
      "tip_afe_igv": "10"
    }
  ]
}
```

Luego emite una **nueva factura** con el RUC correcto.

### Respuesta (201)

```json
{
  "estado": "exito",
  "mensaje": "Nota de crédito creada y encolada para envío a SUNAT.",
  "datos": {
    "id": 67,
    "tipo_documento": "07",
    "serie": "FC01",
    "correlativo": "00000067",
    "numero_completo": "FC01-67",
    "fecha_emision": "2026-04-18",
    "doc_afectado": {
      "tipo": "01",
      "serie": "F001",
      "correlativo": "00000123",
      "numero_completo": "F001-123"
    },
    "cod_motivo": "06",
    "des_motivo": "Devolución total por defecto",
    "mto_imp_venta": "7052.86",
    "sunat_status": "pendiente"
  }
}
```

---

## 2. `GET /notas-credito` — Listar

Mismos filtros que facturas + adicionales:

| Query | Descripción |
|-------|-------------|
| `doc_afectado_serie` | Serie del doc original |
| `doc_afectado_correlativo` | Correlativo original |
| `cod_motivo` | Cat. 09 |

```bash
curl "https://tu-api.com/api/v1/notas-credito?doc_afectado_serie=F001&cod_motivo=06" \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

---

## 3. `GET /notas-credito/{id}` — Ver

---

## 4. `PUT /notas-credito/{id}` — Actualizar

Solo si `sunat_status != 'aceptado'`. Permite corregir datos y reenvía automáticamente.

```bash
curl -X PUT https://tu-api.com/api/v1/notas-credito/67 \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}" \
  -H "Content-Type: application/json" \
  -d '{ "des_motivo": "Devolución corregida" }'
```

---

## 5. `GET /notas-credito/{id}/xml`
## 6. `GET /notas-credito/{id}/cdr`
## 7. `GET /notas-credito/{id}/pdf`

Idéntico a facturas. Headers y `format=a4|a5|ticket-80|ticket-58`.

---

## 8. `POST /notas-credito/{id}/reenviar`

---

## 🎯 Flujos típicos

### Anular factura aceptada

```
Original: F001-123 ACEPTADA
  ↓
POST /notas-credito
  doc_afectado_tipo=01, doc_afectado_serie=F001, doc_afectado_correlativo=123
  cod_motivo=01 (Anulación de la operación)
  items[] = mismos items de la factura original
  ↓
NC emitida: FC01-67  (revierte F001-123)
```

### Devolución parcial

```
Original: F001-123 con 2 laptops ACEPTADA
Cliente devuelve 1 laptop
  ↓
POST /notas-credito
  cod_motivo=06 (Devolución total/parcial)
  items[] = 1 laptop (la devuelta)
  ↓
NC emitida: FC01-68  (solo por 1 laptop)
```

### Corrección RUC

```
Original: F001-123 con RUC mal escrito → ACEPTADA (error no detectado por SUNAT)
  ↓
Opción A: POST /notas-credito con cod_motivo=02 → anular
POST /facturas (nueva con RUC correcto) → emitir de nuevo

Opción B: Si es el mismo día y no se envió aún → PUT /facturas/{id}
```

---

## ⚙️ Reglas importantes

- El documento afectado **NO necesariamente** debe estar en tu API. Puede ser de cualquier fecha (respetando plazos SUNAT: 1 año para anular, etc.)
- Si hay varias notas de crédito sobre el mismo documento, SUNAT lleva el control — tu API solo registra
- El IGV se calcula sobre los items igual que en factura
- **Motivo 03** (corrección descripción) debe tener los mismos montos que el original — el cambio es solo de texto
- **Motivos 01 y 02** suelen usar los mismos items que el original
- **Motivos 04, 05, 07, 08** usan items que describen el descuento/bonificación

---

## 📋 Estados SUNAT

| Estado | Significado |
|--------|-------------|
| `pendiente` | Encolada |
| `enviado` | En SUNAT |
| `aceptado` | ✅ |
| `rechazado` | ❌ revisar `sunat_code`/`sunat_description` |

Las notas de crédito **no se anulan con comunicación de baja** — si una NC fue aceptada erróneamente, emite una Nota de Débito que la revierta.

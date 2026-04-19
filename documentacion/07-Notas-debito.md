# 📘 Notas de Débito (Tipo 08)

> Base URL: `https://tu-api.com/api/v1`
> Documento que modifica **al alza** un comprobante ya emitido (factura/boleta).

## 📑 Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/notas-debito` | Crear nota de débito |
| `GET` | `/notas-debito` | Listar |
| `GET` | `/notas-debito/{id}` | Ver |
| `PUT` | `/notas-debito/{id}` | Actualizar (si NO aceptada) |
| `GET` | `/notas-debito/{id}/xml` | XML |
| `GET` | `/notas-debito/{id}/cdr` | CDR |
| `GET` | `/notas-debito/{id}/pdf` | PDF |
| `POST` | `/notas-debito/{id}/reenviar` | Reenviar |

---

## 🎯 Cuándo emitir Nota de Débito

**Casos más comunes:**
- Intereses por mora en el pago
- Aumento en el valor (cargos adicionales descubiertos después)
- Penalidades contractuales
- Ajustes de operaciones de exportación
- Ajustes por cargos adicionales no facturados originalmente

---

## 1. `POST /notas-debito` — Crear

### Body completo

```json
{
  "serie": "FD01",
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
  "cod_motivo": "01",
  "des_motivo": "Intereses por mora 15 días",

  "items": [
    {
      "descripcion": "INTERES POR MORA 30%",
      "unidad": "ZZ",
      "cantidad": 1,
      "precio_unitario": 100.00,
      "tip_afe_igv": "10"
    }
  ]
}
```

### Campos obligatorios del documento afectado

| Campo | Descripción |
|-------|-------------|
| `doc_afectado_tipo` | `01` (Factura), `03` (Boleta), `12` (Ticket) |
| `doc_afectado_serie` | Serie del doc original |
| `doc_afectado_correlativo` | Correlativo original |
| `cod_motivo` | Cat. 10 (ver abajo) |
| `des_motivo` | Descripción libre, max 250 |

### Serie

- Para ND de **facturas**: prefijo `FD` (`FD01`, `FD02`, etc.)
- Para ND de **boletas**: prefijo `BD` (`BD01`, etc.)

### Catálogo 10 — Tipos de Nota de Débito

| Código | Descripción | Uso típico |
|--------|-------------|------------|
| `01` | Intereses por mora | Mora en pago atrasado |
| `02` | Aumento en el valor | Ajuste al alza |
| `03` | Penalidades / otros conceptos | Multa contractual |
| `04` | Ajustes de valor de exportación | Solo para exportaciones |
| `05` | Ajustes por corrección de la moneda | Cambio de moneda |
| `06` | Ajustes por corrección de la cantidad | Cantidad mayor a la inicial |
| `07` | Ajustes por descuentos no aplicados | Recupero de descuento |
| `08` | Ajustes por cargos adicionales | Cargos omitidos |
| `09` | Otros | Caso no cubierto |
| `11` | Ajustes de operaciones de exportación | Exportación |
| `12` | Ajustes afectos al IVAP | Arroz |

### Items

**Mismos campos que factura**. Los items representan el **monto adicional** a cobrar.

### Ejemplo — Intereses por mora

```json
{
  "serie": "FD01",
  "fecha_emision": "2026-05-20",
  "cliente": { "tipo_doc":"6", "num_doc":"20555666777", "razon_social":"ACME SAC" },
  "doc_afectado_tipo": "01",
  "doc_afectado_serie": "F001",
  "doc_afectado_correlativo": "123",
  "cod_motivo": "01",
  "des_motivo": "Intereses moratorios por 30 días de atraso (tasa 1%/mes)",
  "items": [
    {
      "descripcion": "INTERES MORATORIO — F001-123",
      "unidad": "ZZ",
      "cantidad": 1,
      "precio_unitario": 59.01,
      "tip_afe_igv": "10"
    }
  ]
}
```

### Ejemplo — Penalidad contractual

```json
{
  "serie": "FD01",
  "fecha_emision": "2026-04-18",
  "cliente": { "tipo_doc":"6", "num_doc":"20555666777", "razon_social":"ACME SAC" },
  "doc_afectado_tipo": "01",
  "doc_afectado_serie": "F001",
  "doc_afectado_correlativo": "200",
  "cod_motivo": "03",
  "des_motivo": "Penalidad por incumplimiento de SLA (cláusula 7.2 del contrato)",
  "items": [
    {
      "descripcion": "PENALIDAD SLA — Abril 2026",
      "unidad": "ZZ",
      "cantidad": 1,
      "precio_unitario": 500.00,
      "tip_afe_igv": "10"
    }
  ]
}
```

### Ejemplo — Aumento por cargo omitido

```json
{
  "serie": "FD01",
  "fecha_emision": "2026-04-18",
  "cliente": { "tipo_doc":"6", "num_doc":"20555666777", "razon_social":"ACME SAC" },
  "doc_afectado_tipo": "01",
  "doc_afectado_serie": "F001",
  "doc_afectado_correlativo": "150",
  "cod_motivo": "08",
  "des_motivo": "Flete no facturado originalmente en F001-150",
  "items": [
    {
      "descripcion": "SERVICIO DE FLETE (ajuste)",
      "unidad": "ZZ",
      "cantidad": 1,
      "precio_unitario": 100.00,
      "tip_afe_igv": "10"
    }
  ]
}
```

### Ejemplo — ND sobre boleta

```json
{
  "serie": "BD01",
  "fecha_emision": "2026-04-18",
  "cliente": { "tipo_doc":"1", "num_doc":"12345678", "razon_social":"JUAN PEREZ" },
  "doc_afectado_tipo": "03",
  "doc_afectado_serie": "B001",
  "doc_afectado_correlativo": "500",
  "cod_motivo": "02",
  "des_motivo": "Aumento por extras no facturados",
  "items": [
    {
      "descripcion": "EXTRAS",
      "unidad": "ZZ",
      "cantidad": 1,
      "precio_unitario": 20.00,
      "tip_afe_igv": "10"
    }
  ]
}
```

### Respuesta (201)

```json
{
  "estado": "exito",
  "mensaje": "Nota de débito creada y encolada para envío a SUNAT.",
  "datos": {
    "id": 42,
    "tipo_documento": "08",
    "serie": "FD01",
    "correlativo": "00000042",
    "numero_completo": "FD01-42",
    "fecha_emision": "2026-04-18",
    "doc_afectado": {
      "tipo": "01",
      "serie": "F001",
      "correlativo": "00000123",
      "numero_completo": "F001-123"
    },
    "cod_motivo": "01",
    "des_motivo": "Intereses por mora 15 días",
    "mto_imp_venta": "118.00",
    "sunat_status": "pendiente"
  }
}
```

---

## 2. `GET /notas-debito` — Listar

Mismos filtros que facturas + adicionales:

| Query | Descripción |
|-------|-------------|
| `doc_afectado_serie` | Serie del doc original |
| `doc_afectado_correlativo` | Correlativo original |
| `cod_motivo` | Cat. 10 |

---

## 3-8. Ver/Actualizar/XML/CDR/PDF/Reenviar

Idéntico comportamiento a facturas/notas de crédito.

```bash
# Ver
curl https://tu-api.com/api/v1/notas-debito/42 -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"

# Actualizar (si no aceptada)
curl -X PUT https://tu-api.com/api/v1/notas-debito/42 \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}" \
  -H "Content-Type: application/json" \
  -d '{ "des_motivo": "Motivo corregido" }'

# PDF
curl -o nd.pdf https://tu-api.com/api/v1/notas-debito/42/pdf \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"

# XML
curl -o nd.xml https://tu-api.com/api/v1/notas-debito/42/xml \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"

# CDR
curl -o cdr.zip https://tu-api.com/api/v1/notas-debito/42/cdr \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"

# Reenviar
curl -X POST https://tu-api.com/api/v1/notas-debito/42/reenviar \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

---

## 🎯 Flujos típicos

### Cobro de intereses moratorios

```
1. Factura original F001-123 con vencimiento 2026-04-01
2. Cliente paga con 30 días de retraso
3. POST /notas-debito con cod_motivo=01 y monto de intereses
4. ND FD01-42 emitida — se cobra adicional
```

### Facturación de penalidad

```
1. Contrato con SLA incluye penalidad por incumplimiento
2. Cliente incumple
3. POST /notas-debito con cod_motivo=03
4. ND emitida contra la factura del servicio
```

### Ajuste de monto omitido

```
1. Factura F001-150 olvidó incluir el flete
2. POST /notas-debito con cod_motivo=08 (ajustes por cargos adicionales)
3. ND FD01-43 complementa la factura
```

---

## ⚙️ Reglas importantes

- La nota de débito **aumenta** la obligación del cliente (ND suma, NC resta)
- Los items reflejan el monto **adicional**, no el total
- El IGV se calcula sobre los items igual que en factura
- **Cod_motivo 01** (intereses por mora) — el monto suele ser un cálculo: monto_deuda × tasa × días
- **Cod_motivo 06** (ajustes por cantidad) — solo cantidad adicional, no el total
- SUNAT valida que el documento afectado exista en su sistema

---

## 🔗 Relacionados

- Para **anular** una factura (no aumentar) → usar Nota de Crédito
- Para **anular una ND errónea** → Nota de Crédito contra la ND
- Si la ND afecta una boleta y el cliente pagó el adicional → registrar con `POST /boletas/{id}/pagos`

---

## 📋 Estados SUNAT

Idéntico a facturas/notas de crédito: `pendiente`, `enviado`, `aceptado`, `rechazado`, `anulado`.

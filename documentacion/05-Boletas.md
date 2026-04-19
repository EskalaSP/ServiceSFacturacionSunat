# 📙 Boletas de Venta (Tipo 03)

> Base URL: `https://tu-api.com/api/v1`
> Serie requerida: debe empezar con `B` (ej: `B001`) o ser 4 dígitos numéricos.

## 📑 Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/boletas` | Crear boleta |
| `GET` | `/boletas` | Listar boletas |
| `GET` | `/boletas/{id}` | Ver boleta |
| `PUT` | `/boletas/{id}` | Actualizar (si NO aceptada) |
| `DELETE` | `/boletas/{id}` | Borrar localmente (si no enviada) |
| `GET` | `/boletas/{id}/xml` | XML firmado |
| `GET` | `/boletas/{id}/cdr` | CDR de SUNAT |
| `GET` | `/boletas/{id}/pdf` | PDF |
| `POST` | `/boletas/{id}/reenviar` | Reenviar |
| `POST` | `/boletas/{id}/pagos` | Registrar pago |
| `GET` | `/boletas/{id}/pagos` | Listar pagos |
| `DELETE` | `/boletas/{id}/pagos/{paymentId}` | Anular pago |

---

## 🔑 Diferencias clave con Factura

1. **Cliente** — puede ser DNI (`tipo_doc=1`) o "sin documento" (`tipo_doc=0`)
2. **Totales < S/ 700** — no requiere identificar al cliente obligatoriamente
3. **Envío a SUNAT** — boletas suelen enviarse por **Resumen Diario** (ver `09-Anular.md` sección resúmenes) — aunque también se admiten envíos individuales
4. **Serie** — prefijo `B`

---

## 1. `POST /boletas` — Crear boleta

### Body — ejemplo básico con DNI

```json
{
  "serie": "B001",
  "fecha_emision": "2026-04-18",
  "tipo_moneda": "PEN",
  "forma_pago": "Contado",

  "cliente": {
    "tipo_doc": "1",
    "num_doc": "12345678",
    "razon_social": "JUAN PEREZ LOPEZ",
    "direccion": "CALLE FALSA 123"
  },

  "items": [
    {
      "descripcion": "PRODUCTO A",
      "unidad": "NIU",
      "cantidad": 1,
      "precio_unitario": 50.00
    }
  ],

  "pagos": [
    { "metodo": "efectivo", "monto": 50.00 }
  ]
}
```

### Body — ejemplo sin cliente identificado (venta menor)

```json
{
  "serie": "B001",
  "fecha_emision": "2026-04-18",
  "cliente": {
    "tipo_doc": "0",
    "num_doc": "-",
    "razon_social": "CLIENTES VARIOS"
  },
  "items": [
    { "descripcion": "PRODUCTO X", "unidad": "NIU", "cantidad": 1, "precio_unitario": 20.00 }
  ]
}
```

### Campos raíz — mismos que factura

| Campo | Tipo | Obligatorio |
|-------|------|-------------|
| `serie` | string(4) | ✅ `B001`-`B999` o 4 dígitos |
| `cod_local` | string(4) | ❌ |
| `fecha_emision` | date | ✅ |
| `fecha_vencimiento` | date | Si `forma_pago=Credito` |
| `tipo_operacion` | string | ❌ Cat. 51 (default `0101`) |
| `tipo_moneda` | string | ❌ `PEN` \| `USD` \| `EUR` |
| `forma_pago` | string | ❌ `Contado` \| `Credito` |

### Cliente — reglas

| `tipo_doc` | Notas |
|------------|-------|
| `0` | Doc. trib. no dom. sin RUC — usar en boletas sin identificación |
| `1` | DNI (8 dígitos) |
| `4` | Carnet extranjería |
| `6` | RUC (11 dígitos) |
| `7` | Pasaporte |

**Validación:** si DNI → 8 dígitos. Si RUC → 11 dígitos con prefijo `10/15/17/20`.

### Items y tributos

**Idénticos a factura** (ver `04-Facturas.md` sección Items). Todos los catálogos aplican: Cat. 07 (afectación IGV), Cat. 08 (ISC), Cat. 53 (descuentos), Cat. 54 (detracciones), etc.

### Ejemplo con múltiples métodos de pago (pago mixto)

```json
{
  "serie": "B001",
  "fecha_emision": "2026-04-18",
  "cliente": { "tipo_doc": "1", "num_doc": "12345678", "razon_social": "JUAN PEREZ" },
  "items": [
    { "descripcion": "PRODUCTO", "unidad": "NIU", "cantidad": 1, "precio_unitario": 118.00 }
  ],
  "pagos": [
    { "metodo": "yape", "monto": 50.00, "referencia": "YP-001" },
    { "metodo": "efectivo", "monto": 68.00 }
  ]
}
```

### Ejemplo con ICBPER (bolsa plástica)

```json
{
  "serie": "B001",
  "fecha_emision": "2026-04-18",
  "cliente": { "tipo_doc": "0", "num_doc": "-", "razon_social": "CLIENTES VARIOS" },
  "items": [
    {
      "descripcion": "PRODUCTO A",
      "unidad": "NIU",
      "cantidad": 1,
      "precio_unitario": 100.00,
      "tip_afe_igv": "10"
    },
    {
      "descripcion": "BOLSA PLÁSTICA",
      "unidad": "BG",
      "cantidad": 1,
      "precio_unitario": 0.50,
      "tip_afe_igv": "10",
      "icbper": 0.50,
      "factor_icbper": 0.50
    }
  ]
}
```

### Respuesta (201)

```json
{
  "estado": "exito",
  "mensaje": "Boleta creada y encolada para envío a SUNAT.",
  "datos": {
    "id": 345,
    "tipo_documento": "03",
    "serie": "B001",
    "correlativo": "00000345",
    "numero_completo": "B001-345",
    "fecha_emision": "2026-04-18",
    "cliente": { "tipo_doc": "1", "num_doc": "12345678", "razon_social": "JUAN PEREZ LOPEZ" },
    "tipo_moneda": "PEN",
    "mto_imp_venta": "50.00",
    "sunat_status": "pendiente",
    "items": [...]
  }
}
```

---

## 2. `GET /boletas` — Listar

Mismos filtros que `/facturas`:
- `buscar`, `serie`, `correlativo`
- `cliente_doc`, `estado`, `payment_status`, `moneda`
- `desde`, `hasta`
- `con=items,payments`
- `por_pagina`

```bash
curl "https://tu-api.com/api/v1/boletas?desde=2026-04-01&hasta=2026-04-18" \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"
```

---

## 3-7. Ver / Actualizar / XML / CDR / PDF

**Idéntico comportamiento a Facturas**, solo cambia la ruta base. Ejemplo:

```bash
# Ver
curl https://tu-api.com/api/v1/boletas/345 -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"

# Actualizar (solo si no está aceptada)
curl -X PUT https://tu-api.com/api/v1/boletas/345 \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}" \
  -H "Content-Type: application/json" \
  -d '{ "observacion": "Corrección" }'

# PDF formato ticket
curl -o boleta.pdf "https://tu-api.com/api/v1/boletas/345/pdf?format=ticket-80" \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"

# XML
curl -o boleta.xml https://tu-api.com/api/v1/boletas/345/xml \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"

# CDR
curl -o cdr.zip https://tu-api.com/api/v1/boletas/345/cdr \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

---

## 8. `DELETE /boletas/{id}`

Borra la boleta **localmente** — solo si aún no fue enviada a SUNAT (estado `pendiente`).

```bash
curl -X DELETE https://tu-api.com/api/v1/boletas/345 \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

Si ya fue aceptada → usar Nota de Crédito (anulación) o comunicación de baja.

---

## 9. `POST /boletas/{id}/reenviar`

Idéntico a facturas.

---

## 10. Pagos asociados

Idéntico a facturas:

```bash
# Registrar pago
curl -X POST https://tu-api.com/api/v1/boletas/345/pagos \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}" \
  -H "Content-Type: application/json" \
  -d '{
    "metodo": "efectivo",
    "monto": 50.00
  }'

# Listar
curl https://tu-api.com/api/v1/boletas/345/pagos \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"

# Eliminar pago
curl -X DELETE https://tu-api.com/api/v1/boletas/345/pagos/99 \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

---

## 🎯 Flujos típicos

### Venta rápida al mostrador

```
1. POST /boletas         → { serie:"B001", cliente: { tipo_doc:"0", razon_social:"CLIENTES VARIOS" }, items:[...] }
2. GET /boletas/{id}/pdf?format=ticket-80  → imprimir ticket
3. [Sistema] Job → SUNAT  → aceptado
```

### Venta con identificación (monto mayor)

```
1. GET /buscar-documento?tipo=1&numero=12345678  → autocompletar datos
2. POST /boletas                                  → con cliente identificado
3. POST /boletas/{id}/pagos                       → registrar pago
```

### Envío por resumen diario

```
1. POST /boletas (N boletas del día)
2. POST /resumenes { fecha_resumen: "2026-04-18" }  → agrupa todas las boletas del día
3. [Sistema] envía el resumen a SUNAT
4. GET /resumenes/{id}/estado                        → verificar
```

Ver [09-Anular.md](./09-Anular.md) sección resúmenes.

---

## 📋 Estados SUNAT

| Estado | Significado |
|--------|-------------|
| `pendiente` | Encolada |
| `enviado` | En SUNAT |
| `aceptado` | ✅ |
| `rechazado` | ❌ |
| `anulado` | Anulada |
| `anulacion_en_proceso` | Baja en curso |

---

## 🔗 Relacionados

- **Anular boleta aceptada:** usar Nota de Crédito → [`06-Notas-credito.md`](./06-Notas-credito.md)
- **Envío masivo:** usar Resumen Diario → [`09-Anular.md`](./09-Anular.md)
- **Actualizar cliente en boleta rechazada:** `PUT /boletas/{id}` (automáticamente reenvía)

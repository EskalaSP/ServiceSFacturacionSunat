# 📗 Facturas Electrónicas (Tipo 01)

> Base URL: `https://tu-api.com/api/v1`
> Todos los endpoints requieren `X-Api-Key` + `X-Api-Secret`.
> Serie requerida: debe empezar con `F` (ej: `F001`) o ser 4 dígitos numéricos.

## 📑 Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/facturas` | Crear factura |
| `GET` | `/facturas` | Listar facturas |
| `GET` | `/facturas/{id}` | Ver factura |
| `PUT` | `/facturas/{id}` | Actualizar (solo si NO aceptada) |
| `GET` | `/facturas/{id}/xml` | Descargar XML firmado |
| `GET` | `/facturas/{id}/cdr` | Descargar CDR de SUNAT |
| `GET` | `/facturas/{id}/pdf` | PDF (formato A4/A5/ticket) |
| `POST` | `/facturas/{id}/reenviar` | Reenviar a SUNAT |
| `POST` | `/facturas/{id}/pagos` | Registrar pago |
| `GET` | `/facturas/{id}/pagos` | Listar pagos |
| `DELETE` | `/facturas/{id}/pagos/{paymentId}` | Anular pago |

---

## 1. `POST /facturas` — Crear factura

### Body completo

```json
{
  "serie": "F001",
  "cod_local": "0000",
  "fecha_emision": "2026-04-18",
  "fecha_vencimiento": "2026-05-18",
  "tipo_operacion": "0101",
  "tipo_moneda": "PEN",
  "forma_pago": "Contado",

  "cliente": {
    "tipo_doc": "6",
    "num_doc": "20555666777",
    "razon_social": "ACME CORP SAC",
    "direccion": "JR. ACME 456 - LIMA",
    "email": "facturas@acme.com",
    "telefono": "+51 12345678"
  },

  "items": [
    {
      "codigo": "P001",
      "cod_producto_sunat": "10191509",
      "descripcion": "LAPTOP HP PAVILION 15",
      "unidad": "NIU",
      "cantidad": 2,
      "precio_unitario": 2950.00,
      "tip_afe_igv": "10",
      "porcentaje_igv": 18
    },
    {
      "codigo": "P002",
      "descripcion": "MOUSE LOGITECH",
      "unidad": "NIU",
      "cantidad": 3,
      "precio_unitario": 59.00,
      "tip_afe_igv": "10"
    }
  ],

  "observacion": "Pedido #12345",

  "pagos": [
    { "metodo": "yape", "monto": 177.00, "referencia": "YP-2026-0001" }
  ]
}
```

### Campos raíz

| Campo | Tipo | Obligatorio | Notas |
|-------|------|-------------|-------|
| `serie` | string(4) | ✅ | `F001`-`F999` o 4 dígitos |
| `cod_local` | string(4) | ❌ | Si no se envía usa el de la sucursal principal |
| `fecha_emision` | date | ✅ | `yyyy-mm-dd` |
| `fecha_vencimiento` | date | ❌ | Requerido si `forma_pago=Credito` |
| `tipo_operacion` | string | ❌ | Cat. 51 (default `0101`) |
| `tipo_moneda` | string | ❌ | `PEN` (default) \| `USD` \| `EUR` |
| `forma_pago` | string | ❌ | `Contado` (default) \| `Credito` |
| `leyenda` | string(500) | ❌ | Leyenda custom |
| `observacion` | string(500) | ❌ | |

### Cliente

| Campo | Obligatorio |
|-------|-------------|
| `tipo_doc` | ✅ Cat. 06 (normalmente `6` RUC) |
| `num_doc` | ✅ max 15 |
| `razon_social` | ✅ max 1500 |
| `direccion` | ❌ max 500 |
| `email`, `telefono` | ❌ |

**Regla:** en factura el cliente **normalmente** debe ser RUC (`tipo_doc=6`). RUC debe tener 11 dígitos y empezar con `10`, `15`, `17` o `20`.

### Items

| Campo | Tipo | Obligatorio | Notas |
|-------|------|-------------|-------|
| `codigo` | string(30) | ❌ | Código interno |
| `cod_producto_sunat` | string(8) | ❌ | UNSPSC (Cat. 25) |
| `descripcion` | string(500) | ✅ | |
| `unidad` | string | ✅ | UN/CEFACT (ver tabla abajo) |
| `cantidad` | numeric | ✅ | > 0 |
| `precio_unitario` | numeric | ✅ | Con IGV |
| `tip_afe_igv` | string | ❌ | Cat. 07 (default `10`) |
| `porcentaje_igv` | numeric | ❌ | default 18 |
| `isc`, `porcentaje_isc`, `tip_sis_isc` | | ❌ | Para productos con ISC |
| `icbper`, `factor_icbper` | numeric | ❌ | Para bolsas (Ley 30884) |
| `descuentos` | array | ❌ | Ver estructura abajo |

**Unidades UN/CEFACT más usadas:** `NIU` (unidad), `KGM` (kg), `LTR` (litro), `MTR` (metro), `ZZ` (servicios), `BG` (bolsa), `BO` (botella), `BX` (caja), `DZN` (docena), `PK` (paquete), `SET`, `HUR` (hora), `DAY` (día), `MON` (mes).

### Ejemplo con impuestos especiales

**Operación exonerada:**
```json
{
  "items": [{
    "descripcion": "LIBRO (exonerado)",
    "unidad": "NIU",
    "cantidad": 1,
    "precio_unitario": 50.00,
    "tip_afe_igv": "20"
  }]
}
```

**Operación gratuita (bonificación):**
```json
{
  "items": [{
    "descripcion": "MUESTRA GRATIS",
    "unidad": "NIU",
    "cantidad": 1,
    "precio_unitario": 10.00,
    "tip_afe_igv": "15"
  }]
}
```

**Exportación:**
```json
{
  "tipo_operacion": "0200",
  "tipo_moneda": "USD",
  "items": [{
    "descripcion": "SERVICIO EXPORTADO",
    "unidad": "ZZ",
    "cantidad": 1,
    "precio_unitario": 1000.00,
    "tip_afe_igv": "40"
  }]
}
```

**Bolsa ICBPER:**
```json
{
  "items": [{
    "descripcion": "BOLSA PLÁSTICA",
    "unidad": "BG",
    "cantidad": 5,
    "precio_unitario": 0.50,
    "tip_afe_igv": "10",
    "icbper": 2.50,
    "factor_icbper": 0.50
  }]
}
```

### Descuentos por ítem

```json
{
  "items": [{
    "descripcion": "PRODUCTO A",
    "unidad": "NIU",
    "cantidad": 2,
    "precio_unitario": 100.00,
    "descuentos": [
      {
        "cod_tipo": "00",
        "factor": 0.10,
        "monto_base": 200.00,
        "monto": 20.00
      }
    ]
  }]
}
```

**Catálogo 53 — códigos de descuento/cargo:**
| Código | Descripción | Nivel |
|--------|-------------|-------|
| `00` | Descuentos que afectan base imponible | Ítem |
| `01` | Descuentos que NO afectan base imponible | Ítem |
| `02` | Descuentos globales que afectan base imp | Global |
| `03` | Descuentos globales que NO afectan base | Global |
| `04`/`05`/`06` | Descuentos globales por anticipos | Global |
| `47` | Cargos ítem afectos | Ítem |
| `49` | Cargos globales afectos | Global |
| `50` | Cargos globales NO afectos | Global |
| `51`-`53` | Percepciones | Global |

### Descuentos globales

```json
{
  "descuentos_globales": [
    {
      "cod_tipo": "02",
      "porcentaje": 0.05,
      "monto": 100.00,
      "monto_base": 2000.00
    }
  ]
}
```

### Crédito — cuotas

Si `forma_pago=Credito`, las cuotas son obligatorias:

```json
{
  "forma_pago": "Credito",
  "cuotas": [
    { "monto": 1000.00, "fecha_pago": "2026-05-15" },
    { "monto": 1000.00, "fecha_pago": "2026-06-15" }
  ]
}
```

### Guías relacionadas

```json
{
  "guias": [
    { "tipo_doc": "09", "nro_doc": "T001-123" }
  ]
}
```

### Detracción (Op. sujeta a detracción)

```json
{
  "tipo_operacion": "1001",
  "detraccion": {
    "cod_bien": "037",
    "porcentaje": 10,
    "cta_banco": "00012345678901234567",
    "cod_medio_pago": "003",
    "monto": 100.00
  }
}
```

**Catálogo 54 — Bienes/servicios sujetos a detracción:**
| Código | Descripción | Tasa |
|--------|-------------|------|
| `019` | Arrendamiento de bienes muebles | 10% |
| `022` | Otros servicios empresariales | 10% |
| `027` | Servicio de transporte de carga | 4% |
| `030` | Contratos de construcción | 4% |
| `037` | Demás servicios gravados con IGV | 10% |
| `040` | Bien inmueble gravado con IGV | 4% |

**Catálogo 59 — Medios de pago:** `003` (Transferencia), `005` (Tarjeta débito), `006` (Tarjeta crédito), `008` (Efectivo), `009` (Efectivo demás casos).

### Percepción

```json
{
  "tipo_operacion": "2001",
  "percepcion": {
    "cod_regimen": "01",
    "porcentaje": 2,
    "monto": 20.00,
    "base": 1000.00
  }
}
```

**Cat. 22:**
- `01` — Venta Interna (2%)
- `02` — Combustible (1%)
- `03` — Agente tasa especial (0.5%)

### Anticipos

```json
{
  "anticipos": [
    {
      "tipo_doc": "02",
      "serie": "F001",
      "correlativo": "100",
      "monto": 500.00
    }
  ],
  "total_anticipos": 500.00
}
```

### Leyendas custom

```json
{
  "leyendas": [
    { "code": "1000", "value": "SON UN MIL CIENTO OCHENTA Y 00/100 SOLES" },
    { "code": "2006", "value": "Operación sujeta a detracción" }
  ]
}
```

### Respuesta (201 Created)

```json
{
  "estado": "exito",
  "mensaje": "Factura creada y encolada para envío a SUNAT.",
  "datos": {
    "id": 123,
    "tipo_documento": "01",
    "serie": "F001",
    "correlativo": "00000123",
    "numero_completo": "F001-123",
    "fecha_emision": "2026-04-18",
    "cliente": {
      "tipo_doc": "6",
      "num_doc": "20555666777",
      "razon_social": "ACME CORP SAC"
    },
    "tipo_moneda": "PEN",
    "forma_pago": "Contado",
    "mto_oper_gravadas": "5977.00",
    "mto_igv": "1075.86",
    "mto_imp_venta": "7052.86",
    "leyenda": "SON SIETE MIL CINCUENTA Y DOS CON 86/100 SOLES",
    "sunat_status": "pendiente",
    "sunat_code": null,
    "sunat_description": null,
    "items": [...]
  }
}
```

### Estados SUNAT (`sunat_status`)

| Estado | Significado |
|--------|-------------|
| `pendiente` | Aún no enviado / encolado |
| `enviado` | En SUNAT, esperando respuesta |
| `aceptado` | ✅ Aceptada |
| `rechazado` | ❌ Error SUNAT (ver `sunat_code` y `sunat_description`) |
| `anulado` | Anulada por comunicación de baja |
| `anulacion_en_proceso` | Comunicación de baja en curso |

---

## 2. `GET /facturas` — Listar

### Query params

| Param | Descripción |
|-------|-------------|
| `buscar` | Texto libre (serie, correlativo, cliente) |
| `serie` | Filtrar por serie exacta |
| `correlativo` | Filtrar por correlativo (carga items automáticamente) |
| `cliente_doc` | RUC/DNI del cliente |
| `estado` | `pendiente`, `enviado`, `aceptado`, `rechazado`, `anulado` |
| `payment_status` | `pendiente`, `parcial`, `pagado` |
| `moneda` | `PEN`, `USD`, `EUR` |
| `desde`, `hasta` | Rango de `fecha_emision` |
| `con` | CSV con relaciones: `items,payments` |
| `por_pagina` | Default 15, máx 100 |

### Ejemplo

```bash
curl "https://tu-api.com/api/v1/facturas?estado=aceptado&desde=2026-04-01&hasta=2026-04-30&con=items,payments" \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": {
    "datos": [/* facturas */],
    "paginacion": {
      "pagina_actual": 1,
      "ultima_pagina": 8,
      "por_pagina": 15,
      "total": 112
    }
  }
}
```

---

## 3. `GET /facturas/{id}` — Ver factura

Devuelve la factura con `items` + `payments`.

```bash
curl https://tu-api.com/api/v1/facturas/123 \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"
```

---

## 4. `PUT /facturas/{id}` — Actualizar

⚠️ **Solo si `sunat_status != 'aceptado'`.**

Al actualizar:
- Si envías `items[]`, se recalculan totales/impuestos y se reemplazan los ítems
- Si envías `cliente`, se actualizan los campos del cliente
- Se resetea `sunat_status → pendiente`
- Se reencola automáticamente a SUNAT

### Ejemplo — corregir RUC rechazado

```bash
curl -X PUT https://tu-api.com/api/v1/facturas/123 \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}" \
  -H "Content-Type: application/json" \
  -d '{
    "cliente": {
      "tipo_doc": "6",
      "num_doc": "20555666778",
      "razon_social": "ACME CORP SAC CORREGIDO",
      "direccion": "JR. ACME 456 - LIMA"
    },
    "observacion": "Corrección de RUC del cliente"
  }'
```

### Ejemplo — recalcular ítems

```bash
curl -X PUT https://tu-api.com/api/v1/facturas/123 \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}" \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      {
        "codigo": "P001",
        "descripcion": "LAPTOP HP PAVILION 15",
        "unidad": "NIU",
        "cantidad": 2,
        "precio_unitario": 2950.00
      }
    ]
  }'
```

### Error — intentar editar una aceptada

```json
{
  "estado": "error",
  "mensaje": "No se puede editar una factura aceptada por SUNAT."
}
```

**Solución:** emitir Nota de Crédito (`POST /notas-credito`).

---

## 5. `GET /facturas/{id}/xml` — XML firmado

```bash
curl -o factura.xml \
  https://tu-api.com/api/v1/facturas/123/xml \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"
```

Headers respuesta:
```
Content-Type: application/xml
Content-Disposition: attachment; filename="F001-123.xml"
```

---

## 6. `GET /facturas/{id}/cdr` — CDR de SUNAT

CDR = Constancia de Recepción emitida por SUNAT. Disponible solo después de recibir respuesta.

```bash
curl -o cdr.zip \
  https://tu-api.com/api/v1/facturas/123/cdr \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"
```

**404** si aún no hay CDR (factura en estado `pendiente` o `enviado`).

---

## 7. `GET /facturas/{id}/pdf` — PDF

### Query params

- `format`: `a4` (default), `a5`, `ticket-80`, `ticket-58`

```bash
# A4 (default)
curl -o factura.pdf https://tu-api.com/api/v1/facturas/123/pdf \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"

# Ticket 80mm para impresora térmica
curl -o factura-ticket.pdf "https://tu-api.com/api/v1/facturas/123/pdf?format=ticket-80" \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"
```

Headers:
```
Content-Type: application/pdf
Content-Disposition: inline; filename="F001-123.pdf"
Cache-Control: private, max-age=300
```

---

## 8. `POST /facturas/{id}/reenviar` — Reenviar a SUNAT

Útil cuando SUNAT rechazó por tema transitorio, o quieres forzar reenvío.

```bash
curl -X POST https://tu-api.com/api/v1/facturas/123/reenviar \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"
```

Resetea `sunat_status → pendiente` y encola el job.

**Error si ya está aceptada:**
```json
{
  "estado": "error",
  "mensaje": "Esta factura ya fue aceptada por SUNAT."
}
```

---

## 9. Pagos asociados

### `POST /facturas/{id}/pagos`

```bash
curl -X POST https://tu-api.com/api/v1/facturas/123/pagos \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}" \
  -H "Content-Type: application/json" \
  -d '{
    "metodo": "yape",
    "monto": 1000.00,
    "referencia": "YP-20260418-001",
    "monto_recibido": 1000.00,
    "notas": "Primera cuota"
  }'
```

**Métodos:** `efectivo`, `yape`, `plin`, `tunki`, `transferencia`, `tarjeta_credito`, `tarjeta_debito`, `deposito`, `cheque`, `otro`.

### `GET /facturas/{id}/pagos`

Lista pagos registrados.

### `DELETE /facturas/{id}/pagos/{paymentId}`

Anula un pago específico.

---

## 🎯 Flujos típicos

### Flujo feliz

```
1. POST /facturas                 → factura creada, sunat_status=pendiente
2. [Sistema] Job envía a SUNAT    → sunat_status=enviado
3. [Sistema] SUNAT responde       → sunat_status=aceptado
4. GET /facturas/{id}/pdf         → imprimir/enviar al cliente
5. GET /facturas/{id}/xml         → guardar XML
6. GET /facturas/{id}/cdr         → guardar CDR
```

### Flujo de corrección tras rechazo

```
1. POST /facturas                  → creada
2. SUNAT rechaza (ej: código 2325) → sunat_status=rechazado
3. PUT /facturas/{id}              → corregir datos
4. [Sistema] reencola              → sunat_status=pendiente → enviado → aceptado
```

### Flujo de anulación

```
# Si la factura está ACEPTADA y quieres anularla:
1. POST /anulaciones               → comunicación de baja (ver 09-Anular.md)

# Si quieres revertir el valor:
1. POST /notas-credito             → nota de crédito por anulación (ver 06-Notas-credito.md)
```

---

## 📋 Catálogos SUNAT referenciados

Todos los códigos están en [config/sunat_catalogs.php](../config/sunat_catalogs.php).

- **Cat. 01** — Tipo documento: `01`=Factura
- **Cat. 06** — Tipo doc identidad
- **Cat. 07** — Tipo afectación IGV (en ítems)
- **Cat. 09** — Tipo Nota de Crédito
- **Cat. 10** — Tipo Nota de Débito
- **Cat. 12** — Documentos relacionados tributarios
- **Cat. 22** — Régimen percepciones
- **Cat. 51** — Tipo de operación
- **Cat. 52** — Leyendas
- **Cat. 53** — Cargos/descuentos
- **Cat. 54** — Detracciones
- **Cat. 59** — Medios de pago

---

## ⚙️ Reglas de negocio clave

- **Correlativo:** se autoasigna en base a la `serie` y la última factura de ese tenant
- **Recalculo automático:** si envías totales, el sistema los recalcula desde los items (igualmente, puedes confiarles)
- **IGV default:** 18% (configurable)
- **Redondeo:** 2 decimales (Formato 1.3.4)
- **Fecha vencimiento:** requerida solo si `forma_pago=Credito`
- **Detracción:** aplicable solo si monto total > S/ 700
- **Cliente RUC:** obligatorio en factura (para boleta puede ser DNI/sin documento)

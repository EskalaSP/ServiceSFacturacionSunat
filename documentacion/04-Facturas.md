# 📗 Facturas Electrónicas (Tipo 01)

> Base URL: `https://tu-api.com/api/v1`
> Todos los endpoints requieren `X-Api-Key` + `X-Api-Secret`.
> Serie requerida: debe empezar con `F` (ej: `F001`) o ser 4 dígitos numéricos.

## 📑 Tabla de contenido

- [Endpoints](#-endpoints)
- [1. POST /facturas — Crear](#1-post-facturas--crear-factura)
- [2. GET /facturas — Listar](#2-get-facturas--listar)
- [3. GET /facturas/{id} — Ver](#3-get-facturasid--ver-factura)
- [4. PUT /facturas/{id} — Actualizar](#4-put-facturasid--actualizar)
- [5. XML firmado](#5-get-facturasidxml--xml-firmado)
- [6. CDR de SUNAT](#6-get-facturasidcdr--cdr-de-sunat)
- [7. PDF](#7-get-facturasidpdf--pdf)
- [8. Reenviar a SUNAT](#8-post-facturasidreenviar--reenviar-a-sunat)
- [9. Pagos](#9-pagos-asociados)
- [10. Sucursales — Filtrar y asociar](#10-sucursales--filtrar-y-asociar-facturas)
- [11. Descuentos y cargos — Guía completa](#11-descuentos-y-cargos--guía-completa)
- [Flujos típicos](#-flujos-típicos)
- [Catálogos SUNAT](#-catálogos-sunat-referenciados)
- [Reglas de negocio](#️-reglas-de-negocio-clave)

---

## 📑 Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/facturas` | Crear factura |
| `GET` | `/facturas` | Listar facturas (con filtro `?sucursal_id=N`) |
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
| `sucursal_id` | integer | ❌ | ID de la sucursal (ver `GET /sucursales`). Si no se envía, la factura no queda asociada a ninguna sucursal |
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

Aplican sobre el valor de un ítem específico antes de calcular el IGV. Se envían dentro de `items[].descuentos[]`.

#### Formas de enviar el descuento por ítem

La API acepta **3 formas equivalentes** — elige la que sea más cómoda:

```json
// Forma 1 — Solo el monto fijo (la API calcula todo automáticamente)
"descuentos": [{ "cod_tipo": "00", "monto": 4.00 }]

// Forma 2 — Por porcentaje (0-100)
"descuentos": [{ "cod_tipo": "00", "porcentaje": 20 }]

// Forma 3 — Completa (monto_base + factor + monto)
"descuentos": [{ "cod_tipo": "00", "monto_base": 20.00, "factor": 0.20, "monto": 4.00 }]
```

> Para la mayoría de casos usa la **Forma 1** o **Forma 2** — el usuario solo indica cuánto descuenta.

#### Campos

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `cod_tipo` | string | ❌ | `"00"` afecta base IGV (default) · `"01"` no afecta IGV |
| `monto` | numeric | ✅* | Importe fijo del descuento sin IGV. *Requerido si no se envía `porcentaje` o `factor` |
| `porcentaje` | numeric | ✅* | Porcentaje de descuento (0-100). *Requerido si no se envía `monto` o `factor` |
| `factor` | numeric | ✅* | Tasa decimal (ej: `0.20` = 20%). *Requerido si no se envía `monto` o `porcentaje` |
| `monto_base` | numeric | ❌ | La API lo calcula automáticamente desde el valor venta del ítem |

#### Ejemplo completo — descuento por línea en 2 ítems

**Escenario:** 10 Cuadernos a S/ 2.36 c/u con 20% de descuento + 1 Radio a S/ 59.00 sin descuento.

**Paso 1 — Calcular el valor venta de cada ítem** (`precio_unitario ÷ 1.18`):

| Ítem | Cant. | P.Unit (c/IGV) | Valor venta (s/IGV) |
|------|-------|----------------|---------------------|
| Cuadernos | 10 | 2.36 | **20.00** |
| Radio | 1 | 59.00 | **50.00** |

**Paso 2 — Calcular el descuento del ítem P001:**

```
monto_base = 20.00        (valor venta s/IGV de los 10 cuadernos)
factor     = 0.20         (20% de descuento)
monto      = 20.00 × 0.20 = 4.00
```

**Paso 3 — Recalcular P001 con descuento aplicado:**

```
Valor venta neto = 20.00 - 4.00 = 16.00
IGV              = 16.00 × 0.18 = 2.88
Total P001       = 18.88
```

**Paso 4 — P002 sin descuento:**

```
Valor venta = 50.00
IGV         = 50.00 × 0.18 = 9.00
Total P002  = 59.00
```

**Totales finales:**

```
mto_oper_gravadas = 16.00 + 50.00 = 66.00
mto_igv           = 2.88  + 9.00  = 11.88
mto_imp_venta     =                 77.88
```

**Payload — forma simple (recomendada):**

```json
{
  "tipo_documento": "01",
  "serie": "F001",
  "fecha_emision": "2026-04-17",
  "tipo_operacion": "0101",
  "tipo_moneda": "PEN",
  "cliente": {
    "tipo_doc": "6",
    "num_doc": "20000000002",
    "razon_social": "EMPRESA X SAC"
  },
  "items": [
    {
      "codigo": "P001",
      "descripcion": "Cuadernos",
      "unidad": "NIU",
      "cantidad": 10,
      "precio_unitario": 2.36,
      "tip_afe_igv": "10",
      "descuentos": [
        { "monto": 4.00 }
      ]
    },
    {
      "codigo": "P002",
      "descripcion": "Radio",
      "unidad": "NIU",
      "cantidad": 1,
      "precio_unitario": 59.00,
      "tip_afe_igv": "10"
    }
  ]
}
```

**Payload — forma por porcentaje:**

```json
{
  "items": [
    {
      "codigo": "P001",
      "descripcion": "Cuadernos",
      "unidad": "NIU",
      "cantidad": 10,
      "precio_unitario": 2.36,
      "tip_afe_igv": "10",
      "descuentos": [
        { "porcentaje": 20 }
      ]
    }
  ]
}
```

**Respuesta esperada:**

```json
{
  "mto_oper_gravadas": "66.00",
  "mto_igv":           "11.88",
  "mto_imp_venta":     "77.88"
}
```

#### Diferencia entre cod_tipo 00 y 01

| cod_tipo | Efecto | Cuándo usar |
|----------|--------|-------------|
| `00` | Reduce la base imponible → el IGV se recalcula sobre el valor neto | Descuento comercial normal (el más común) |
| `01` | No reduce la base imponible → el IGV se calcula sobre el precio original | Descuento financiero (ej: pronto pago) que no debe afectar el IGV declarado |

**Ejemplo cod_tipo 01** (mismo caso pero IGV no cambia):

```
Valor venta s/IGV  = 20.00
IGV (no cambia)    = 20.00 × 0.18 = 3.60
Descuento          = 4.00
Total P001         = 23.60 - 4.00 = 19.60  (pero IGV sigue siendo 3.60)
```

#### Múltiples descuentos en un mismo ítem

Puedes enviar varios descuentos por ítem — se aplican en orden:

```json
{
  "descuentos": [
    { "cod_tipo": "00", "monto_base": 100.00, "factor": 0.10, "monto": 10.00 },
    { "cod_tipo": "00", "monto_base": 90.00,  "factor": 0.05, "monto": 4.50  }
  ]
}
```

```
Valor original  = 100.00
1er descuento   = -10.00  → base = 90.00
2do descuento   = -4.50   → base = 85.50
IGV             = 85.50 × 0.18 = 15.39
Total           = 100.89
```

Con `cod_tipo "00"` el descuento reduce la base imponible del ítem antes de calcular el IGV.

### Descuentos globales

Aplican sobre el total de la factura, después de sumar todos los ítems.

#### Formas de enviar el descuento global

La API acepta **3 formas equivalentes** — elige la que sea más cómoda:

```json
// Forma 1 — Solo el monto (la API calcula todo automáticamente)
"descuentos_globales": [{ "cod_tipo": "02", "monto": 20.00 }]

// Forma 2 — Por porcentaje (0-100)
"descuentos_globales": [{ "cod_tipo": "02", "porcentaje": 5 }]

// Forma 3 — Completa (monto_base + factor + monto)
"descuentos_globales": [{ "cod_tipo": "02", "monto_base": 70.00, "factor": 0.0429, "monto": 3.00 }]
```

> Para la mayoría de casos usa la **Forma 1** — solo escribe cuánto quieres descontar.

#### Campos

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `cod_tipo` | string | ✅ | Código catálogo 53 (`"02"` más común) |
| `monto` | numeric | ✅* | Importe del descuento sin IGV. *Requerido si no se envía `porcentaje` |
| `porcentaje` | numeric | ✅* | Porcentaje de descuento (0-100). *Requerido si no se envía `monto` |
| `monto_base` | numeric | ❌ | La API lo calcula automáticamente desde el total gravado |
| `factor` | numeric | ❌ | La API lo calcula automáticamente desde `monto` o `porcentaje` |

> `precio_unitario` en esta API es el precio **con IGV**. Para obtener el valor venta: `precio_unitario ÷ 1.18`

#### Catálogo 53 — códigos de descuento/cargo

| Código | Descripción | Nivel | Afecta IGV |
|--------|-------------|-------|------------|
| `00` | Descuento por ítem | Ítem | ✅ Sí |
| `01` | Descuento por ítem (financiero) | Ítem | ❌ No |
| `02` | Descuento global | Global | ✅ Sí |
| `03` | Descuento global (financiero/comercial) | Global | ❌ No |
| `04` | Descuento global por anticipo gravado | Global | ✅ Sí |
| `05` | Descuento global por anticipo exonerado | Global | ❌ No |
| `06` | Descuento global por anticipo inafecto | Global | ❌ No |
| `47` | Cargo por ítem | Ítem | ✅ Sí |
| `48` | Cargo por ítem (financiero) | Ítem | ❌ No |
| `49` | Cargo global (ej: flete, seguro) | Global | ✅ Sí |
| `50` | Cargo global (ej: intereses) | Global | ❌ No |
| `51`-`53` | Percepciones | Global | — |

#### Diferencia entre cod_tipo 02 y 03

**`02` — Afecta la base imponible del IGV (más común):**
El descuento reduce el valor de venta → el IGV se recalcula sobre la base reducida.

```
Valor venta total  = 70.00
Descuento (cod 02) = -3.00
Base gravada nueva = 67.00
IGV (67 × 18%)     = 12.06
Total a pagar      = 79.06
```

**`03` — No afecta la base imponible:**
El descuento se aplica al monto final pero el IGV no cambia.

```
Valor venta total  = 70.00
IGV                = 12.60  (no cambia)
Total bruto        = 82.60
Descuento (cod 03) = -3.00
Total a pagar      = 79.60
```

#### Ejemplo completo con descuento global de S/ 3.00

**Escenario:** 10 Cuadernos a S/ 2.36 c/u + 1 Radio a S/ 59.00. Descuento global de S/ 3.00 que afecta base IGV.

**Paso 1 — Calcular el valor venta de cada ítem** (`precio_unitario ÷ 1.18`):

| Ítem | Cant. | P.Unit (c/IGV) | Valor venta (s/IGV) | IGV | Total |
|------|-------|----------------|---------------------|-----|-------|
| Cuadernos | 10 | 2.36 | 20.00 | 3.60 | 23.60 |
| Radio | 1 | 59.00 | 50.00 | 9.00 | 59.00 |
| **Subtotal** | | | **70.00** | **12.60** | **82.60** |

**Paso 2 — Aplicar descuento global cod_tipo 02:**

```
monto_base = 70.00  (suma de valores venta s/IGV)
monto      = 3.00   (descuento sin IGV)
factor     = 3.00 ÷ 70.00 = 0.0429
```

**Paso 3 — Totales finales:**

```
Base gravada = 70.00 - 3.00  = 67.00
IGV          = 67.00 × 0.18  = 12.06
Total a pagar                = 79.06
```

**Payload — forma simple (recomendada):**

```json
{
  "tipo_documento": "01",
  "serie": "F001",
  "fecha_emision": "2026-05-07",
  "tipo_operacion": "0101",
  "tipo_moneda": "PEN",
  "cliente": {
    "tipo_doc": "6",
    "num_doc": "20000000002",
    "razon_social": "EMPRESA X SAC"
  },
  "items": [
    { "codigo": "P001", "descripcion": "Cuadernos", "unidad": "NIU", "cantidad": 10, "precio_unitario": 2.36,  "tip_afe_igv": "10" },
    { "codigo": "P002", "descripcion": "Radio",     "unidad": "NIU", "cantidad": 1,  "precio_unitario": 59.00, "tip_afe_igv": "10" }
  ],
  "descuentos_globales": [
    { "cod_tipo": "02", "monto": 3.00 }
  ]
}
```

**Payload — forma por porcentaje (5% de descuento):**

```json
{
  "descuentos_globales": [
    { "cod_tipo": "02", "porcentaje": 5 }
  ]
}
```

**Payload — forma completa (cuando necesitas control total):**

```json
{
  "descuentos_globales": [
    { "cod_tipo": "02", "monto_base": 70.00, "factor": 0.0429, "monto": 3.00 }
  ]
}
```

**Respuesta esperada (las 3 formas producen el mismo resultado):**

```json
{
  "mto_oper_gravadas": "67.00",
  "mto_igv":           "12.06",
  "mto_imp_venta":     "79.06"
}
```

#### Ejemplo con cargo global (flete)

Un cargo global con `cod_tipo "49"` suma al total y también afecta la base del IGV.

```json
{
  "items": [
    {
      "descripcion": "Mercadería",
      "unidad": "NIU",
      "cantidad": 1,
      "precio_unitario": 118.00,
      "tip_afe_igv": "10"
    }
  ],
  "cargos_globales": [
    {
      "cod_tipo": "49",
      "monto_base": 100.00,
      "factor": 0.10,
      "monto": 10.00
    }
  ]
}
```

```
Base gravada = 100.00 + 10.00 = 110.00
IGV          = 110.00 × 0.18 = 19.80
Total        = 129.80
```

#### Descuento fijo vs descuento porcentual

| Caso | monto_base | factor | monto |
|------|-----------|--------|-------|
| Descuento fijo de S/ 5.00 | `5.00` | `1` | `5.00` |
| Descuento fijo de S/ 5.00 (forma alternativa) | `70.00` | `0.0714` | `5.00` |
| Descuento del 10% sobre base 70.00 | `70.00` | `0.10` | `7.00` |
| Descuento del 5% sobre base 200.00 | `200.00` | `0.05` | `10.00` |

> La relación siempre debe cumplirse: `monto = monto_base × factor`

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

| Param | Tipo | Descripción |
|-------|------|-------------|
| `search` / `q` | string | Búsqueda libre: razón social, num doc, serie o correlativo |
| `serie` | string(4) | Serie exacta (ej: `F001`) o con comodín (ej: `F0%`) |
| `correlativo` | integer | Correlativo exacto (carga items automáticamente) |
| `correlativo_desde` | integer | Correlativo mínimo |
| `correlativo_hasta` | integer | Correlativo máximo |
| `client_num_doc` | string | RUC/DNI exacto del cliente |
| `client_tipo_doc` | string | Tipo doc: `6`=RUC, `1`=DNI, etc. |
| `cliente` | string | Búsqueda parcial en razón social (LIKE) |
| `sunat_status` | string | `pendiente`, `enviado`, `aceptado`, `rechazado`, `anulado` (permite varios separados por coma) |
| `estado_pago` | string | `pendiente`, `parcial`, `pagado` (permite varios separados por coma) |
| `tipo_moneda` | string | `PEN`, `USD`, `EUR` |
| `tipo_operacion` | string | `0101`, `0200`, `1001`, etc. |
| `forma_pago` | string | `Contado`, `Credito` |
| `fecha_desde` | date | Desde `fecha_emision` (`yyyy-mm-dd`) |
| `fecha_hasta` | date | Hasta `fecha_emision` (`yyyy-mm-dd`) |
| `vencimiento_desde` | date | Desde `fecha_vencimiento` |
| `vencimiento_hasta` | date | Hasta `fecha_vencimiento` |
| `monto_min` | numeric | Monto total mínimo |
| `monto_max` | numeric | Monto total máximo |
| `sucursal_id` | integer | **Filtrar por sucursal** (ver sección 10) |
| `con_cdr` | boolean | `true` = solo facturas con CDR descargado |
| `con` | string | Relaciones a cargar: `items`, `payments` (CSV) |
| `ordenar_por` | string | `fecha_emision`, `correlativo`, `mto_imp_venta`, `client_razon_social`, `sunat_status`, `created_at` (default) |
| `orden` | string | `desc` (default) \| `asc` |
| `por_pagina` | integer | Default `15`, máx `100` |

### Ejemplo

```bash
curl "https://tu-api.com/api/v1/facturas?sunat_status=aceptado&fecha_desde=2026-04-01&fecha_hasta=2026-04-30&con=items,payments" \
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

## 10. Sucursales — Filtrar y asociar facturas

Cada factura puede estar asociada a una sucursal mediante el campo `sucursal_id`. Esto permite que cada punto de venta o sede solo vea sus propios documentos.

### Paso 1 — Obtener el ID de tu sucursal

```bash
curl https://tu-api.com/api/v1/sucursales \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"
```

```json
{
  "datos": [
    { "id": 1, "nombre": "Sede Principal",      "cod_local": "0000", "is_principal": true  },
    { "id": 2, "nombre": "Sucursal Lima Norte",  "cod_local": "0001", "is_principal": false },
    { "id": 3, "nombre": "Sucursal Miraflores",  "cod_local": "0002", "is_principal": false }
  ]
}
```

### Paso 2 — Crear facturas vinculadas a una sucursal

```json
{
  "sucursal_id": 2,
  "serie": "F002",
  "fecha_emision": "2026-05-11",
  "cliente": { "tipo_doc": "6", "num_doc": "20100000001", "razon_social": "CLIENTE SAC" },
  "items": [{ "descripcion": "Producto", "unidad": "NIU", "cantidad": 1, "precio_unitario": 100 }]
}
```

> ⚠️ Si no envías `sucursal_id`, la factura **no queda asociada** a ninguna sucursal y no aparecerá al filtrar por ella.

### Paso 3 — Ver solo las facturas de una sucursal

```bash
# Facturas de la sucursal 2
curl "https://tu-api.com/api/v1/facturas?sucursal_id=2" \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"

# Facturas de la sucursal 2 aceptadas en mayo 2026
curl "https://tu-api.com/api/v1/facturas?sucursal_id=2&sunat_status=aceptado&fecha_desde=2026-05-01&fecha_hasta=2026-05-31" \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"
```

### También aplica a boletas, notas y guías

```bash
GET /api/v1/boletas?sucursal_id=2
GET /api/v1/notas-credito?sucursal_id=2
GET /api/v1/notas-debito?sucursal_id=2
GET /api/v1/guias-remision?sucursal_id=2
```

### Series por sucursal

Cada sucursal tiene asignadas sus propias series. Esto evita mezclar correlativos entre sedes:

| Sucursal | Series facturas | Series boletas |
|----------|----------------|----------------|
| Principal (0000) | `F001` | `B001` |
| Lima Norte (0001) | `F002` | `B002` |
| Miraflores (0002) | `F003` | `B003` |

Para consultar las series de una sucursal:

```bash
curl "https://tu-api.com/api/v1/sucursales/2" \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"
```

---

---

## 11. Descuentos y cargos — Guía completa

Ver sección **[Descuentos por ítem](#descuentos-por-ítem)** y **[Descuentos globales](#descuentos-globales)** dentro del cuerpo del POST `/facturas` arriba.

**Resumen rápido:**

| Tipo | Campo en el body | Cuándo usarlo |
|------|-----------------|---------------|
| Descuento por ítem | `items[].descuentos[]` | Descuento sobre un producto específico |
| Descuento global `02` | `descuentos_globales[]` | Promoción/descuento comercial que reduce la base del IGV |
| Descuento global `03` | `descuentos_globales[]` | Descuento financiero o nota de crédito futura (no toca IGV) |
| Cargo global `49` | `cargos_globales[]` | Flete, seguro u otro cargo que suma a la base del IGV |
| Cargo global `50` | `cargos_globales[]` | Intereses o recargos que no afectan el IGV |

**Regla de oro:** Solo necesitas enviar UNO de estos tres: `monto`, `porcentaje` o `factor`. La API calcula el resto automáticamente.

| Lo que envías | Ejemplo | La API calcula |
|---------------|---------|----------------|
| Solo `monto` | `"monto": 20.00` | `monto_base` y `factor` |
| Solo `porcentaje` | `"porcentaje": 10` | `monto_base`, `factor` y `monto` |
| Solo `factor` | `"factor": 0.10` | `monto_base` y `monto` |
| Los tres | `"monto_base": 70, "factor": 0.0429, "monto": 3` | Nada (usa los valores enviados) |

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
- **Sucursal:** enviar `sucursal_id` al crear para asociar la factura a una sede. Sin este campo, la factura no aparece en filtros por sucursal. Ver sección 10.

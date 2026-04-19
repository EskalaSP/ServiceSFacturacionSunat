# NRUS — Guía completa de integración

**NRUS** (Nuevo Régimen Único Simplificado) es un régimen tributario peruano para pequeños contribuyentes que pagan una cuota mensual fija en lugar de IGV y Renta.

Este documento explica:
1. [Qué es NRUS y a quién aplica](#1-qué-es-nrus)
2. [Cómo registrar una empresa NRUS en la API](#2-registrar-una-empresa-nrus)
3. [Cómo convertir una empresa existente a NRUS](#3-cambiar-a-nrus-después-del-registro)
4. [Ejemplos reales de operación](#4-ejemplos-reales-de-operación)
5. [Cómo emitir cada tipo de documento](#5-cómo-emitir-cada-documento)
6. [Errores comunes y cómo resolverlos](#6-errores-comunes)
7. [Diferencias vs régimen general](#7-diferencias-vs-régimen-general)

---

## 1. ¿Qué es NRUS?

Régimen para personas naturales/jurídicas con pequeña actividad comercial. En lugar de pagar IGV + Impuesto a la Renta, pagan una **cuota mensual única**:

| Categoría | Ingresos/compras mensuales | Cuota mensual |
|-----------|----------------------------|---------------|
| **1** | Hasta S/ 5,000 | **S/ 20** |
| **2** | Hasta S/ 8,000 | **S/ 50** |

### Actividades típicas en NRUS
- Bodegas, tiendas de barrio
- Food trucks, cevicherías pequeñas
- Peluquerías, barberías
- Fotocopiadoras, cabinas de internet
- Servicios técnicos por cuenta propia

### Obligaciones respecto a facturación electrónica
- ✅ Emiten **boletas de venta** (código 03) y tickets
- ✅ Emiten notas de crédito y débito **solo contra sus propias boletas**
- ❌ **NO pueden emitir facturas** (código 01) — está prohibido por ley
- ❌ **NO pueden emitir NC/ND contra facturas de otros**

### Sobre el IGV en los documentos
- El contribuyente NRUS **no cobra IGV** al cliente final
- Cada item se reporta con `tip_afe_igv = "30"` (Inafecto)
- El XML sale con `<cbc:Percent>0</cbc:Percent>` y `TaxExemptionReasonCode=30`
- El cliente final **no puede usar la boleta NRUS como crédito fiscal**

---

## 2. Registrar una empresa NRUS

### Datos obligatorios

Igual que cualquier empresa, **más dos campos específicos**:

| Campo | Valor | Obligatorio |
|-------|-------|-------------|
| `ruc` | 11 dígitos (típicamente inicia en `10` para persona natural) | ✅ |
| `razon_social` | Nombre completo o razón social | ✅ |
| `direccion` | Dirección fiscal | ✅ |
| `ubigeo` | 6 dígitos SUNAT (ej: `150101` Lima-Lima-Lima) | ✅ |
| `departamento`, `provincia`, `distrito` | Texto | Recomendado |
| `sol_user` | Usuario Clave SOL de SUNAT | ✅ |
| `sol_pass` | Contraseña Clave SOL | ✅ |
| `certificado` | Archivo `.pfx`/`.p12` del certificado digital | ✅ |
| `contrasena_certificado` | Password del `.pfx` | ✅ (si es pfx) |
| `entorno` | `beta` o `production` | Default `beta` |
| `plan` | `free`, `pro`, `business` | Default `free` |
| **`tax_regime`** | **`nrus`** | ✅ **para NRUS** |
| **`nrus_categoria`** | **`1` o `2`** | Recomendado |

### Ejemplo: Registrar una bodega NRUS categoría 1

```bash
curl -X POST https://tu-api.com/api/v1/registro \
  -F "ruc=10459876543" \
  -F "razon_social=BODEGA DON JUAN - JUAN PEREZ" \
  -F "nombre_comercial=Bodega Don Juan" \
  -F "direccion=JR. LAS FLORES 456" \
  -F "ubigeo=150122" \
  -F "departamento=Lima" \
  -F "provincia=Lima" \
  -F "distrito=San Juan de Lurigancho" \
  -F "sol_user=MODDATOS" \
  -F "sol_pass=MODDATOS" \
  -F "entorno=beta" \
  -F "plan=free" \
  -F "tax_regime=nrus" \
  -F "nrus_categoria=1" \
  -F "certificado=@/ruta/a/certificado.pfx" \
  -F "contrasena_certificado=mi_password_pfx"
```

### Respuesta

```json
{
  "estado": "exito",
  "mensaje": "Creado exitosamente",
  "datos": {
    "tenant_id": 15,
    "ruc": "10459876543",
    "razon_social": "BODEGA DON JUAN - JUAN PEREZ",
    "entorno": "beta",
    "plan": "free",
    "tax_regime": "nrus",
    "nrus_categoria": 1,
    "igv_rate_override": null,
    "api_key": "abc123...64chars",
    "api_secret": "xyz789...64chars",
    "importante": "Guarde sus credenciales. El api_secret NO se puede recuperar."
  }
}
```

> ⚠️ **Guarda `api_key` y `api_secret`** — el secret no se puede recuperar. Si se pierde, hay que regenerar credenciales vía administración.

### Notas sobre el RUC

- Personas naturales con negocio: RUC empieza en **`10`** (ej: `10459876543` = DNI 45987654 + dígito verificador)
- Personas jurídicas: RUC empieza en **`20`** (poco común en NRUS)
- NRUS **sí permite** RUCs tipo 20, aunque la mayoría son tipo 10

---

## 3. Cambiar a NRUS después del registro

Si registraste como `general` (18%) pero el cliente luego se acoge a NRUS:

```bash
curl -X PUT https://tu-api.com/api/v1/empresa \
  -H "X-Api-Key: $API_KEY" \
  -H "X-Api-Secret: $API_SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "tax_regime": "nrus",
    "nrus_categoria": 1
  }'
```

### Pasar de NRUS de vuelta a general (si dejó el régimen)

```bash
curl -X PUT https://tu-api.com/api/v1/empresa \
  -H "X-Api-Key: $API_KEY" \
  -H "X-Api-Secret: $API_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"tax_regime": "general"}'
```

La API **automáticamente** limpia `nrus_categoria` cuando cambias a otro régimen.

### Consultar el estado actual

```bash
curl https://tu-api.com/api/v1/empresa \
  -H "X-Api-Key: $API_KEY" \
  -H "X-Api-Secret: $API_SECRET"
```

Respuesta:
```json
{
  "tax_regime": "nrus",
  "nrus_categoria": 1,
  "igv_rate_override": null,
  "tasa_igv_vigente": 0,
  ...
}
```

`tasa_igv_vigente: 0` confirma que se aplicará 0% IGV.

---

## 4. Ejemplos reales de operación

### Escenario A: Pollería/Restaurante pequeño

**Contexto:** Restaurante popular, cliente se sienta a almorzar, pide menú, pregunta boleta al final.

```bash
curl -X POST https://tu-api.com/api/v1/boletas \
  -H "X-Api-Key: $KEY" -H "X-Api-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "serie": "B001",
    "fecha_emision": "2026-04-18",
    "cliente": {
      "tipo_doc": "0",
      "num_doc": "-",
      "razon_social": "CLIENTES VARIOS"
    },
    "items": [
      {
        "codigo": "POLLO-1/4",
        "descripcion": "1/4 POLLO A LA BRASA CON PAPAS",
        "unidad": "NIU",
        "cantidad": 2,
        "precio_unitario": 18.50
      },
      {
        "codigo": "INCA-500",
        "descripcion": "INCA KOLA 500ML",
        "unidad": "NIU",
        "cantidad": 2,
        "precio_unitario": 4.00
      },
      {
        "codigo": "TORTA-QUE",
        "descripcion": "TORTA DE QUESO",
        "unidad": "NIU",
        "cantidad": 1,
        "precio_unitario": 8.00
      }
    ]
  }'
```

**Respuesta exitosa:**
```json
{
  "estado": "exito",
  "mensaje": "Boleta creada y encolada para envío a SUNAT.",
  "datos": {
    "numero_completo": "B001-001509",
    "tipo_operacion": "0113",
    "totales": {
      "inafectas": 53.00,
      "total_impuestos": 0,
      "valor_venta": 53.00,
      "sub_total": 53.00,
      "total": 53.00
    }
  }
}
```

**Lo que hace la API automáticamente:**
- `tipo_operacion: "0113"` — Venta Interna NRUS (Cat. 51)
- Cada item recibe `tip_afe_igv: "30"` (Inafecto)
- Los S/ 53.00 se acumulan en `mto_oper_inafectas` (NO en gravadas)
- `mto_igv: 0`
- El XML sale sin bloque `<TaxTotal>` de IGV
- SUNAT lo recibe y acepta con code 0

---

### Escenario B: Barbería/Peluquería

**Contexto:** Cliente pide corte + afeitado, paga al salir.

```bash
curl -X POST https://tu-api.com/api/v1/boletas \
  -H "X-Api-Key: $KEY" -H "X-Api-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "serie": "B001",
    "fecha_emision": "2026-04-18",
    "cliente": {
      "tipo_doc": "0",
      "num_doc": "-",
      "razon_social": "CLIENTES VARIOS"
    },
    "items": [
      {
        "codigo": "CORTE",
        "descripcion": "CORTE DE CABELLO CABALLERO",
        "unidad": "ZZ",
        "cantidad": 1,
        "precio_unitario": 20.00
      },
      {
        "codigo": "AFEITADO",
        "descripcion": "AFEITADO CLASICO",
        "unidad": "ZZ",
        "cantidad": 1,
        "precio_unitario": 15.00
      }
    ]
  }'
```

> **Unidad `ZZ`** = "Servicios" en catálogo SUNAT. Usa `NIU` para productos físicos.

---

### Escenario C: Bodega vendiendo productos

**Contexto:** Cliente compra varios productos, su consumo pasa de S/ 700 — boleta con datos del cliente.

```bash
curl -X POST https://tu-api.com/api/v1/boletas \
  -H "X-Api-Key: $KEY" -H "X-Api-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "serie": "B001",
    "fecha_emision": "2026-04-18",
    "cliente": {
      "tipo_doc": "1",
      "num_doc": "46583291",
      "razon_social": "MARIA LOPEZ QUISPE",
      "direccion": "AV. TUPAC AMARU 123",
      "email": "maria.lopez@gmail.com"
    },
    "items": [
      {"codigo":"ARROZ-5K","descripcion":"ARROZ SUPERIOR 5KG","unidad":"NIU","cantidad":2,"precio_unitario":28.00},
      {"codigo":"ACEITE-1L","descripcion":"ACEITE VEGETAL 1L","unidad":"NIU","cantidad":1,"precio_unitario":9.50},
      {"codigo":"AZUCAR-2K","descripcion":"AZUCAR RUBIA 2KG","unidad":"NIU","cantidad":3,"precio_unitario":7.00},
      {"codigo":"LECHE-EVA","descripcion":"LECHE EVAPORADA 410G","unidad":"NIU","cantidad":6,"precio_unitario":5.20},
      {"codigo":"DETERGENT","descripcion":"DETERGENTE EN POLVO 2KG","unidad":"NIU","cantidad":2,"precio_unitario":24.90}
    ]
  }'
```

Total: S/ 2× 28 + 9.50 + 3× 7 + 6× 5.20 + 2× 24.90 = **S/ 166.60**

> ⚠️ **IMPORTANTE — SUNAT obliga identificar al cliente cuando el total supera S/ 700**. Si el total es ≤ S/ 700, puedes usar `tipo_doc: "0"`, `num_doc: "-"`, `razon_social: "CLIENTES VARIOS"`.

---

### Escenario D: Anulación de una boleta emitida por error

Si emites B001-001509 por error (cliente devolvió, cancelación, etc.), emites una **nota de crédito** contra ella:

```bash
curl -X POST https://tu-api.com/api/v1/notas-credito \
  -H "X-Api-Key: $KEY" -H "X-Api-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "serie": "BC01",
    "fecha_emision": "2026-04-18",
    "doc_afectado_tipo": "03",
    "doc_afectado_serie": "B001",
    "doc_afectado_correlativo": "1509",
    "cod_motivo": "01",
    "des_motivo": "Anulacion por error de digitacion",
    "cliente": {
      "tipo_doc": "1",
      "num_doc": "46583291",
      "razon_social": "MARIA LOPEZ QUISPE"
    },
    "items": [
      {"descripcion":"1/4 POLLO A LA BRASA","unidad":"NIU","cantidad":2,"precio_unitario":18.50},
      {"descripcion":"INCA KOLA 500ML","unidad":"NIU","cantidad":2,"precio_unitario":4.00},
      {"descripcion":"TORTA DE QUESO","unidad":"NIU","cantidad":1,"precio_unitario":8.00}
    ]
  }'
```

**Importante:**
- `doc_afectado_tipo: "03"` → **obligatorio 03** para NRUS (boleta). Si intentas `"01"` (factura), la API rechaza con HTTP 422.
- `cod_motivo: "01"` → Anulación de la operación (Cat. 09). Otros motivos comunes:
  - `02` Anulación por error en el RUC/DNI
  - `03` Corrección por error en la descripción
  - `06` Devolución total/parcial
- La serie recomendada para NC-de-boletas es `BC01` (prefijo B = boleta). Si la serie no existe, créala desde el panel.

---

### Escenario E: Food truck / venta ambulatoria

```bash
curl -X POST https://tu-api.com/api/v1/boletas \
  -H "X-Api-Key: $KEY" -H "X-Api-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "serie": "B001",
    "fecha_emision": "2026-04-18",
    "cliente": {"tipo_doc":"0","num_doc":"-","razon_social":"CLIENTES VARIOS"},
    "items": [
      {"descripcion":"ANTICUCHO CLASICO (2U)","unidad":"NIU","cantidad":1,"precio_unitario":15.00},
      {"descripcion":"CHICHA MORADA 500ML","unidad":"NIU","cantidad":1,"precio_unitario":5.00}
    ]
  }'
```

**Consejo práctico:** muchos clientes NRUS emiten cientos de boletas al día con cliente anónimo. Puedes emitirlas con `"solo_registro": true` para guardarlas localmente y enviar todas al final del día vía **Resumen Diario** (más rápido, menos llamadas a SUNAT).

```bash
curl -X POST https://tu-api.com/api/v1/boletas \
  -H "X-Api-Key: $KEY" -H "X-Api-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "serie": "B001",
    "fecha_emision": "2026-04-18",
    "solo_registro": true,
    "cliente": {"tipo_doc":"0","num_doc":"-","razon_social":"CLIENTES VARIOS"},
    "items": [{"descripcion":"ANTICUCHO","unidad":"NIU","cantidad":1,"precio_unitario":15}]
  }'
```

Luego al cierre del día:

```bash
curl -X POST https://tu-api.com/api/v1/resumenes \
  -H "X-Api-Key: $KEY" -H "X-Api-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{"fecha_resumen":"2026-04-18"}'
```

SUNAT recibe todas las boletas pendientes del día en una sola transacción. Ver [`08-Resumen-diario.md`](./08-Resumen-diario.md) para detalles.

---

## 5. Cómo emitir cada documento

| Documento | Endpoint | NRUS permitido | Defaults aplicados |
|-----------|----------|----------------|---------------------|
| Boleta | `POST /api/v1/boletas` | ✅ | `tipo_operacion=0113`, `tip_afe_igv=30` |
| Factura | `POST /api/v1/facturas` | ❌ HTTP 422 | — |
| Nota de crédito (contra boleta) | `POST /api/v1/notas-credito` con `doc_afectado_tipo=03` | ✅ | `tip_afe_igv=30` |
| Nota de crédito (contra factura) | `POST /api/v1/notas-credito` con `doc_afectado_tipo=01` | ❌ HTTP 422 | — |
| Nota de débito (contra boleta) | `POST /api/v1/notas-debito` con `doc_afectado_tipo=03` | ✅ | `tip_afe_igv=30` |
| Nota de débito (contra factura) | `POST /api/v1/notas-debito` con `doc_afectado_tipo=01` | ❌ HTTP 422 | — |
| Guía de remisión | `POST /api/v1/guias-remision` | ✅ | — |
| Resumen diario | `POST /api/v1/resumenes` | ✅ | — |

---

## 6. Errores comunes

### ❌ "Los contribuyentes del régimen NRUS solo pueden emitir boletas de venta, no facturas"

**Causa:** Intentaste `POST /api/v1/facturas` con un tenant NRUS.

**Solución:** Usa `POST /api/v1/boletas`. Si necesitas facturar (para un cliente empresa que exige factura con IGV), el cliente NRUS debe cambiar de régimen antes.

---

### ❌ "Los contribuyentes del régimen NRUS solo pueden emitir notas de crédito contra boletas de venta (tipo 03)"

**Causa:** Enviaste `doc_afectado_tipo: "01"` (factura).

**Solución:** Cambia a `doc_afectado_tipo: "03"` y asegúrate de que la boleta de referencia existe.

---

### ❌ SUNAT rechaza con código 2020: "Tipo de afectación al IGV no corresponde al tipo de operación"

**Causa:** El cliente envió `tip_afe_igv: "10"` (Gravado) pero `tipo_operacion: "0113"` (NRUS). SUNAT valida coherencia.

**Solución:** No envíes `tip_afe_igv` — la API lo resuelve sola. O si lo envías explícito, usa `"30"`.

---

### ❌ Boletas con total > S/ 700 y cliente genérico

**Causa:** SUNAT obliga identificar al cliente cuando el monto supera S/ 700.

**Solución:** Pide DNI/RUC al cliente:
```json
"cliente": {
  "tipo_doc": "1",
  "num_doc": "12345678",
  "razon_social": "JUAN PEREZ LOPEZ"
}
```

---

### ❌ Cambié `tax_regime` pero los documentos siguen con 18%

**Causa:** OPcache del servidor web.

**Solución:**
```bash
php artisan cache:clear
php artisan config:clear
```
O reiniciar Apache/PHP-FPM.

---

## 7. Diferencias vs régimen general

| Aspecto | General | NRUS |
|---------|---------|------|
| Paga IGV mensual | Sí (según ventas) | No — cuota fija S/ 20 o 50 |
| Presenta declaraciones | PDT 621, PLE, etc. | Solo Formulario 1611 (cuota) |
| Emite facturas | ✅ | ❌ |
| Emite boletas | ✅ | ✅ |
| NC/ND contra factura | ✅ | ❌ |
| NC/ND contra boleta | ✅ | ✅ |
| Cliente usa boleta como crédito fiscal | No (boleta nunca da crédito) | No |
| Cliente usa factura como crédito fiscal | ✅ | N/A (NRUS no emite factura) |
| Tasa IGV en XML | 18% en `tip_afe_igv=10` | 0% en `tip_afe_igv=30` |
| `tipo_operacion` | `0101` | `0113` |
| Límite ingresos anuales | Sin límite | S/ 96,000 (Cat. 2) |
| Libros contables | Obligatorio | No requiere |

---

## 8. Campos útiles del endpoint `GET /api/v1/empresa`

Siempre puedes consultar cómo está configurado tu tenant:

```json
{
  "ruc": "10459876543",
  "razon_social": "BODEGA DON JUAN - JUAN PEREZ",
  "tax_regime": "nrus",
  "nrus_categoria": 1,
  "igv_rate_override": null,
  "tasa_igv_vigente": 0,
  "plan": "free",
  "entorno": "beta"
}
```

- `tasa_igv_vigente: 0` confirma el régimen NRUS activo.
- `igv_rate_override: null` indica que no hay tasa manual forzada.

---

## 9. Pruebas end-to-end confirmadas (2026-04-18)

Realicé pruebas reales contra SUNAT beta con el tenant `20161515648`:

| # | Escenario | Resultado |
|---|-----------|-----------|
| 1 | Activar `tax_regime=nrus, nrus_categoria=1` via `PUT /empresa` | ✅ `tasa_igv_vigente: 0%` |
| 2 | Emitir boleta sin `tip_afe_igv` (1 menú S/12) | ✅ **B001-001508 aceptada** por SUNAT (code 0) |
| 3 | Emitir boleta con 3 items variados (pollo+gaseosa+postre S/53) | ✅ **B001-001509 aceptada** (code 0) |
| 4 | Emitir boleta con `tip_afe_igv=30` explícito (S/22) | ✅ **B001-001510 aceptada** (code 0) |
| 5 | Intentar emitir factura | ❌ HTTP 422 bloqueada |
| 6 | Intentar NC contra factura | ❌ HTTP 422 bloqueada |
| 7 | Intentar ND contra factura | ❌ HTTP 422 bloqueada |
| 8 | Descargar PDF A4 de B001-001509 | ✅ 200 OK (57 KB) |
| 9 | Descargar PDF ticket-80mm | ✅ 200 OK (54 KB) |
| 10 | Descargar XML firmado | ✅ 200 OK (9 KB) |
| 11 | Descargar CDR oficial SUNAT | ✅ ResponseCode 0 — aceptada |

**Fragmento del XML real aceptado por SUNAT (B001-001509):**
```xml
<cbc:InvoiceTypeCode listID="0113">03</cbc:InvoiceTypeCode>
...
<cac:InvoiceLine>
  <cac:TaxCategory>
    <cbc:Percent>0</cbc:Percent>
    <cbc:TaxExemptionReasonCode>30</cbc:TaxExemptionReasonCode>
    <cac:TaxScheme>
      <cbc:ID>9998</cbc:ID>
      <cbc:Name>INA</cbc:Name>
      <cbc:TaxTypeCode>FRE</cbc:TaxTypeCode>
    </cac:TaxScheme>
  </cac:TaxCategory>
</cac:InvoiceLine>
```

**CDR oficial de SUNAT:**
```xml
<cbc:ReferenceID>B001-1509</cbc:ReferenceID>
<cbc:ResponseCode>0</cbc:ResponseCode>
<cbc:Description>La Boleta numero B001-1509, ha sido aceptada</cbc:Description>
```

---

## 10. Referencias cruzadas

- Para otros regímenes (general, MYPE restaurantes): [`02-Tasas-IGV.md`](./02-Tasas-IGV.md)
- Envío de boletas en lote al cierre del día: [`08-Resumen-diario.md`](./08-Resumen-diario.md)
- Configurar series y sucursales: [`01-Configuracion.md`](./01-Configuracion.md)
- Anular documentos: [`09-Anular.md`](./09-Anular.md), [`06-Notas-credito.md`](./06-Notas-credito.md)

## 11. Archivos técnicos de la implementación

| Archivo | Función |
|---------|---------|
| `app/Services/TaxRateService.php` | `REGIMEN_NRUS`, `isNrus()`, `defaultTipAfeIgv()` |
| `app/Services/DocumentCalculationService.php` | Auto-default `tip_afe_igv=30` |
| `app/Services/Greenter/Builders/InvoiceBuilder.php` | Aplica default en XML de boleta |
| `app/Services/Greenter/Builders/NoteBuilder.php` | Aplica default en XML de NC/ND |
| `app/Actions/Documents/CreateBoletaAction.php` | Auto `tipo_operacion=0113` |
| `app/Http/Requests/Api/V1/StoreInvoiceRequest.php` | Bloqueo de facturas |
| `app/Http/Requests/Api/V1/StoreCreditNoteRequest.php` | Bloqueo NC contra factura |
| `app/Http/Requests/Api/V1/StoreDebitNoteRequest.php` | Bloqueo ND contra factura |
| `database/migrations/2026_04_18_130000_add_nrus_categoria_to_tenants.php` | Columna `nrus_categoria` |
| `tests/Unit/TaxRateServiceTest.php` | 5 tests específicos NRUS (13 totales) |

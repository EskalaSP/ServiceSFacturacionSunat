# Conceptos SUNAT / Perú indispensables

Contexto mínimo que Claude necesita para no cometer errores de dominio.

---

## 1. Identificación de personas/empresas

| Tipo | Longitud | Uso | Cat. 06 |
|---|---|---|---|
| **RUC** | 11 dígitos | Empresas + naturales con negocio | `6` |
| **DNI** | 8 dígitos | Personas naturales (peruanos) | `1` |
| **Carnet Extranjería (CE)** | 9-12 dígitos | Extranjeros residentes | `4` |
| **Pasaporte** | variable | Extranjeros no residentes | `7` |
| **Otros** | — | Consumidor final sin doc | `0` |

**Regla SUNAT**:
- **Factura**: el cliente DEBE tener RUC (tipo 6) que empiece con `10`, `15`, `17` o `20`
- **Boleta**: puede ser DNI, Otros (hasta S/ 700 no requiere DNI, usar `0` + `"00000000"`), o cualquiera

**RUCs válidos para pruebas beta**: `20000000001`, `20123456789`, `20512345678`

---

## 2. Tipos de comprobante (Cat. 01)

| Código | Nombre | Uso |
|---|---|---|
| `01` | **Factura** | B2B, cliente con RUC |
| `03` | **Boleta** | B2C, cliente con DNI o consumidor final |
| `07` | **Nota de Crédito** | Anular/devolver/descontar un comprobante |
| `08` | **Nota de Débito** | Intereses, penalidades, aumentos |
| `09` | **Guía de Remisión Remitente (GRR)** | Quien envía la mercancía |
| `31` | **Guía de Remisión Transportista (GRT)** | Quien transporta mercancía de terceros |
| `20` | **Retención** | Agente de retención (retiene 3% al proveedor) |
| `40` | **Percepción** | Agente de percepción (percibe 0.5%-2% al cliente) |
| `RC` | **Resumen Diario** | Envío en lote de boletas |
| `RA` | **Comunicación de Baja** | Anular factura/NC/ND aceptadas |
| `RR` | **Reversión** | Anular retenciones/percepciones aceptadas |

---

## 3. Regímenes tributarios

La empresa (tenant) tiene un `tax_regime` que controla cómo se calcula el IGV:

| `tax_regime` | Tasa IGV | Características | Emite |
|---|---|---|---|
| `general` | 18% | Régimen estándar | Todo |
| `mype_restaurantes` | 10.5% (2026-2029) o 18% | Ley 31556 para restaurantes/hoteles | Todo |
| `nrus` | **0%** | Nuevo RUS — paga cuota mensual fija (S/20 o S/50) | **Solo boletas** |

**NRUS**: si el tenant tiene `tax_regime=nrus`:
- La API **bloquea** emisión de facturas, NC/ND
- `tipo_operacion` se setea automáticamente a `0113` (Venta interna NRUS)
- IGV siempre 0
- `nrus_categoria`: `"1"` (S/20 mensual, hasta S/5k ventas) o `"2"` (S/50, hasta S/8k)

---

## 4. `tipo_afe_igv` — Afectación IGV por ítem (Cat. 07)

Cada ítem lleva un código que dice cómo lo afecta el IGV:

| Código | Nombre | Aplica IGV |
|---|---|---|
| `10` | Gravado — operación onerosa | ✅ 18% |
| `11` | Gravado — retiro por premio | 18% (no cobrado al cliente) |
| `12` | Gravado — retiro por donación | 18% (no cobrado) |
| `20` | **Exonerado** — operación onerosa | ❌ (servicios médicos, educación) |
| `30` | **Inafecto** — operación onerosa | ❌ (exportación de servicios) |
| `40` | **Exportación** de bienes o servicios | ❌ (tasa 0%) |

**Default**: `10` (gravado estándar)

---

## 5. `tipo_operacion` — Tipo de venta (Cat. 51)

| Código | Descripción |
|---|---|
| `0101` | Venta interna (default) |
| `0113` | Venta interna — NRUS (auto si régimen NRUS) |
| `0200` | Exportación de bienes |
| `0300` | Exportación de servicios |
| `2001` | Operación sujeta a detracción |
| `2100` | Operación sujeta a percepción |

---

## 6. Series y correlativos

**Formato serie**: 4 caracteres, empieza con letra, luego 3 alfanuméricos.

| Tipo | Prefijos válidos | Ejemplo |
|---|---|---|
| Factura (01) | `F` | `F001` |
| Boleta (03) | `B` | `B001` |
| Nota Crédito (07) | `F`, `B` (según doc afectado) | `FC01` |
| Nota Débito (08) | `F`, `B` | `FD01` |
| GRR (09) | `T`, `V` | `T001` |
| GRT (31) | `V` | `V001` |
| Retención (20) | `R` | `R001` |
| Percepción (40) | `P` | `P001` |

**Correlativo**: auto-incremental por serie. El endpoint de crear NO lo recibe — la API lo asigna.

**Para empezar desde un número específico** (ej. migrando de otro proveedor): `POST /series` con `correlativo_inicial: 499` → el próximo doc será correlativo 500.

---

## 7. Flujo asíncrono SUNAT

SUNAT **no responde inmediato** cuando se envía un comprobante. El flujo es:

```
Cliente → API-PRO → BD: status=pendiente
API-PRO → Job en cola: status=enviado
Job ejecuta (segundos/minutos) → SUNAT
SUNAT responde → status=aceptado | rechazado
API-PRO guarda XML + CDR + hash_cpe
API-PRO dispara webhook (si tenant configuró webhook_url)
```

**No bloquees tu UI esperando `aceptado`**. Estrategias:
1. Webhook → recibes `document.sent` / `document.rejected`
2. Polling → `GET /facturas/{id}` cada N segundos hasta ver `sunat.estado === 'aceptado'`
3. UI optimista → muestra "enviando..." y actualiza cuando llegue evento

---

## 8. Códigos SUNAT (resultado del CDR)

El CDR (Constancia de Recepción) de SUNAT trae un código numérico:

| Rango | Significado |
|---|---|
| `0` | ✅ Aceptado sin observaciones |
| `100`-`1999` | ⚠️ Excepción servidor SUNAT (reintentar: 100, 109, 500, 1033, 2800) |
| `2000`-`3999` | ❌ **Rechazado** — error permanente de validación |
| `4000`+ | ✅ Aceptado con observación (no bloquea, solo advertencia) |

**Críticos comunes**:
- `2017` — Datos del cliente no coinciden con SUNAT
- `2335` — El XML no cumple con formato
- `2800` — Correlativo duplicado (la API maneja con lock + reintenta)
- `3208` — Total no coincide con sumatoria
- `4331` — El CPE fue comunicado previamente (ya existe en SUNAT)

---

## 9. Catálogos SUNAT relevantes

| Cat. | Uso | Valores clave |
|---|---|---|
| `01` | Tipo comprobante | `01`, `03`, `07`, `08`, `09`, `31`, `20`, `40` |
| `06` | Tipo doc identidad | `0`, `1`, `4`, `6`, `7` |
| `07` | Afectación IGV | `10`, `11`, `12`, `20`, `30`, `40` |
| `09` | Motivo NC | `01`=anulación, `06`=devolución total, `07`=devolución parcial |
| `10` | Motivo ND | `01`=intereses mora, `02`=aumento valor, `03`=penalidad |
| `20` | Motivo traslado GRE | `01`=venta, `02`=compra, `04`=traslado entre establecimientos |
| `51` | Tipo operación | `0101`, `0113`, `0200` |

Full en `config/sunat_catalogs.php` del proyecto API.

---

## 10. Unidades de medida (Cat. 03)

Las más usadas:

| Código | Descripción |
|---|---|
| `NIU` | Unidad (default) |
| `ZZ` | Servicio |
| `KGM` | Kilogramo |
| `MTR` | Metro |
| `LTR` | Litro |
| `H87` | Pieza |
| `BG` | Bolsa |
| `CA` | Lata |

---

## 11. Guía de Remisión — GRR vs GRT

| | **GRR (Remitente)** | **GRT (Transportista)** |
|---|---|---|
| Tipo | 09 | 31 |
| Quién emite | El dueño de la mercancía | La empresa transportista |
| Remite campo `remitente`? | No (es el tenant) | **SÍ (obligatorio)** — el cliente del transportista |
| Requiere `doc_relacionado`? | No | **SÍ** — factura/GRR que origina el transporte |
| Serie ejemplo | T001 | V001 |

**Importante**: GRT funciona SOLO en producción de SUNAT. La beta no lo valida bien. Cuando emitas GRT con `entorno=beta`, la respuesta incluye `advertencias: [{codigo: "grt_beta_no_soportado", ...}]` — mostralo en UI para que el usuario sepa que la validación no es 100% real.

---

## 12. Webhooks (opcional pero recomendado)

Si el tenant configura `webhook_url` en su empresa, la API envía POST:

```http
POST https://tu-app.com/sunat-webhook
Content-Type: application/json

{
  "event": "document.sent" | "document.rejected",
  "tenant_id": 15,
  "model": "Invoice" | "Boleta" | "CreditNote" | ...,
  "id": 123,
  "data": {
    "numero": "F001-123",
    "sunat_status": "aceptado",
    "sunat_code": "0",
    "hash_cpe": "..."
  }
}
```

**Respuesta esperada**: 200 OK. Timeouts o 5xx hacen que el job reintente.

---

## 13. Certificado digital — obligatorio

Cada empresa necesita un **certificado .pfx** (o .p12, .pem) para firmar XMLs:

- En **beta SUNAT**: usar el cert de prueba + `sol_user=MODDATOS` / `sol_pass=MODDATOS`
- En **producción**: certificado real comprado en Certicámara / LLAMA.PE / otra CA autorizada + credenciales SOL reales

Sube via `POST /empresa/certificado` con `multipart/form-data`:
```
certificado: <file>
contrasena_certificado: <password>
```

---

## 14. Modos `entorno`

En `POST /registro` o `PUT /empresa`:

- `entorno: "beta"` → apunta a SUNAT beta (pruebas). Los XML dicen `BETA` y no son legales.
- `entorno: "production"` → SUNAT producción real. Los XML son legales y facturados.

**Siempre empieza en `beta`**, valida la integración, luego cambia.

---

## 15. Cosas que NO puedes hacer

Estas reglas las **valida la API antes** de enviar a SUNAT — devuelve 422 con `codigo_error` + `siguiente_accion` (ver `RESPONSE-FORMAT.md`), no te deja pegarte contra SUNAT innecesariamente:

- ❌ Emitir factura con `cliente.tipo_doc` distinto de `6` (RUC) → 422 sugiriendo `POST /boletas`
- ❌ Modificar una factura/boleta **aceptada** → 422 `codigo_error=documento_aceptado_no_editable`, `siguiente_accion` con endpoint de NC + `doc_afectado`
- ❌ Modificar una NC/ND **aceptada** → 422 con `siguiente_accion` apuntando a `POST /anulaciones`
- ❌ Anular boleta con comunicación de baja → 422 `codigo_error=boletas_no_soportadas_en_ra`, `siguiente_accion` apunta a `POST /resumenes` con `{"anular":[...]}`
- ❌ Anular comprobante con más de **7 días** desde emisión (validado en `VoidedController`)
- ❌ Emitir NC contra factura si el tenant es NRUS (NRUS no emite facturas ni NC)
- ❌ Repetir correlativo en la misma serie (la API lo previene con lock, pero si pasa → código 2800)

**Regla del cliente**: cuando veas `siguiente_accion`, no muestres el mensaje seco — renderízalo como CTA (botón "Emitir Nota de Crédito", "Anular por Resumen Diario", etc.) porque el body ya trae los datos del doc afectado listos para el siguiente request.

# Guía de Remisión Transportista (GRT) — Tipo 31

La **Guía de Remisión Transportista** es el documento electrónico que emite la **empresa de transporte** cuando traslada mercadería por encargo de un remitente (con o sin contrato de transporte).

Es distinta a la [**Guía de Remisión Remitente (GRR)**](./10-Guia-remision-RM.md), que emite el propio remitente cuando traslada sus propios bienes (o contrata un transporte público que se reporta en la misma GRR).

---

## 1. Cuándo emitir GRT vs GRR

| Situación | Documento |
|-----------|-----------|
| Empresa envía sus propios bienes en vehículo propio | **GRR** (tipo 09) |
| Empresa envía bienes contratando transportista público | **GRR** (tipo 09) con datos del transportista |
| Empresa de transporte recibe carga para llevarla a un destino | **GRT** (tipo 31) |
| Empresa de transporte subcontrata a otra para el traslado | **GRT** con subcontratador informado |

> ⚠️ Una misma operación puede generar **ambas guías**: la empresa dueña de la carga emite GRR, y el transportista emite GRT apuntando a esa GRR como documento relacionado.

---

## 2. Datos obligatorios para GRT

| Categoría | Campo | Obligatorio | Notas |
|-----------|-------|-------------|-------|
| Header | `tipo_documento` | **`"31"`** | Forzado automáticamente al usar `POST /guias-remision-transportista` |
| Header | `serie` | ✅ | Formato `V[A-Z0-9]{3}` (ej: `V001`) |
| Header | `fecha_emision` | ✅ | `YYYY-MM-DD` |
| Emisor | — | ✅ | **Es el tenant** (empresa de transporte que emite) |
| Remitente | `remitente.tipo_doc` | ✅ | `1` DNI / `6` RUC |
| Remitente | `remitente.num_doc` | ✅ | Quien manda la carga. NO puede ser el tenant |
| Remitente | `remitente.razon_social` | ✅ | |
| Destinatario | `destinatario.*` | ✅ | Quien recibe la carga |
| Doc relacionado | `doc_relacionado` | **✅ OBLIGATORIO** | Al menos un doc (factura, boleta, DAM, GRR, etc.) |
| Traslado | `cod_traslado` | ✅ | Cat. 20 (01=Venta, 02=Compra, 04=Traslado entre establec., etc.) |
| Traslado | `mod_traslado` | ✅ | `01`=público / `02`=privado |
| Traslado | `fecha_traslado` | ✅ | Debe ser ≥ `fecha_emision` |
| Traslado | `peso_total` + `und_peso_total` | ✅ | KGM o TNE |
| Direcciones | `partida_ubigeo`, `partida_direccion` | ✅ | Ubigeo SUNAT (6 dígitos) |
| Direcciones | `llegada_ubigeo`, `llegada_direccion` | ✅ | |
| Vehículo | `vehiculo.placa` | ✅ | 6-8 caracteres alfanuméricos |
| Conductor | `conductor.*` | ✅ | Principal. Licencia, nombres, apellidos, DNI |
| Items | `items[]` | ✅ | Al menos uno con descripción, cantidad, unidad |

---

## 3. Registrar una empresa de transporte (tenant transportista)

Se registra igual que cualquier otra empresa:

```bash
curl -X POST https://tu-api.com/api/v1/registro \
  -F "ruc=20100200300" \
  -F "razon_social=TRANSPORTES RAPIDOS SAC" \
  -F "nombre_comercial=Transportes Rápidos" \
  -F "direccion=AV. TRANSPORTE 500" \
  -F "ubigeo=150101" \
  -F "sol_user=MODDATOS" \
  -F "sol_pass=MODDATOS" \
  -F "entorno=beta" \
  -F "plan=business" \
  -F "client_id=$GRE_CLIENT_ID" \
  -F "client_secret=$GRE_CLIENT_SECRET" \
  -F "certificado=@cert.pfx" \
  -F "contrasena_certificado=secret"
```

> 🔑 **Credenciales GRE (OAuth2)**: para emitir GRE (tanto GRR como GRT), el tenant necesita `client_id` y `client_secret` de SUNAT GRE — son distintos a la Clave SOL. Se obtienen en SUNAT → Clave SOL → **Servicios al Contribuyente** → **API SUNAT**.

---

## 4. Crear serie GRT

Antes de emitir, crea una serie con prefijo `V` (obligatorio para tipo 31):

```bash
curl -X POST https://tu-api.com/api/v1/series \
  -H "X-Api-Key: $KEY" -H "X-Api-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "tipo": "guia_transportista",
    "serie": "V001",
    "correlativo_inicial": 0
  }'
```

---

## 5. Emitir GRT — ejemplos reales

### Ejemplo A — Transporte público de una factura (LIMA → AREQUIPA)

```bash
curl -X POST https://tu-api.com/api/v1/guias-remision-transportista \
  -H "X-Api-Key: $KEY" -H "X-Api-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "serie": "V001",
    "fecha_emision": "2026-04-18",
    "observacion": "Traslado de carga por pedido del cliente",

    "remitente": {
      "tipo_doc": "6",
      "num_doc": "20555666777",
      "razon_social": "DISTRIBUIDORA EL SOL SAC"
    },

    "destinatario": {
      "tipo_doc": "6",
      "num_doc": "20123456789",
      "razon_social": "COMERCIAL AREQUIPA SAC"
    },

    "doc_relacionado": {
      "tipo_codigo": "01",
      "numero": "F001-100",
      "tipo_descripcion": "FACTURA",
      "ruc_emisor": "20555666777"
    },

    "cod_traslado": "01",
    "mod_traslado": "01",
    "fecha_traslado": "2026-04-19",
    "peso_total": 150.50,
    "und_peso_total": "KGM",
    "num_bultos": 5,

    "partida_ubigeo": "150101",
    "partida_direccion": "AV. PROCERES 100 - LIMA",
    "llegada_ubigeo": "040101",
    "llegada_direccion": "AV. EJERCITO 500 - AREQUIPA",

    "vehiculo": {
      "placa": "ABC123",
      "nro_circulacion": "TUC1234567890"
    },

    "conductor": {
      "tipo_doc": "1",
      "num_doc": "45678901",
      "tipo": "Principal",
      "nombres": "JUAN CARLOS",
      "apellidos": "PEREZ LOPEZ",
      "licencia": "Q45678901"
    },

    "items": [
      {
        "descripcion": "MERCADERIA VARIA SEGUN FACTURA F001-100",
        "cantidad": 5,
        "unidad": "NIU",
        "codigo": "REF-001"
      }
    ]
  }'
```

**Respuesta esperada:**

```json
{
  "estado": "exito",
  "mensaje": "Guía de Remisión Transportista creada y encolada para envío a SUNAT.",
  "datos": {
    "numero_completo": "V001-000001",
    "tipo_documento": "31",
    "sunat_status": "enviado",
    "ticket": "20260418000000001"
  }
}
```

---

### Ejemplo B — Transporte subcontratado (un transportista contrata a otro)

Escenario: **TRANSPORTES RAPIDOS SAC** (el tenant) recibe carga pero subcontrata a **LOGÍSTICA EXPRESS SAC** para hacer el traslado. El pagador del flete es el **remitente**.

```bash
curl -X POST https://tu-api.com/api/v1/guias-remision-transportista \
  -H "X-Api-Key: $KEY" -H "X-Api-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "serie": "V001",
    "fecha_emision": "2026-04-18",
    "remitente": {"tipo_doc":"6","num_doc":"20555666777","razon_social":"DISTRIBUIDORA EL SOL SAC"},
    "destinatario": {"tipo_doc":"6","num_doc":"20123456789","razon_social":"COMERCIAL AREQUIPA SAC"},
    "doc_relacionado": {"tipo_codigo":"01","numero":"F001-250","tipo_descripcion":"FACTURA","ruc_emisor":"20555666777"},
    "cod_traslado": "01",
    "mod_traslado": "01",
    "fecha_traslado": "2026-04-19",
    "peso_total": 300.00,
    "und_peso_total": "KGM",
    "partida_ubigeo": "150101",
    "partida_direccion": "AV. LIMA 100",
    "llegada_ubigeo": "040101",
    "llegada_direccion": "AV. AREQUIPA 200",

    "datos_subcontratador": {
      "num_doc": "20999888111",
      "razon_social": "LOGISTICA EXPRESS SAC"
    },
    "datos_pagador_flete": {
      "tipo": "remitente"
    },

    "vehiculo": { "placa": "XYZ789" },
    "conductor": {
      "tipo_doc": "1","num_doc":"87654321","tipo":"Principal",
      "nombres":"MARIA","apellidos":"GOMEZ","licencia":"Q87654321"
    },
    "items": [{"descripcion":"CARGA GENERAL","cantidad":20,"unidad":"NIU","codigo":"CG-01"}]
  }'
```

La API automáticamente agrega los indicadores SUNAT correspondientes:
- `SUNAT_Envio_IndicadorTrasporteSubcontratado`
- `SUNAT_Envio_IndicadorPagadorFlete_Remitente`

---

### Ejemplo C — Tercero paga el flete

Si un **tercero distinto** al remitente/subcontratador paga el flete (por ejemplo, el comprador pide que el envío se cobre a su cuenta):

```json
{
  "datos_pagador_flete": {
    "tipo": "tercero",
    "tipo_doc": "6",
    "num_doc": "20777888999",
    "razon_social": "COMPRADOR QUE PAGA SAC"
  }
}
```

La API valida que si `tipo="tercero"`, vengan `num_doc` y `razon_social`.

---

### Ejemplo D — Múltiples documentos relacionados (hasta 2 con tipos 31/65/66/67/68/69)

Un solo traslado puede consolidar múltiples facturas o referencias:

```json
{
  "doc_relacionado": [
    {"tipo_codigo": "01", "numero": "F001-100", "tipo_descripcion": "FACTURA", "ruc_emisor": "20555666777"},
    {"tipo_codigo": "09", "numero": "T001-50", "tipo_descripcion": "GUIA REMITENTE", "ruc_emisor": "20555666777"}
  ]
}
```

**Restricciones SUNAT:**
- Máximo 2 documentos si alguno es tipo `31, 65, 66, 67, 68, 69`
- Más de 2 solo permitido si uno es tipo `09` con serie electrónica

---

### Ejemplo E — Transporte de DAM (importación)

Para transportar mercadería recién nacionalizada desde aduanas:

```json
{
  "cod_traslado": "08",
  "doc_relacionado": {
    "tipo_codigo": "50",
    "numero": "118-2026-10-001234",
    "tipo_descripcion": "DECLARACION ADUANERA DE MERCANCIAS"
  }
}
```

Formato DAM: `[0-9]{3}-[0-9]{4}-[0-9]{2}-[0-9]{1,6}` (Aduana-Año-Régimen-Correlativo).

---

### Ejemplo F — Vehículo con autorización especial (MATPEL, carga pesada)

Si el vehículo tiene autorización especial del MTC (Catálogo D-37):

```json
{
  "vehiculo": {
    "placa": "ABC123",
    "nro_circulacion": "TUC1234567890",
    "cod_emisor": "01",
    "nro_autorizacion": "AUT-MTC-2026-001234"
  }
}
```

`cod_emisor` de Cat. D-37:
- `01` = MTC
- `02` = OSINERGMIN
- `03` = SUTRAN
- `04` = OEFA
- `05` = OSIPTEL

---

### Ejemplo G — Hasta 2 vehículos y 2 conductores secundarios

```json
{
  "vehiculo": {
    "placa": "ABC123",
    "secundarios": [
      {"placa": "DEF456", "nro_circulacion": "TUC9876543210"},
      {"placa": "GHI789"}
    ]
  },
  "conductores": [
    {"tipo_doc":"1","num_doc":"11111111","tipo":"Principal","nombres":"JUAN","apellidos":"PEREZ","licencia":"Q11111111"},
    {"tipo_doc":"1","num_doc":"22222222","tipo":"Secundario","nombres":"LUIS","apellidos":"GOMEZ","licencia":"Q22222222"}
  ]
}
```

---

## 6. Catálogos aplicables

| Catálogo | Descripción | Uso típico |
|----------|-------------|------------|
| **01** | Tipo de documento | `31` = GRT |
| **06** | Tipo documento de identidad | `1` DNI, `6` RUC |
| **13** | Ubigeo (INEI) | partida/llegada |
| **18** | Modalidad de transporte | `01` público, `02` privado |
| **20** | Motivo de traslado | `01`-`19` (Venta, Compra, Traslado, Export, Import, etc.) |
| **61** | Documentos relacionados | `01,03,04,09,12,48,50,52,65-69,80,82` |
| **D-37** | Entidades autorizadoras | `01` MTC, `02` OSINERGMIN, etc. |

---

## 7. Validaciones automáticas de la API

La API rechaza con HTTP **422** si:

### Datos faltantes GRT
```json
{"errores": {
  "remitente": ["La Guía de Remisión Transportista requiere los datos del remitente (quien envía la carga)."],
  "doc_relacionado": ["La Guía de Remisión Transportista requiere al menos un documento relacionado (factura, boleta, DAM, GRR, etc.)."],
  "vehiculo": ["La Guía Transportista requiere datos del vehículo."],
  "conductor": ["La Guía Transportista requiere al menos un conductor."]
}}
```

### Remitente = transportista
```json
{"errores": {
  "remitente.num_doc": ["El remitente no puede ser el mismo que el transportista emisor (RUC del tenant)."]
}}
```

### Pagador tercero sin datos
```json
{"errores": {
  "datos_pagador_flete.num_doc": ["Si el pagador es tercero, debe informar su número de documento."]
}}
```

### Subcontratación sin pagador
```json
{"errores": {
  "datos_pagador_flete": ["Si existe transporte subcontratado, debe informar quién paga el flete (remitente/subcontratador/tercero)."]
}}
```

---

## 8. Diferencias XML: GRR vs GRT

| Elemento UBL | GRR (09) | GRT (31) |
|--------------|----------|----------|
| `DespatchAdviceTypeCode` | `09` | **`31`** |
| `DespatchSupplierParty` | Remitente (= tenant) | Transportista (= tenant) |
| `DeliveryCustomerParty` | Destinatario | Destinatario |
| `Shipment/Delivery/Despatch/DespatchParty` | ❌ No existe | **✅ Remitente** |
| `ShipmentStage/CarrierParty` | Transportista contratado | ❌ No se duplica (es DespatchSupplierParty) |
| `AdditionalDocumentReference` | Opcional | **✅ Obligatorio** |
| Serie XML | `T[A-Z0-9]{3}` (T001) | **`V[A-Z0-9]{3}` (V001)** |

---

## 9. Flujo interno de la API

La API emite GRT con un flujo custom (porque Greenter no soporta nativamente el nodo `DespatchParty` dentro de `cac:Despatch`):

1. **Build**: `DespatchBuilder` arma el objeto Greenter `Despatch` con `tipoDoc=31`
2. **Generate XML unsigned**: usa `Greenter\Factory\XmlBuilderResolver`
3. **Inject DespatchParty**: inyecta el bloque con datos del remitente dentro de `cac:Despatch > cac:DespatchAddress`
4. **Sign**: firma con `Greenter\Xml\Signed\SignedXml` y el certificado del tenant
5. **Send**: `$api->sendXml($name, $signedXml)` al endpoint REST OAuth2 GRE
6. **Ticket polling**: Job asíncrono consulta el estado cada 15s hasta recibir CDR

Referencia: `app/Services/Greenter/GreenterService.php::sendGrt()`

---

## 10. Consultar estado y descargar documentos

```bash
# Estado SUNAT (polling ticket si aún está "enviado")
GET /api/v1/guias-remision/{id}/estado

# Descargar XML firmado
GET /api/v1/guias-remision/{id}/xml

# Descargar PDF A4 o ticket
GET /api/v1/guias-remision/{id}/pdf?format=a4

# Listar guías
GET /api/v1/guias-remision?tipo_documento=31
```

---

## 11. Endpoints

| Método | URL | Descripción |
|--------|-----|-------------|
| `POST` | `/api/v1/guias-remision` | Crear GRR (tipo 09) por default, o GRT enviando `"tipo_documento":"31"` |
| `POST` | `/api/v1/guias-remision-transportista` | **Atajo** — forza `tipo_documento=31` automáticamente |
| `GET` | `/api/v1/guias-remision` | Listar todas (GRR + GRT) |
| `GET` | `/api/v1/guias-remision/{id}` | Detalle |
| `PUT` | `/api/v1/guias-remision/{id}` | Actualizar pendiente |
| `GET` | `/api/v1/guias-remision/{id}/xml` | Descargar XML firmado |
| `GET` | `/api/v1/guias-remision/{id}/pdf` | Descargar PDF |
| `GET` | `/api/v1/guias-remision/{id}/estado` | Polling estado SUNAT |

---

## 12. ⚠️ Nota importante sobre entornos

**SUNAT beta (test) no valida completamente todas las reglas REST de GRT** — puede rechazar con códigos como `3383` por validaciones cruzadas con servicios REST (RENIEC, MTC, contribuyentes) que en beta son limitados.

**En producción, con RUCs reales activos y licencias MTC vigentes, GRT funciona correctamente.** Las pruebas estructurales (XML bien formado, firma, nodos correctos) se validan en beta, pero la validación final solo se completa en producción.

**Recomendación:**
- En **beta**: validar el XML generado (debe contener `DespatchAdviceTypeCode=31`, `DespatchParty` con remitente, `AdditionalDocumentReference` con doc relacionado)
- En **producción**: probar con un piloto pequeño antes de escalar

---

## 13. Archivos técnicos de la implementación

| Archivo | Función |
|---------|---------|
| `app/Services/Greenter/GreenterService.php` | Método `sendGrt()` con flujo custom (build → inject → sign → send) |
| `app/Services/Greenter/Builders/DespatchBuilder.php` | `setTipoDoc()` dinámico, `setAddDocs()` para doc relacionados, auto-indicadores |
| `app/Http/Requests/Api/V1/StoreDispatchGuideRequest.php` | Validación GRR vs GRT con `withValidator()` |
| `app/Http/Controllers/Api/V1/DispatchGuideController.php` | Método `storeTransportista()` atajo |
| `app/Actions/Documents/CreateDispatchGuideAction.php` | Serie filtra por `tipo_documento` |
| `app/Models/DispatchGuide.php` | Campos: `tipo_documento`, `remitente`, `doc_relacionado`, `datos_subcontratador`, `datos_pagador_flete` |
| `app/Models/Serie.php` | `guia_transportista` => `31`, prefijo `V` |
| `app/Jobs/SendDispatchGuideToSunat.php` | Ramifica a `sendGrt()` si `tipo_documento === '31'` |
| `database/migrations/2026_04_18_140000_add_grt_fields_to_dispatch_guides.php` | Columnas nuevas |
| `routes/api.php` | Ruta `POST /guias-remision-transportista` |

---

## 14. Referencias SUNAT

- **Catálogo 01**: Tipos de documento — `09` (GRR) y `31` (GRT)
- **Catálogo 61**: Documentos relacionados aplicables a GRE
- **Catálogo D-37**: Entidades emisoras de autorizaciones especiales
- **Manual SUNAT**: ValidacionesGRE v20250421 (hojas *Guía-Transportista2_0* + *Catálogos*)
- **Endpoint REST GRE (producción)**: `https://api-cpe.sunat.gob.pe/v1`
- **Endpoint REST GRE (beta)**: `https://gre-test.nubefact.com/v1`

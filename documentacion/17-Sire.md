# 📘 API SIRE — Documentación completa

> **Módulo:** SIRE (Sistema Integrado de Registros Electrónicos) — SUNAT Perú
> **Libro cubierto:** RCE (Registro de Compras Electrónico), código `080000`
> **Manual base:** Manual de servicios Web Api - SIRE_Compras v22.pdf
> **Total de endpoints:** 25

---

## 🧠 ¿Qué es SIRE y para qué sirve?

**SIRE = Sistema Integrado de Registros Electrónicos** es el sistema de SUNAT que **reemplaza los libros contables físicos o en Excel**. En lugar de llevar tu Registro de Compras (RCE) y Registro de Ventas (RVIE) manualmente, SUNAT genera una **propuesta automática** cada mes basada en los comprobantes electrónicos que ya tiene registrados, y tú la revisas, corriges y confirmas.

> **En una frase:** SUNAT ya sabe lo que compraste y vendiste → te propone el libro → tú lo aceptas o corriges.

### ¿Quiénes están obligados?

Contribuyentes del **Régimen General** y **MYPE Tributario** que lleven libros electrónicos. Aplica a partir del año en que SUNAT los incorpora al padrón (ver `GET /sire/periodos`).

### ¿Qué hace esta API por ti?

| Función | Para qué |
|---------|----------|
| Descargar la propuesta de SUNAT | Ver qué comprobantes incluye SUNAT ese mes |
| Comparar con tus datos locales (reconciliación) | Detectar facturas que faltan o están con montos distintos |
| Aceptar la propuesta | Confirmar el libro si todo está correcto |
| Reemplazar la propuesta | Corregir el libro subiendo tu propio archivo |
| Registrar preliminar | Cerrar el libro del mes en SUNAT |
| Ajustes posteriores | Corregir meses ya cerrados |
| Descargar constancia | Obtener el PDF oficial de SUNAT |

### Flujo mensual resumido

```
1. Activar SIRE (una sola vez)
        ↓
2. Ver periodos disponibles  →  GET /sire/periodos
        ↓
3. Solicitar propuesta SUNAT →  GET /sire/rce/{periodo}/propuesta
        ↓ (asíncrono — el sistema hace polling automático)
4. Ver comprobantes parseados → GET /sire/rce/{periodo}/comprobantes
        ↓
    ¿Está bien?                 ¿Hay diferencias?
        ↓                              ↓
5a. Aceptar propuesta         5b. Reemplazar propuesta
    POST /aceptar-propuesta        POST /reemplazar-propuesta
        ↓                              ↓
6. Registrar preliminar  →  POST /sire/rce/{periodo}/registrar-preliminar
        ↓
7. Descargar constancia PDF → GET /sire/rce/constancia
```

### Libros soportados

| Código | Clave API | Descripción |
|--------|-----------|-------------|
| `080000` | `rce` | Registro de Compras Electrónico |
| `140000` | `rvie` | Registro de Ventas e Ingresos Electrónico |

---

## 📑 Tabla de contenido

- [¿Qué es SIRE y para qué sirve?](#-qué-es-sire-y-para-qué-sirve)
- [1. Conceptos clave](#1-conceptos-clave)
- [2. Configuración previa](#2-configuración-previa)
- [3. Flujo de activación](#3-flujo-de-activación)
- [4. Endpoints por área](#4-endpoints-por-área)
  - [4.1 Activación](#41-activación)
  - [4.2 Periodos (5.33)](#42-periodos-533)
  - [4.3 RCE Propuesta, Preliminar, Resumen, Constancia](#43-rce-propuesta-preliminar-resumen-constancia)
  - [4.4 Comprobantes locales](#44-comprobantes-locales)
  - [4.5 Tickets](#45-tickets)
  - [4.6 Uploads TUS (5.3, 5.5, 5.6)](#46-uploads-tus-53-55-56)
  - [4.7 Ajustes Posteriores (5.18-5.29, 5.45-5.48)](#47-ajustes-posteriores)
  - [4.8 Reconciliación](#48-reconciliación)
- [5. Flujo típico end-to-end](#5-flujo-típico-end-to-end)
- [6. Códigos de error SUNAT mapeados](#6-códigos-de-error-sunat-mapeados)
- [7. Webhooks](#7-webhooks)
- [8. Colección Postman](#8-colección-postman)

---

## 1. Conceptos clave

### 1.1. Modelo asíncrono por tickets

SIRE de SUNAT es **asíncrono**: toda operación modificatoria/consulta pesada devuelve un `num_ticket` de 16 dígitos. El sistema polea automáticamente ese ticket y descarga el resultado cuando termina.

```
[Cliente]  → POST /accion           → [Nuestra API]  → SUNAT
                                          │
             ← 202 Accepted + num_ticket  │
                                          │
[Sistema]  PollTicketJob (cada 10-60s)    │
                                          │
           cuando TERMINADO:
           DownloadTicketFileJob  →  descarga ZIP
           ProcessPropuestaJob    →  parsea → BD

[Cliente]  GET /tickets/{num}      → ver estado actual
[Cliente]  GET /rce/{per}/comprobantes → datos ya parseados
```

### 1.2. Autenticación

| Capa | Cómo |
|------|------|
| **Cliente → Nuestra API** | `X-Api-Key` + `X-Api-Secret` en headers (igual que facturación) |
| **Nuestra API → SUNAT** | OAuth `password` grant, cache automático por tenant (TTL ~59 min) |

Tus credenciales SIRE (`sol_user`, `sol_pass`, `client_id`, `client_secret`) ya están en tu tenant desde el registro inicial. **No debes enviarlas en cada request.**

### 1.3. Estados posibles de un ticket

| Código | Nombre | ¿Final? |
|--------|--------|---------|
| `01` | Pendiente | ❌ |
| `02` | En proceso | ❌ |
| `03` | Procesando | ❌ |
| `05` | Terminado | ✅ |
| `06` | Terminado con errores | ✅ |
| `07` | Error | ✅ |

---

## 2. Configuración previa

### 2.1. Requisitos en Clave SOL

1. Ingresar a [Clave SOL](https://www.sunat.gob.pe) con RUC + usuario + clave
2. Navegar: **Empresas → Consulta Integrada → API Sunat → Registrar Aplicación**
3. En el listado de URIs, seleccionar: **✅ MIGE RCE y RVIE – SIRE**
4. SUNAT genera `client_id` (UUID) y `client_secret` (string)
5. Guardar ambos valores (el `secret` solo se muestra una vez)

### 2.2. Registrar tu empresa en la API

Si aún no registraste tu empresa:

```bash
curl -X POST https://tu-api.com/api/v1/registro \
  -F "ruc=20100000001" \
  -F "razon_social=MI EMPRESA SAC" \
  -F "direccion=AV. PRINCIPAL 123" \
  -F "ubigeo=150101" \
  -F "sol_user=MODDATOS" \
  -F "sol_pass=MODDATOS" \
  -F "client_id=tu-uuid-de-sunat" \
  -F "client_secret=tu-secret-de-sunat" \
  -F "certificado=@mi-cert.pfx" \
  -F "contrasena_certificado=secreto" \
  -F "plan=business"
```

Respuesta:
```json
{
  "estado": "exito",
  "datos": {
    "tenant_id": 1,
    "api_key": "abc123...",
    "api_secret": "def456..."
  }
}
```

**Guarda `api_key` y `api_secret`.** Son los que usarás en todos los endpoints SIRE.

---

## 3. Flujo de activación

Antes de usar cualquier endpoint SIRE, debes **activarlo**. Esto verifica tus credenciales contra SUNAT.

### `POST /api/v1/sire/activar`

```bash
curl -X POST https://tu-api.com/api/v1/sire/activar \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}"
```

**Respuesta OK (200):**
```json
{
  "estado": "exito",
  "datos": {
    "sire_enabled": true,
    "ruc": "20100000001",
    "razon_social": "MI EMPRESA SAC",
    "mensaje": "SIRE activado. Ya puedes consumir los endpoints /v1/sire/*.",
    "token_preview": "eyJhbGciOiJSUzI1..."
  }
}
```

**Respuesta error — credenciales inválidas (401):**
```json
{
  "estado": "error",
  "mensaje": "Credenciales SIRE rechazadas por SUNAT. Verifica que client_id/client_secret tengan seleccionada la URI \"MIGE RCE y RVIE - SIRE\" en Menú SOL, y que sol_user/sol_pass sean correctos.",
  "errores": { "sunat_code": "unauthorized_client" }
}
```

### `POST /api/v1/sire/desactivar`

```bash
curl -X POST https://tu-api.com/api/v1/sire/desactivar \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}"
```

---

## 4. Endpoints por área

Todos los endpoints (excepto `/activar` y `/desactivar`) requieren:
- Header `X-Api-Key` + `X-Api-Secret`
- Middleware `sire.enabled` (tenant activado)

### Respuestas genéricas

- **200 OK** — operación síncrona exitosa
- **202 Accepted** — operación asíncrona aceptada, devuelve `num_ticket`
- **401 Unauthorized** — credenciales API inválidas o SUNAT rechaza
- **403 Forbidden** — SIRE no activado (`error_code: sire_not_enabled`)
- **404 Not Found** — recurso inexistente
- **422 Unprocessable Entity** — error de validación (local o SUNAT)
- **500** — error interno
- **502 Bad Gateway** — SUNAT no disponible

---

### 4.1 Activación

| Método | URL | Descripción |
|--------|-----|-------------|
| POST | `/api/v1/sire/activar` | Verifica credenciales con SUNAT + marca `sire_enabled=true` |
| POST | `/api/v1/sire/desactivar` | Desactiva + invalida token cache |

---

### 4.2 Periodos (5.33)

#### `GET /api/v1/sire/periodos`

Lista los años y periodos habilitados para el contribuyente.

**Query params:**
- `libro` (opcional) — `rce` (default) | `rvie`

**Ejemplo:**
```bash
curl "https://tu-api.com/api/v1/sire/periodos?libro=rce" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}"
```

**Respuesta (200):**
```json
{
  "estado": "exito",
  "datos": {
    "libro": "080000",
    "libro_desc": "Registro de Compras Electrónico",
    "ejercicios": [
      {
        "anio": "2026",
        "des_estado": "ACTIVO",
        "periodos": [
          { "per_tributario": "202601", "cod_estado": "01", "des_estado": "Pendiente" },
          { "per_tributario": "202602", "cod_estado": "02", "des_estado": "Generado" },
          { "per_tributario": "202603", "cod_estado": "01", "des_estado": "Pendiente" }
        ]
      }
    ]
  }
}
```

---

### 4.3 RCE Propuesta, Preliminar, Resumen, Constancia

#### `GET /api/v1/sire/rce/{periodo}/propuesta` — Servicio 5.34

Solicita a SUNAT la generación del archivo con la propuesta de compras.

**Path params:**
- `periodo` — formato `yyyymm` (ej: `202604`)

**Query params (todos opcionales excepto formato):**
- `formato` — `txt` (default) | `csv` | `excel`
- `fec_emision_ini`, `fec_emision_fin` — `yyyy-mm-dd`
- `cod_tipo_cdp` — 2 dígitos (ej: `01`, `07`)
- `num_serie_cdp`, `num_cdp`
- `num_doc_adquiriente`, `mto_desde`, `mto_hasta`

**Ejemplo:**
```bash
curl "https://tu-api.com/api/v1/sire/rce/202604/propuesta?formato=csv&cod_tipo_cdp=01" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}"
```

**Respuesta (202 Accepted):**
```json
{
  "estado": "exito",
  "mensaje": "Solicitud aceptada",
  "datos": {
    "num_ticket": "2026040012345678",
    "per_tributario": "202604",
    "estado": "01",
    "mensaje": "Propuesta solicitada. Consulta el ticket para obtener el archivo."
  }
}
```

> Después de esto, el sistema pooleará automáticamente el ticket y, cuando termine, parseará el archivo. Puedes consultar los comprobantes con `GET /rce/{periodo}/comprobantes`.

---

#### `POST /api/v1/sire/rce/{periodo}/aceptar-propuesta` — Servicio 5.2

Acepta la propuesta SUNAT tal cual para el periodo.

```bash
curl -X POST "https://tu-api.com/api/v1/sire/rce/202604/aceptar-propuesta" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}"
```

**Respuesta (202):**
```json
{
  "estado": "exito",
  "datos": {
    "num_ticket": "2026040011111111",
    "per_tributario": "202604",
    "estado": "01",
    "mensaje": "Propuesta aceptada. El sistema cambiará al estado \"Preliminar registrado\" cuando el ticket termine."
  }
}
```

---

#### `POST /api/v1/sire/rce/{periodo}/registrar-preliminar` — Servicio 5.4

Cierra el periodo como "Preliminar Registrado". Síncrono.

```bash
curl -X POST "https://tu-api.com/api/v1/sire/rce/202604/registrar-preliminar" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}"
```

**Respuesta (200):**
```json
{
  "estado": "exito",
  "datos": {
    "exitoso": true,
    "per_tributario": "202604",
    "respuesta_sunat": { "respuesta": "T" }
  }
}
```

**Error 1008 (ya existe preliminar) (422):**
```json
{
  "estado": "error",
  "mensaje": "El registro electrónico ya se encuentra en el módulo de preliminar.",
  "errores": { "sunat_code": "1008" }
}
```

---

#### `GET /api/v1/sire/rce/{periodo}/resumen` — Servicio 5.35

Descarga resumen binario (sin ticket, directo).

**Query params:**
- `tipo` — `propuesta` (default) | `preliminar` | `registro` | `preliminar_registrado` | `ajustes_posteriores` | `no_domiciliados` | `no_incluidos_excluidos`
- `formato` — `txt` (default) | `csv` | `excel`

```bash
curl -o resumen-202604.csv \
  "https://tu-api.com/api/v1/sire/rce/202604/resumen?tipo=propuesta&formato=csv" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}"
```

**Respuesta:** archivo binario con header `Content-Disposition: attachment; filename="resumen-202604-1.csv"`

---

#### `GET /api/v1/sire/rce/constancia` — Servicio 5.49

Descarga PDF oficial de constancia de recepción SUNAT.

**Query params:**
- `nom_constancia` (obligatorio) — nombre del archivo generado por SUNAT (lo obtienes tras registrar preliminar)

```bash
curl -o constancia.pdf \
  "https://tu-api.com/api/v1/sire/rce/constancia?nom_constancia=LE20100000001202604080000000010001.pdf" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}"
```

---

### 4.4 Comprobantes locales

#### `GET /api/v1/sire/rce/{periodo}/comprobantes`

Lista los comprobantes ya parseados localmente (después del flujo `propuesta → ticket → download → parse`).

**Query params:**
- `fase` — `propuesta` | `preliminar` | `registrado`
- `cod_tipo_cdp` — 2 dígitos
- `num_doc_proveedor` — RUC del proveedor
- `incluido` — `true` | `false`
- `per_page` — 1-200 (default 50)
- `sort_by` — `fec_emision` | `mto_total` | `razon_social_proveedor`
- `sort_dir` — `asc` | `desc`

```bash
curl "https://tu-api.com/api/v1/sire/rce/202604/comprobantes?fase=propuesta&per_page=100" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}"
```

**Respuesta (200):**
```json
{
  "estado": "exito",
  "datos": {
    "totales": {
      "total_comprobantes": 120,
      "suma_bi_gravada": 25000.00,
      "suma_igv": 4500.00,
      "suma_total": 29500.00
    },
    "comprobantes": {
      "pagina_actual": 1,
      "datos": [
        {
          "id": 1,
          "car_sunat": "11-202604-0001",
          "fec_emision": "2026-04-15",
          "cod_tipo_cdp": "01",
          "num_serie_cdp": "F001",
          "num_cdp": "123",
          "num_doc_proveedor": "20100000002",
          "razon_social_proveedor": "PROVEEDOR SAC",
          "mto_bi_gravada": "1000.00",
          "mto_igv": "180.00",
          "mto_total": "1180.00",
          "cod_moneda": "PEN"
        }
      ],
      "total": 120,
      "por_pagina": 100
    }
  }
}
```

#### `GET /api/v1/sire/rce/{periodo}/comprobantes/{id}`

Detalle de un comprobante específico.

---

### 4.5 Tickets

#### `GET /api/v1/sire/tickets`

Lista los tickets del tenant.

**Query params:**
- `per_tributario` — yyyymm
- `estado` — 2 dígitos (01, 03, 05, 07...)
- `cod_proceso` — Anexo I
- `finalizado` — `true` | `false`
- `per_page` — 1-100

```bash
curl "https://tu-api.com/api/v1/sire/tickets?per_tributario=202604&finalizado=true" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}"
```

**Respuesta:**
```json
{
  "estado": "exito",
  "datos": {
    "pagina_actual": 1,
    "datos": [
      {
        "num_ticket": "2026040012345678",
        "per_tributario": "202604",
        "cod_proceso": "10",
        "estado": "05",
        "estado_descripcion": "Terminado",
        "estado_enum": "TERMINADO",
        "finalizado": true,
        "exitoso": true,
        "archivo_disponible": true,
        "nom_archivo_reporte": "LE20100000001202604080000010000001.zip",
        "cnt_cp_informados": 120,
        "cnt_cp_error": 0,
        "poll_attempts": 3,
        "created_at": "2026-04-15T10:30:00Z",
        "last_polled_at": "2026-04-15T10:32:15Z",
        "finished_at": "2026-04-15T10:32:15Z"
      }
    ]
  }
}
```

#### `GET /api/v1/sire/tickets/{numTicket}`

Detalle de un ticket.

#### `POST /api/v1/sire/tickets/{numTicket}/refrescar`

Fuerza consulta inmediata del estado del ticket contra SUNAT (sin esperar al polling automático).

```bash
curl -X POST "https://tu-api.com/api/v1/sire/tickets/2026040012345678/refrescar" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}"
```

#### `GET /api/v1/sire/tickets/{numTicket}/archivo`

Descarga el ZIP generado (ya guardado localmente).

**Respuestas:**
- `200` — archivo binario
- `409` — archivo aún no disponible
- `404` — archivo no existe en storage

---

### 4.6 Uploads TUS (5.3, 5.5, 5.6)

Tres endpoints que suben archivos `.zip` a SUNAT usando el protocolo TUS.io. Aceptan **dos formatos de entrada:**

**A) Multipart con ZIP preparado:**
```bash
curl -X POST "https://tu-api.com/api/v1/sire/rce/202604/reemplazar-propuesta" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}" \
  -F "archivo=@LE20100000001202604080000061000000001.zip" \
  -F "secuencia=1"
```

**B) JSON con el TXT (el sistema lo empaqueta):**
```bash
curl -X POST "https://tu-api.com/api/v1/sire/rce/202604/reemplazar-propuesta" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}" \
  -H "Content-Type: application/json" \
  -d '{
    "txt": "202604|11-202604-0001|1|15/04/2026||01|F001|123|6|20100000002|PROVEEDOR SAC|1000.00|180.00|0.00|0.00|0.00|0.00|0.00|1180.00|PEN|3.700\n",
    "secuencia": 1
  }'
```

Respuesta (202):
```json
{
  "estado": "exito",
  "datos": {
    "num_ticket": "2026040055555555",
    "per_tributario": "202604",
    "estado": "01",
    "archivo": "LE20100000001202604080000061000000001.zip",
    "mensaje": "Propuesta reemplazada. Siga el ticket."
  }
}
```

| Endpoint | Servicio SUNAT | CodProceso | Uso |
|----------|----------------|------------|-----|
| `POST /rce/{per}/reemplazar-propuesta` | 5.3 | 61 | Reemplazar propuesta SUNAT por tu archivo |
| `POST /rce/{per}/no-domiciliados` | 5.5 | 56 | Cargar comprobantes de proveedores no domiciliados |
| `POST /rce/{per}/complementar-propuesta` | 5.6 | 54 | Complementar datos sin reemplazar propuesta |

**Límites:**
- Tamaño máximo: 6 GB
- Tamaño mínimo: > 0 KB
- Extensión: solo `.zip`
- Nombre del ZIP: formato posicional SUNAT (error 1044 si no cumple). Si envías `txt`, el sistema arma el nombre automáticamente.

---

### 4.7 Ajustes Posteriores

Cubren servicios 5.18-5.29 y 5.45-5.48. **4 variantes × 4 acciones = 16 combinaciones**, pero solo 4 rutas reales usando `{variant}` como parámetro:

**Variantes disponibles:**
| `{variant}` | Descripción |
|-------------|-------------|
| `actual` | Ajuste del periodo actual |
| `no-domiciliados` | Ajuste con proveedores no domiciliados |
| `periodos-anteriores` | Ajuste de periodos anteriores (general) |
| `periodos-anteriores-nd` | Ajuste periodos anteriores con no domiciliados |

#### `POST /api/v1/sire/rce/{periodo}/ajustes-posteriores/{variant}/cargar`

TUS upload del archivo con los ajustes (servicios 5.18/5.21/5.24/5.27).

```bash
curl -X POST "https://tu-api.com/api/v1/sire/rce/202604/ajustes-posteriores/actual/cargar" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}" \
  -H "Content-Type: application/json" \
  -d '{
    "txt": "202604|AP001|...|\n",
    "secuencia": 1
  }'
```

Respuesta (202):
```json
{
  "estado": "exito",
  "datos": {
    "num_ticket": "2026040088888888",
    "variant": "actual",
    "archivo": "LE20100000001202604080000059000000001.zip",
    "mensaje": "Ajustes cargados. Siga el ticket para confirmar."
  }
}
```

#### `POST /api/v1/sire/rce/{periodo}/ajustes-posteriores/{variant}/enviar`

Confirma/registra los ajustes previamente cargados (servicios 5.19/5.22/5.25/5.28).

**Body JSON:**
- `num_ticket_carga` — el ticket del `cargar` previo (16 caracteres)

```bash
curl -X POST "https://tu-api.com/api/v1/sire/rce/202604/ajustes-posteriores/actual/enviar" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}" \
  -H "Content-Type: application/json" \
  -d '{ "num_ticket_carga": "2026040088888888" }'
```

#### `GET /api/v1/sire/rce/{periodo}/ajustes-posteriores/{variant}/descargar`

Solicita exportación (servicios 5.45/5.46/5.47/5.48).

**Query:** `formato=txt|csv|excel`

```bash
curl "https://tu-api.com/api/v1/sire/rce/202604/ajustes-posteriores/actual/descargar?formato=excel" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}"
```

#### `POST /api/v1/sire/rce/{periodo}/ajustes-posteriores/{variant}/eliminar`

Elimina comprobantes específicos del ajuste (servicios 5.20/5.23/5.26/5.29).

**Body JSON:**
```json
{
  "cod_ajuste_posterior": "AP-202604-001",
  "detalles": [
    {
      "cod_tipo_cdp": "01",
      "num_serie_cdp": "F001",
      "num_cdp": "123",
      "cod_car": "11-202604-0001"
    },
    {
      "cod_tipo_cdp": "07",
      "num_serie_cdp": "FC01",
      "num_cdp": "45",
      "cod_car": "11-202604-0002"
    }
  ]
}
```

Respuesta:
```json
{
  "estado": "exito",
  "datos": {
    "variant": "actual",
    "per_tributario": "202604",
    "eliminados": 2,
    "respuesta_sunat": { "resultado": "OK" }
  }
}
```

---

### 4.8 Reconciliación

Análisis cruzado de los comprobantes SIRE con tu BD local.

#### `GET /api/v1/sire/rce/{periodo}/reconciliar`

Ejecuta reconciliación síncrona (periodos pequeños).

```bash
curl "https://tu-api.com/api/v1/sire/rce/202604/reconciliar" \
  -H "X-Api-Key: {tu_api_key}" \
  -H "X-Api-Secret: {tu_api_secret}"
```

**Respuesta (200):**
```json
{
  "estado": "exito",
  "datos": {
    "tenant_id": 1,
    "per_tributario": "202604",
    "run_at": "2026-04-18T14:30:00Z",
    "totales": {
      "total_comprobantes": 120,
      "total_incluidos": 118,
      "total_excluidos": 2,
      "suma_bi_gravada": 25000.00,
      "suma_igv": 4500.00,
      "suma_total": 29500.00,
      "promedio_monto": 245.83,
      "fecha_min": "2026-04-01",
      "fecha_max": "2026-04-30"
    },
    "por_proveedor": [
      {
        "num_doc": "20100000002",
        "tipo_doc": "6",
        "razon_social": "PROVEEDOR SAC",
        "cantidad_cp": 15,
        "suma_bi": 10000.00,
        "suma_igv": 1800.00,
        "suma_total": 11800.00
      }
    ],
    "por_tipo": [
      { "cod": "01", "nombre": "Factura", "cantidad": 100, "monto": 28000 },
      { "cod": "07", "nombre": "Nota de Crédito", "cantidad": 20, "monto": 1500 }
    ],
    "cruce_local": {
      "proveedores_totales": 35,
      "proveedores_en_clients": 12,
      "proveedores_no_en_clients": 23,
      "relacion_bidireccional": [
        {
          "num_doc": "20100000002",
          "razon_social": "PROVEEDOR SAC",
          "me_emitio_en_periodo": true,
          "le_emiti_en_periodo": true,
          "cnt_facturas_venta": 3,
          "monto_ventas": 5000.00
        }
      ]
    },
    "alertas": {
      "duplicados": [],
      "outliers_monto": [
        {
          "id": 45,
          "car_sunat": "11-202604-0045",
          "proveedor": "20100000007",
          "razon_social": "OUTLIER SAC",
          "mto_total": 50000,
          "veces_promedio": 203.4
        }
      ],
      "con_inconsistencia_sunat": 2
    }
  }
}
```

#### `POST /api/v1/sire/rce/{periodo}/reconciliar-async`

Encola la reconciliación para background (periodos grandes).

#### `GET /api/v1/sire/rce/{periodo}/reconciliaciones`

Historial de reconciliaciones previas del periodo.

#### `GET /api/v1/sire/rce/reconciliaciones/{id}`

Detalle completo de una reconciliación guardada.

---

## 5. Flujo típico end-to-end

### 5.1 Camino feliz A: aceptar propuesta SUNAT

```bash
# 1. Activar SIRE (una sola vez)
POST /api/v1/sire/activar

# 2. Ver periodos disponibles
GET /api/v1/sire/periodos

# 3. Solicitar la propuesta SUNAT del periodo
GET /api/v1/sire/rce/202604/propuesta
# → num_ticket = "2026040012345678"

# 4. [El sistema hace polling automático en background]
#    Para verificar manualmente:
GET /api/v1/sire/tickets/2026040012345678

# 5. Cuando el ticket esté "Terminado", ver los comprobantes parseados
GET /api/v1/sire/rce/202604/comprobantes

# 6. Si todo se ve bien, aceptar la propuesta
POST /api/v1/sire/rce/202604/aceptar-propuesta
# → num_ticket = "2026040011111111"

# 7. Esperar que ese ticket termine y registrar preliminar
POST /api/v1/sire/rce/202604/registrar-preliminar
# → exitoso: true

# 8. Descargar constancia PDF
GET /api/v1/sire/rce/constancia?nom_constancia=LE20100000001202604080000000010001.pdf

# 9. (Opcional) Reconciliar con tu data local
GET /api/v1/sire/rce/202604/reconciliar
```

### 5.2 Camino feliz B: reemplazar propuesta

```bash
# 1-3. Igual que A

# 4. Si la propuesta no refleja tu realidad, reemplazarla con tu TXT
POST /api/v1/sire/rce/202604/reemplazar-propuesta
Body: { "txt": "202604|...|...\n", "secuencia": 1 }
# → num_ticket = "2026040022222222"

# 5. Esperar y verificar
GET /api/v1/sire/tickets/2026040022222222

# 6. (Opcional) Cargar no domiciliados
POST /api/v1/sire/rce/202604/no-domiciliados
Body: { "txt": "202604|ND001|...\n" }

# 7. Registrar preliminar
POST /api/v1/sire/rce/202604/registrar-preliminar
```

### 5.3 Camino C: ajustes posteriores

```bash
# Después de haber generado el periodo en SUNAT

# 1. Cargar los ajustes
POST /api/v1/sire/rce/202604/ajustes-posteriores/actual/cargar
Body: { "txt": "...", "secuencia": 1 }
# → num_ticket_carga = "2026040088888888"

# 2. Enviar los ajustes con ese ticket
POST /api/v1/sire/rce/202604/ajustes-posteriores/actual/enviar
Body: { "num_ticket_carga": "2026040088888888" }

# 3. Descargar el archivo consolidado
GET /api/v1/sire/rce/202604/ajustes-posteriores/actual/descargar?formato=excel
```

---

## 6. Códigos de error SUNAT mapeados

El API traduce los códigos 422 de SUNAT a mensajes legibles. Los más comunes:

| Código | Mensaje legible | Acción |
|--------|-----------------|--------|
| `1001` | El campo "numRuc" no fue enviado o está vacío | Verificar configuración del tenant |
| `1002` | El número de RUC debe tener 11 dígitos | Corregir RUC |
| `1005` | El campo "perTributario" no fue enviado | Enviar perTributario |
| `1006` | Formato de perTributario no cumple con "yyyymm" | Usar formato 202604 |
| `1007` | El perTributario no debe ser mayor a la fecha actual | Elegir periodo pasado o actual |
| `1008` | El registro ya se encuentra en el módulo preliminar | Ya está hecho — ver `resumen` |
| `1009` | El registro ya ha sido generado | Pasar a ajustes posteriores |
| `1022` | Nombre del archivo vacío | Revisar envío TUS |
| `1024` | El archivo fue previamente enviado | Cambiar `secuencia` o esperar |
| `1044` | Formato del nombre del archivo incorrecto | El ZipBuilder lo arma bien si usas `txt` |
| `1346` | Archivo > 6 GB | Dividir en varios |
| `1348` | La extensión debe ser .zip | Usar ZIP |
| `1350` | Tamaño del archivo debe ser > 0 KB | Verificar contenido |
| `1518` | No existen documentos para exportar | Periodo sin data |
| `2270` | fecEmisionIni debe tener formato yyyy-mm-dd | Ajustar fecha |
| `unauthorized_client` | Credenciales SIRE rechazadas | Verificar URI "MIGE RCE y RVIE - SIRE" en SOL |

Respuesta genérica de error:
```json
{
  "estado": "error",
  "mensaje": "Mensaje legible en español",
  "errores": {
    "sunat_code": "1007"
  }
}
```

---

## 7. Webhooks

Si tu tenant tiene configurado `webhook_url`, recibirás notificaciones POST con eventos SIRE.

**Eventos disponibles:**
- `ticket.completed`
- `ticket.failed`
- `propuesta.processed`
- `preliminar.registered`
- `reconciliation.completed`

**Payload ejemplo (reconciliation.completed):**
```json
{
  "event": "reconciliation.completed",
  "tenant_id": 1,
  "ruc": "20100000001",
  "timestamp": "2026-04-18T03:15:00-05:00",
  "payload": {
    "per_tributario": "202604",
    "total_sunat": 120,
    "suma_total": 29500.00,
    "proveedores_unicos": 35,
    "duplicados": 0,
    "sin_contraparte": 23
  }
}
```

El webhook se dispara solo cuando la reconciliación encuentra algo relevante (duplicados, outliers, inconsistencias SUNAT).

---

## 8. Colección Postman

Importa esta colección directamente en Postman. Guarda como `SIRE-API.postman_collection.json`:

```json
{
  "info": {
    "name": "API-PRO SIRE",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "variable": [
    { "key": "base_url", "value": "https://tu-api.com/api/v1" },
    { "key": "api_key", "value": "tu_api_key" },
    { "key": "api_secret", "value": "tu_api_secret" },
    { "key": "periodo", "value": "202604" },
    { "key": "num_ticket", "value": "" }
  ],
  "auth": {
    "type": "noauth"
  },
  "item": [
    {
      "name": "0. Activación",
      "item": [
        {
          "name": "Activar SIRE",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/sire/activar",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        },
        {
          "name": "Desactivar SIRE",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/sire/desactivar",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        }
      ]
    },
    {
      "name": "1. Periodos",
      "item": [
        {
          "name": "Listar periodos RCE",
          "request": {
            "method": "GET",
            "url": "{{base_url}}/sire/periodos?libro=rce",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        }
      ]
    },
    {
      "name": "2. RCE Flujo Principal",
      "item": [
        {
          "name": "Solicitar propuesta (5.34)",
          "request": {
            "method": "GET",
            "url": "{{base_url}}/sire/rce/{{periodo}}/propuesta?formato=txt",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          },
          "event": [{
            "listen": "test",
            "script": {
              "exec": [
                "if (pm.response.code === 202) {",
                "  pm.collectionVariables.set('num_ticket', pm.response.json().data.num_ticket);",
                "}"
              ]
            }
          }]
        },
        {
          "name": "Aceptar propuesta (5.2)",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/sire/rce/{{periodo}}/aceptar-propuesta",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        },
        {
          "name": "Registrar preliminar (5.4)",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/sire/rce/{{periodo}}/registrar-preliminar",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        },
        {
          "name": "Descargar resumen (5.35)",
          "request": {
            "method": "GET",
            "url": "{{base_url}}/sire/rce/{{periodo}}/resumen?tipo=propuesta&formato=csv",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        },
        {
          "name": "Descargar constancia (5.49)",
          "request": {
            "method": "GET",
            "url": "{{base_url}}/sire/rce/constancia?nom_constancia=LE20100000001202604.pdf",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        }
      ]
    },
    {
      "name": "3. Comprobantes locales",
      "item": [
        {
          "name": "Listar comprobantes del periodo",
          "request": {
            "method": "GET",
            "url": "{{base_url}}/sire/rce/{{periodo}}/comprobantes?fase=propuesta&per_page=50",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        }
      ]
    },
    {
      "name": "4. Tickets",
      "item": [
        {
          "name": "Listar tickets",
          "request": {
            "method": "GET",
            "url": "{{base_url}}/sire/tickets?per_tributario={{periodo}}",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        },
        {
          "name": "Ver ticket",
          "request": {
            "method": "GET",
            "url": "{{base_url}}/sire/tickets/{{num_ticket}}",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        },
        {
          "name": "Refrescar estado",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/sire/tickets/{{num_ticket}}/refrescar",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        },
        {
          "name": "Descargar archivo ZIP",
          "request": {
            "method": "GET",
            "url": "{{base_url}}/sire/tickets/{{num_ticket}}/archivo",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        }
      ]
    },
    {
      "name": "5. Uploads TUS",
      "item": [
        {
          "name": "Reemplazar propuesta (JSON)",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/sire/rce/{{periodo}}/reemplazar-propuesta",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" },
              { "key": "Content-Type", "value": "application/json" }
            ],
            "body": {
              "mode": "raw",
              "raw": "{\n  \"txt\": \"202604|11-202604-0001|1|15/04/2026||01|F001|123|6|20100000002|PROV DEMO|1000.00|180.00|0.00|0.00|0.00|0.00|0.00|1180.00|PEN|\\n\",\n  \"secuencia\": 1\n}"
            }
          }
        },
        {
          "name": "Reemplazar propuesta (Multipart)",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/sire/rce/{{periodo}}/reemplazar-propuesta",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ],
            "body": {
              "mode": "formdata",
              "formdata": [
                { "key": "archivo", "type": "file", "src": "" },
                { "key": "secuencia", "value": "1", "type": "text" }
              ]
            }
          }
        },
        {
          "name": "Cargar No Domiciliados",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/sire/rce/{{periodo}}/no-domiciliados",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" },
              { "key": "Content-Type", "value": "application/json" }
            ],
            "body": {
              "mode": "raw",
              "raw": "{\n  \"txt\": \"...\",\n  \"secuencia\": 1\n}"
            }
          }
        },
        {
          "name": "Complementar propuesta",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/sire/rce/{{periodo}}/complementar-propuesta",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" },
              { "key": "Content-Type", "value": "application/json" }
            ],
            "body": { "mode": "raw", "raw": "{\n  \"txt\": \"...\"\n}" }
          }
        }
      ]
    },
    {
      "name": "6. Ajustes Posteriores",
      "item": [
        {
          "name": "Cargar (actual)",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/sire/rce/{{periodo}}/ajustes-posteriores/actual/cargar",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" },
              { "key": "Content-Type", "value": "application/json" }
            ],
            "body": { "mode": "raw", "raw": "{\n  \"txt\": \"...\",\n  \"secuencia\": 1\n}" }
          }
        },
        {
          "name": "Enviar (actual)",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/sire/rce/{{periodo}}/ajustes-posteriores/actual/enviar",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" },
              { "key": "Content-Type", "value": "application/json" }
            ],
            "body": { "mode": "raw", "raw": "{\n  \"num_ticket_carga\": \"{{num_ticket}}\"\n}" }
          }
        },
        {
          "name": "Descargar (actual)",
          "request": {
            "method": "GET",
            "url": "{{base_url}}/sire/rce/{{periodo}}/ajustes-posteriores/actual/descargar?formato=excel",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        },
        {
          "name": "Eliminar CPs",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/sire/rce/{{periodo}}/ajustes-posteriores/actual/eliminar",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" },
              { "key": "Content-Type", "value": "application/json" }
            ],
            "body": {
              "mode": "raw",
              "raw": "{\n  \"cod_ajuste_posterior\": \"AP-202604-001\",\n  \"detalles\": [\n    { \"cod_tipo_cdp\": \"01\", \"num_serie_cdp\": \"F001\", \"num_cdp\": \"123\", \"cod_car\": \"11-202604-0001\" }\n  ]\n}"
            }
          }
        }
      ]
    },
    {
      "name": "7. Reconciliación",
      "item": [
        {
          "name": "Reconciliar (síncrono)",
          "request": {
            "method": "GET",
            "url": "{{base_url}}/sire/rce/{{periodo}}/reconciliar",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        },
        {
          "name": "Reconciliar (async)",
          "request": {
            "method": "POST",
            "url": "{{base_url}}/sire/rce/{{periodo}}/reconciliar-async",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        },
        {
          "name": "Historial reconciliaciones",
          "request": {
            "method": "GET",
            "url": "{{base_url}}/sire/rce/{{periodo}}/reconciliaciones",
            "header": [
              { "key": "X-Api-Key", "value": "{{api_key}}" },
              { "key": "X-Api-Secret", "value": "{{api_secret}}" }
            ]
          }
        }
      ]
    }
  ]
}
```

---

## 📋 Resumen de endpoints

| # | Método | URL | Async | Servicio SUNAT |
|---|--------|-----|-------|----------------|
| 1 | POST | `/sire/activar` | no | - |
| 2 | POST | `/sire/desactivar` | no | - |
| 3 | GET | `/sire/periodos` | no | 5.33 |
| 4 | GET | `/sire/rce/{per}/propuesta` | sí (ticket) | 5.34 |
| 5 | POST | `/sire/rce/{per}/aceptar-propuesta` | sí (ticket) | 5.2 |
| 6 | POST | `/sire/rce/{per}/registrar-preliminar` | no | 5.4 |
| 7 | GET | `/sire/rce/{per}/resumen` | no (binario) | 5.35 |
| 8 | GET | `/sire/rce/constancia` | no (PDF) | 5.49 |
| 9 | GET | `/sire/rce/{per}/comprobantes` | no (local) | - |
| 10 | GET | `/sire/rce/{per}/comprobantes/{id}` | no (local) | - |
| 11 | GET | `/sire/tickets` | no | - |
| 12 | GET | `/sire/tickets/{num}` | no | - |
| 13 | POST | `/sire/tickets/{num}/refrescar` | no | 5.31 |
| 14 | GET | `/sire/tickets/{num}/archivo` | no | - |
| 15 | POST | `/sire/rce/{per}/reemplazar-propuesta` | sí (ticket) | 5.3 |
| 16 | POST | `/sire/rce/{per}/no-domiciliados` | sí (ticket) | 5.5 |
| 17 | POST | `/sire/rce/{per}/complementar-propuesta` | sí (ticket) | 5.6 |
| 18 | POST | `/sire/rce/{per}/ajustes-posteriores/{v}/cargar` | sí (ticket) | 5.18/21/24/27 |
| 19 | POST | `/sire/rce/{per}/ajustes-posteriores/{v}/enviar` | sí (ticket) | 5.19/22/25/28 |
| 20 | GET | `/sire/rce/{per}/ajustes-posteriores/{v}/descargar` | sí (ticket) | 5.45/46/47/48 |
| 21 | POST | `/sire/rce/{per}/ajustes-posteriores/{v}/eliminar` | no | 5.20/23/26/29 |
| 22 | GET | `/sire/rce/{per}/reconciliar` | no (local) | - |
| 23 | POST | `/sire/rce/{per}/reconciliar-async` | sí (job) | - |
| 24 | GET | `/sire/rce/{per}/reconciliaciones` | no | - |
| 25 | GET | `/sire/rce/reconciliaciones/{id}` | no | - |

---

## ⚙️ Operación en producción

### Workers necesarios

```bash
# En supervisor/systemd:
php artisan queue:work redis --queue=sire-poll    --tries=1 --timeout=60
php artisan queue:work redis --queue=sire-heavy   --tries=3 --timeout=600
php artisan queue:work redis --queue=sire-process --tries=3 --timeout=300
```

### Scheduler (cron cada minuto)

```cron
* * * * * cd /ruta/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

Tareas SIRE registradas automáticamente:
- `sire:poll-pending` — cada minuto (ticket polling)
- `sire:reconcile-all` — diario 03:00 AM (reconciliación de último periodo)

### Rate limiting

Cada tenant tiene su propio rate limit hacia SUNAT: **30 requests/minuto** configurable en `config/sire.php`.

### Almacenamiento

Archivos descargados/subidos se guardan en: `storage/sire/{tenant_id}/{periodo}/...`

En producción recomendamos S3 con lifecycle — configurar en `SIRE_STORAGE_DISK=s3` en `.env`.

---

## 🔒 Seguridad

- Todas las credenciales (`sol_pass`, `client_secret`, `sire_client_secret`) se almacenan **encriptadas** en `tenants`
- El token SUNAT se cachea por tenant — imposible cruzar tokens entre empresas
- Storage de archivos aislado por `tenant_id` + validación en descarga
- Ningún endpoint SIRE toca módulos de facturación electrónica (aislamiento total)

---

## 🧪 Testing

El módulo tiene **84 tests** (27 Unit + 57 Feature) que validan:
- Lógica pura (parsers, enums, builders)
- Orquestación completa (auth → request → parse → BD → jobs)
- Aislamiento multi-tenant
- Mapeo de errores SUNAT
- Validación en cada controller

```bash
php artisan test tests/Unit/Sire/ tests/Feature/Sire/
```

---

✨ **Módulo SIRE v1.0** — Listo para producción.

# 📘 Configuración de Empresa y Datos Maestros

> Base URL: `https://tu-api.com/api/v1`
> Todos los endpoints requieren `X-Api-Key` + `X-Api-Secret` **excepto `/registro`**.

## 📑 Contenido

- [1. Registro de empresa](#1-registro-de-empresa)
- [2. Ver/actualizar empresa](#2-verractualizar-empresa)
- [3. Logo](#3-logo)
- [4. Certificado digital](#4-certificado-digital)
- [5. Sucursales](#5-sucursales)
- [6. Series](#6-series)
- [7. Clientes](#7-clientes)
- [8. Búsqueda RUC/DNI](#8-búsqueda-rucdni)
- [9. Planes y suscripción](#9-planes-y-suscripción)

---

## 1. Registro de empresa

### `POST /registro` — pública (sin auth)

Crea un nuevo tenant (empresa) y devuelve `api_key` + `api_secret`.

**Body (multipart/form-data):**

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `ruc` | string(11) | ✅ | RUC empresa |
| `razon_social` | string | ✅ | Máx 255 |
| `nombre_comercial` | string | ❌ | |
| `direccion` | string | ✅ | Máx 500 |
| `ubigeo` | string(6) | ✅ | Ejemplo: `150101` |
| `departamento`, `provincia`, `distrito` | string | ❌ | |
| `sol_user` | string | ✅ | Usuario secundario SOL |
| `sol_pass` | string | ✅ | Clave SOL |
| `entorno` | string | ❌ | `beta` (default) \| `production` |
| `plan` | string | ❌ | `free` \| `pro` \| `business` |
| `client_id` | string | ❌ | Credenciales API SUNAT (para consulta CPE / SIRE) |
| `client_secret` | string | ❌ | Credenciales API SUNAT |
| `certificado` | file | ✅ | `.pfx`, `.p12`, `.pem`, `.cer`, `.crt` |
| `contrasena_certificado` | string | Si `.pfx`/`.p12` | Contraseña del cert |
| `logo` | file (jpg/png) | ❌ | Máx 2 MB |

### Ejemplo

```bash
curl -X POST https://tu-api.com/api/v1/registro \
  -F "ruc=20100000001" \
  -F "razon_social=MI EMPRESA SAC" \
  -F "nombre_comercial=Mi Empresa" \
  -F "direccion=AV. PRINCIPAL 123 - LIMA" \
  -F "ubigeo=150101" \
  -F "departamento=LIMA" \
  -F "provincia=LIMA" \
  -F "distrito=MIRAFLORES" \
  -F "sol_user=MODDATOS" \
  -F "sol_pass=MODDATOS" \
  -F "entorno=beta" \
  -F "plan=pro" \
  -F "certificado=@certificado_beta.pfx" \
  -F "contrasena_certificado=123456" \
  -F "logo=@logo.png"
```

**Respuesta (201):**
```json
{
  "estado": "exito",
  "mensaje": "Creado exitosamente",
  "datos": {
    "tenant_id": 15,
    "ruc": "20100000001",
    "razon_social": "MI EMPRESA SAC",
    "entorno": "beta",
    "plan": "pro",
    "api_key": "abc123xyz...",
    "api_secret": "def456uvw...",
    "importante": "Guarde sus credenciales. El api_secret NO se puede recuperar."
  }
}
```

⚠️ **Guarda `api_key` + `api_secret`**. El secret no se recupera.

---

## 2. Ver/actualizar empresa

### `GET /empresa`

```bash
curl https://tu-api.com/api/v1/empresa \
  -H "X-Api-Key: {api_key}" \
  -H "X-Api-Secret: {api_secret}"
```

**Respuesta:**
```json
{
  "estado": "exito",
  "datos": {
    "id": 15,
    "ruc": "20100000001",
    "razon_social": "MI EMPRESA SAC",
    "nombre_comercial": "Mi Empresa",
    "direccion": "AV. PRINCIPAL 123",
    "ubigeo": "150101",
    "entorno": "beta",
    "plan": "pro",
    "is_active": true,
    "telefonos": ["+51 999888777"],
    "emails": ["ventas@miempresa.com"],
    "cuentas_bancarias": [
      {
        "banco": "BCP",
        "tipo_cuenta": "Corriente",
        "moneda": "PEN",
        "numero": "1234567890",
        "cci": "00200912345678901234",
        "titular": "MI EMPRESA SAC"
      }
    ],
    "billeteras_digitales": [
      { "tipo": "yape", "numero": "999888777", "titular": "Juan Pérez" }
    ]
  }
}
```

### `PUT /empresa`

**Campos actualizables (todos opcionales):**

| Campo | Tipo | Notas |
|-------|------|-------|
| `razon_social` | string | Máx 255 |
| `nombre_comercial` | string | |
| `direccion`, `ubigeo`, `departamento`, `provincia`, `distrito` | string | |
| `sol_user`, `sol_pass` | string | Credenciales SUNAT |
| `client_id`, `client_secret` | string | Credenciales API SUNAT |
| `entorno` | string | `beta` \| `production` |
| `url_webhook` | URL | Para notificaciones |
| `telefonos` | array(string) | Máx 5 |
| `emails` | array(email) | Máx 5 |
| `cuentas_bancarias` | array | Ver esquema abajo |
| `billeteras_digitales` | array | Ver esquema abajo |
| `mensaje_agradecimiento` | string | Para PDFs |
| `mensaje_promocional` | string | Para PDFs |

**Cuentas bancarias:**
```json
{
  "banco": "BCP",
  "tipo_cuenta": "Corriente",
  "moneda": "PEN",
  "numero": "1234567890",
  "cci": "00200912345678901234",
  "titular": "MI EMPRESA SAC"
}
```

**Billeteras digitales:**
```json
{
  "tipo": "yape",
  "numero": "999888777",
  "titular": "Juan Pérez"
}
```
Tipos: `yape`, `plin`, `tunki`, `otro`.

### Ejemplo

```bash
curl -X PUT https://tu-api.com/api/v1/empresa \
  -H "X-Api-Key: {api_key}" \
  -H "X-Api-Secret: {api_secret}" \
  -H "Content-Type: application/json" \
  -d '{
    "telefonos": ["+51 999888777", "+51 (01) 2223344"],
    "emails": ["ventas@miempresa.com", "soporte@miempresa.com"],
    "cuentas_bancarias": [
      {
        "banco": "BCP",
        "tipo_cuenta": "Corriente",
        "moneda": "PEN",
        "numero": "1234567890",
        "cci": "00200912345678901234"
      }
    ],
    "url_webhook": "https://mi-sistema.com/webhooks/facturacion"
  }'
```

---

## 3. Logo

### `POST /empresa/logo`

```bash
curl -X POST https://tu-api.com/api/v1/empresa/logo \
  -H "X-Api-Key: {api_key}" \
  -H "X-Api-Secret: {api_secret}" \
  -F "logo=@logo.png"
```

Formatos: `jpg`, `jpeg`, `png`. Máx 2 MB.

---

## 4. Certificado digital

### `POST /empresa/certificado`

Actualiza el certificado digital usado para firmar XMLs.

```bash
curl -X POST https://tu-api.com/api/v1/empresa/certificado \
  -H "X-Api-Key: {api_key}" \
  -H "X-Api-Secret: {api_secret}" \
  -F "certificado=@nuevo-cert.pfx" \
  -F "contrasena_certificado=nueva_pass"
```

---

## 5. Sucursales

### `GET /sucursales`

Lista todas las sucursales con su conteo de series.

```json
{
  "estado": "exito",
  "datos": [
    {
      "id": 1,
      "nombre": "Sede Principal",
      "cod_local": "0000",
      "direccion": "AV. PRINCIPAL 123",
      "ubigeo": "150101",
      "is_principal": true,
      "is_active": true,
      "series_count": 3
    }
  ]
}
```

### `POST /sucursales`

**Body:**
| Campo | Tipo | Obligatorio |
|-------|------|-------------|
| `nombre` | string(100) | ✅ |
| `cod_local` | string(4) | ✅ formato `0000`-`9999` |
| `direccion` | string(500) | ✅ |
| `ubigeo` | string(6) | ✅ |
| `telefono` | string | ❌ |
| `email` | email | ❌ |
| `es_principal` | boolean | ❌ |

```bash
curl -X POST https://tu-api.com/api/v1/sucursales \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}" \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Sucursal Norte",
    "cod_local": "0001",
    "direccion": "AV. UNIVERSITARIA 5678",
    "ubigeo": "150135",
    "telefono": "+51 999111222"
  }'
```

### `GET /sucursales/{id}` · `PUT /sucursales/{id}` · `DELETE /sucursales/{id}`

Operaciones estándar de recurso.

---

## 6. Series

Una serie es el correlativo por tipo de documento (F001, B001, etc.) asignado a una sucursal.

**Tipos válidos:** `factura`, `boleta`, `nota_credito`, `nota_debito`, `guia_remision`, `retencion`, `percepcion`.

**Mapeo tipo → código SUNAT (Cat. 01):**
| Clave | Código SUNAT | Prefijo serie |
|-------|--------------|---------------|
| `factura` | `01` | F |
| `boleta` | `03` | B |
| `nota_credito` | `07` | F o B |
| `nota_debito` | `08` | F o B |
| `guia_remision` | `09` | T o EG |
| `retencion` | `20` | R |
| `percepcion` | `40` | P |

### `GET /series`

Filtros query: `sucursal_id`, `tipo` (clave amigable).

```bash
curl "https://tu-api.com/api/v1/series?tipo=factura" \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"
```

### `POST /series`

**Formato A — una serie:**
```json
{
  "tipo": "factura",
  "serie": "F001",
  "sucursal_id": 1,
  "correlativo_inicial": 0
}
```

**Formato B — varias series:**
```json
{
  "series": [
    { "tipo": "factura", "serie": "F001", "sucursal_id": 1 },
    { "tipo": "boleta", "serie": "B001", "sucursal_id": 1 },
    { "tipo": "nota_credito", "serie": "FC01", "sucursal_id": 1 },
    { "tipo": "nota_debito", "serie": "FD01", "sucursal_id": 1 }
  ]
}
```

**Regla:** La serie debe ser 4 caracteres, empezar con letra mayúscula y completar con letras/números.

### `GET /series/{id}` · `PUT /series/{id}`

---

## 7. Clientes

Registra catálogo de clientes para reutilizar en facturas/boletas.

### `GET /clientes`

Query params:
- `buscar` — busca en razón social / num documento
- `tipo_documento` — `1` (DNI), `6` (RUC), `4`, `7`, `0`
- `activo` — `1` / `0`

```bash
curl "https://tu-api.com/api/v1/clientes?buscar=acme&activo=1" \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"
```

### `POST /clientes`

```json
{
  "tipo_documento": "6",
  "numero_documento": "20555666777",
  "razon_social": "ACME CORP SAC",
  "direccion": "JR. ACME 456 - LIMA",
  "email": "facturas@acme.com",
  "telefono": "+51 12345678",
  "ubigeo": "150101"
}
```

**Catálogo tipo de documento (Cat. 06):**
| Código | Descripción |
|--------|-------------|
| `0` | Doc. trib. no dom. sin RUC |
| `1` | DNI |
| `4` | Carnet de extranjería |
| `6` | RUC |
| `7` | Pasaporte |
| `A` | Cédula diplomática |
| `B` | Doc. ident. país residencia |
| `C` | TIN |
| `D` | IN |
| `E` | TAM |

### `GET /clientes/{id}` · `PUT /clientes/{id}` · `DELETE /clientes/{id}`

---

## 8. Búsqueda RUC/DNI

### `GET /buscar-documento`

Primero busca en tu BD local (tabla `clients`), luego consulta SUNAT/RENIEC si no existe.

**Query:**
- `tipo` (obligatorio): `1` (DNI), `6` (RUC), `4`, `7`, `0`
- `numero` (obligatorio)

```bash
curl "https://tu-api.com/api/v1/buscar-documento?tipo=6&numero=20555666777" \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}"
```

**Respuesta (encontrado local):**
```json
{
  "estado": "exito",
  "datos": {
    "tipo_doc": "6",
    "num_doc": "20555666777",
    "razon_social": "ACME CORP SAC",
    "direccion": "JR. ACME 456 - LIMA",
    "fuente": "local"
  }
}
```

**Respuesta (desde SUNAT/RENIEC):**
```json
{
  "estado": "exito",
  "datos": {
    "tipo_doc": "6",
    "num_doc": "20555666777",
    "razon_social": "ACME CORP SAC",
    "direccion": "JR. ACME 456",
    "fuente": "sunat"
  }
}
```

**404 si no existe.**

---

## 9. Planes y suscripción

### `GET /planes` — pública (sin autenticación)

Lista todos los planes activos con sus límites y características.

**Respuesta:**
```json
{
  "estado": "exito",
  "mensaje": "OK",
  "datos": [
    {
      "slug": "free",
      "nombre": "Gratis",
      "precio_mensual": "0.00",
      "precio_anual": "0.00",
      "limites": { "documents_month": 30, "sucursales": 1, "team_members": 1 },
      "caracteristicas": ["facturacion", "boletas", "notas"]
    },
    {
      "slug": "pro",
      "nombre": "Profesional",
      "precio_mensual": "29.00",
      "precio_anual": "290.00",
      "limites": { "documents_month": 200, "sucursales": 3, "team_members": 5 },
      "caracteristicas": ["facturacion", "compras", "crm", "inventario_avanzado"]
    },
    {
      "slug": "business",
      "nombre": "Empresarial",
      "precio_mensual": "79.00",
      "precio_anual": "790.00",
      "limites": { "documents_month": -1, "sucursales": 10, "team_members": 15 },
      "caracteristicas": ["facturacion", "finanzas", "rrhh", "whatsapp_business"]
    }
  ]
}
```

> `-1` en los límites significa **ilimitado**.

### `GET /suscripcion`

Estado de tu suscripción actual + reporte de uso.

### `POST /suscripcion` — crear / activar

**Body:**
| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `plan_slug` | string | ✅ | `free` \| `pro` \| `business` |
| `ciclo_facturacion` | string | ❌ | `monthly` (default) \| `yearly` |
| `prueba` | boolean | ❌ | `true` activa trial de 14 días |
| `token` | string | ❌ | Token del gateway de pagos |

**Ejemplos:**

```bash
# Con trial de 14 días gratis
curl -X POST https://tu-api.com/api/v1/suscripcion \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}" \
  -H "Content-Type: application/json" \
  -d '{
    "plan_slug": "business",
    "ciclo_facturacion": "monthly",
    "prueba": true
  }'

# Con pago anual directo
curl -X POST https://tu-api.com/api/v1/suscripcion \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}" \
  -H "Content-Type: application/json" \
  -d '{
    "plan_slug": "pro",
    "ciclo_facturacion": "yearly",
    "token": "tok_xxx_de_tu_gateway"
  }'
```

### `PUT /suscripcion/cambiar-plan`

```json
{
  "plan_slug": "business",
  "ciclo_facturacion": "monthly"
}
```

### `PUT /suscripcion/cancelar`

Sin body. Mantiene acceso hasta el final del periodo facturado.

### `GET /suscripcion/pagos`

Historial de pagos.

### `GET /suscripcion/uso`

Consumo del mes actual.

```json
{
  "estado": "exito",
  "mensaje": "OK",
  "datos": {
    "documentos_usados": 45,
    "limite_mensual": 100,
    "porcentaje": 45,
    "resetea_en": "2026-05-01"
  }
}
```

---

## 🎯 Checklist de onboarding completo

```
1. [POST] /registro                       → obtener api_key + api_secret
2. [POST] /empresa/certificado (si actualizas)
3. [POST] /empresa/logo (opcional)
4. [POST] /sucursales                     → al menos 1 sucursal
5. [POST] /series                          → series por cada tipo de documento
6. [POST] /clientes                        → catálogo inicial (opcional)
7. Listo para emitir!                      → /facturas, /boletas, etc.
```

---

## 🔐 Autenticación en todas las llamadas

```http
X-Api-Key: tu_api_key_aqui
X-Api-Secret: tu_api_secret_aqui
```

**Nunca expongas** tu `api_secret` en frontend. Úsalo solo en backend/server-to-server.

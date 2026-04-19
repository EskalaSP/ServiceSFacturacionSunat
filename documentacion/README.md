# 📚 API-PRO — Documentación completa

> API REST de facturación electrónica multi-empresa para SUNAT Perú.

## 🗺️ Navegación

> Léelos en orden — los archivos están numerados según el flujo de uso.

### 🏗️ Setup inicial (configura tu empresa antes de emitir)
1. [**01-Configuracion.md**](./01-Configuracion.md) — Registro de empresa, tenant, sucursales, series, clientes, certificado, logo, suscripciones
2. [**02-Tasas-IGV.md**](./02-Tasas-IGV.md) — Configurar régimen tributario (general 18%, MYPE restaurantes, override manual)
3. [**03-NRUS.md**](./03-NRUS.md) — Guía completa NRUS: registro, operación real, ejemplos, errores comunes

### 📄 Comprobantes SUNAT (emisión)
4. [**04-Facturas.md**](./04-Facturas.md) — Facturas (Tipo 01) — CRUD + XML/CDR/PDF + pagos
5. [**05-Boletas.md**](./05-Boletas.md) — Boletas de venta (Tipo 03)
6. [**06-Notas-credito.md**](./06-Notas-credito.md) — Notas de crédito (Tipo 07) — anular/devolver/descontar
7. [**07-Notas-debito.md**](./07-Notas-debito.md) — Notas de débito (Tipo 08) — intereses/penalidades/cargos
8. [**08-Resumen-diario.md**](./08-Resumen-diario.md) — Resumen Diario de Boletas (envío en lote + anulación)
9. [**09-Anular.md**](./09-Anular.md) — Comunicaciones de Baja (Facturas/NC/ND/Retención/Percepción)
10. [**10-Guia-remision-RM.md**](./10-Guia-remision-RM.md) — Guías de remisión remitente (Tipo 09)
11. [**11-Guia-transportista.md**](./11-Guia-transportista.md) — Guías de remisión transportista (Tipo 31) — GRE REST OAuth2

### ⚙️ Operaciones (ciclo de vida)
12. [**12-Envio-manual.md**](./12-Envio-manual.md) — Envío manual a SUNAT (`enviar_automatico=false` + `POST /xxx/{id}/enviar`)
13. [**13-Actualizar.md**](./13-Actualizar.md) — Cómo actualizar documentos rechazados/pendientes
14. [**14-Consultar-CPE.md**](./14-Consultar-CPE.md) — Consulta integrada de comprobantes en SUNAT

### 📊 Análisis y reportes
15. [**15-Panel-de-control.md**](./15-Panel-de-control.md) — Dashboard: KPIs, gráficos, aging, alertas, reportes
16. [**16-Dashboard.md**](./16-Dashboard.md) — Dashboard (versión anterior, ver `15-Panel-de-control.md`)

### 🏦 Módulo SIRE (Registro de Compras)
17. [**17-Sire.md**](./17-Sire.md) — SIRE RCE completo: 25 endpoints, Postman collection
18. [**18-Plan-implementacion-sire.md**](./18-Plan-implementacion-sire.md) — Arquitectura y plan técnico SIRE

### 🚀 Despliegue
19. [**19-Cron-hosting.md**](./19-Cron-hosting.md) — Cómo correr jobs + scheduler en hosting compartido (`cron-jobs.php`)
20. [**20-Despliegue-VPS.md**](./20-Despliegue-VPS.md) — Guía completa: VPS Ubuntu 24 (Hostinger) + MySQL + Nginx + SSL + Supervisor + cron

---

## 🚀 Quick start

### 1. Registrar empresa y obtener credenciales

```bash
curl -X POST https://tu-api.com/api/v1/registro \
  -F "ruc=20100000001" \
  -F "razon_social=MI EMPRESA SAC" \
  -F "direccion=AV. PRINCIPAL 123" \
  -F "ubigeo=150101" \
  -F "sol_user=MODDATOS" \
  -F "sol_pass=MODDATOS" \
  -F "certificado=@cert.pfx" \
  -F "contrasena_certificado=secreto"

# → devuelve { "api_key": "...", "api_secret": "..." }
```

### 2. Configurar sucursal y serie

```bash
# Sucursal
curl -X POST https://tu-api.com/api/v1/sucursales \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}" \
  -H "Content-Type: application/json" \
  -d '{"nombre":"Principal","cod_local":"0000","direccion":"AV. PRINCIPAL 123","ubigeo":"150101","es_principal":true}'

# Series
curl -X POST https://tu-api.com/api/v1/series \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}" \
  -H "Content-Type: application/json" \
  -d '{"series":[
    {"tipo":"factura","serie":"F001","sucursal_id":1},
    {"tipo":"boleta","serie":"B001","sucursal_id":1}
  ]}'
```

### 3. Emitir tu primera factura

```bash
curl -X POST https://tu-api.com/api/v1/facturas \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}" \
  -H "Content-Type: application/json" \
  -d '{
    "serie": "F001",
    "fecha_emision": "2026-04-18",
    "cliente": {
      "tipo_doc": "6",
      "num_doc": "20555666777",
      "razon_social": "CLIENTE DEMO SAC"
    },
    "items": [{
      "descripcion": "SERVICIO DE CONSULTORIA",
      "unidad": "ZZ",
      "cantidad": 1,
      "precio_unitario": 1000.00
    }]
  }'
```

---

## 🔑 Autenticación

**Todos los endpoints** (excepto `/registro` y `/planes`) requieren:

```http
X-Api-Key: {tu_api_key}
X-Api-Secret: {tu_api_secret}
```

⚠️ Nunca expongas `api_secret` en frontend — úsalo solo en backend.

---

## 📋 Mapa completo de rutas (8 grupos)

### Configuración (14 rutas)
```
POST   /registro                      pública
GET    /planes                        pública

GET    /empresa
PUT    /empresa
POST   /empresa/logo
POST   /empresa/certificado

GET    /suscripcion                   ver plan actual
POST   /suscripcion                   crear/upgrade
PUT    /suscripcion/cambiar-plan
PUT    /suscripcion/cancelar
GET    /suscripcion/pagos
GET    /suscripcion/uso

GET|POST|PUT|DELETE /sucursales[/{id}]
GET|POST|PUT        /series[/{id}]
GET|POST|PUT|DELETE /clientes[/{id}]

GET    /buscar-documento              busca RUC/DNI (local + SUNAT/RENIEC)
```

### Facturas (11 rutas)
```
POST   /facturas
GET    /facturas
GET    /facturas/{id}
PUT    /facturas/{id}                 solo si no aceptada
GET    /facturas/{id}/xml
GET    /facturas/{id}/cdr
GET    /facturas/{id}/pdf
POST   /facturas/{id}/reenviar
POST   /facturas/{id}/pagos
GET    /facturas/{id}/pagos
DELETE /facturas/{id}/pagos/{paymentId}
```

### Boletas (12 rutas) — como facturas + `DELETE /boletas/{id}`

### Notas de Crédito (8 rutas)
```
POST   /notas-credito
GET    /notas-credito
GET    /notas-credito/{id}
PUT    /notas-credito/{id}
GET    /notas-credito/{id}/xml
GET    /notas-credito/{id}/cdr
GET    /notas-credito/{id}/pdf
POST   /notas-credito/{id}/reenviar
```

### Notas de Débito (8 rutas) — idéntico a NC

### Guías de Remisión (7 rutas)
```
POST   /guias-remision
GET    /guias-remision
GET    /guias-remision/{id}
PUT    /guias-remision/{id}
GET    /guias-remision/{id}/xml
GET    /guias-remision/{id}/pdf
GET    /guias-remision/{id}/estado
```

### Anulaciones / Resúmenes (8 rutas)
```
POST   /anulaciones
GET    /anulaciones
GET    /anulaciones/{id}
GET    /anulaciones/{id}/estado

POST   /resumenes
GET    /resumenes
GET    /resumenes/{id}/estado
GET    /resumenes/{id}/xml
GET    /resumenes/{id}/cdr
```

### Retenciones / Percepciones / Reversión (11 rutas)
```
POST|GET|GET /retenciones[/{id}[/xml|/cdr]]
POST|GET|GET /percepciones[/{id}[/xml|/cdr]]
POST         /reversiones
```

### Consultas (2 rutas)
```
POST   /consultar-cdr
GET    /consultar-cpe
```

### Panel / Reportes (11 + 7 rutas)
```
GET    /panel/                        vista completa mes
GET    /panel/indicadores
GET    /panel/estado-sunat
GET    /panel/cobranzas
GET    /panel/ventas-mensuales
GET    /panel/por-sucursal
GET    /panel/por-moneda
GET    /panel/clientes
GET    /panel/productos
GET    /panel/documentos-recientes
GET    /panel/alertas

GET    /reportes/registro-ventas
GET    /reportes/ventas-consolidado
GET    /reportes/notas
GET    /reportes/cobranzas
GET    /reportes/documentos-internos
GET    /reportes/por-cliente
GET    /reportes/por-sucursal
```

### Internos — no SUNAT (18 rutas)
```
POST|GET|GET|PUT|PUT|GET /cotizaciones[/{id}[/estado|/pdf]]
POST|GET|GET|PUT|GET     /notas-venta[/{id}[/pdf]]
POST|GET|DELETE          /notas-venta/{id}/pagos[/{paymentId}]
```

### SIRE (25 rutas)
Ver [`17-Sire.md`](./17-Sire.md) — módulo completo con RCE + tickets + uploads TUS + reconciliación.

---

## 📖 Catálogos SUNAT referenciados

Todos viven en [`config/sunat_catalogs.php`](../config/sunat_catalogs.php):

| Cat. | Uso | Documento |
|------|-----|-----------|
| `01` | Tipo documento | Todos |
| `05` | Tipos tributos | Todos |
| `06` | Tipo doc identidad | Cliente |
| `07` | Tipo afectación IGV | Items |
| `08` | Sistema cálculo ISC | Items con ISC |
| `09` | Tipo nota crédito | NC |
| `10` | Tipo nota débito | ND |
| `11` | Tipo valor venta | Resúmenes |
| `12` | Documentos relacionados | NC/ND |
| `16` | Tipo precio unitario | Items |
| `19` | Estado ítem resumen | Resúmenes |
| `20` | Motivos traslado GRE | Guías |
| `22` | Régimen percepciones | Percepción |
| `23` | Régimen retenciones | Retención |
| `51` | Tipo operación | Facturas/Boletas |
| `52` | Leyendas | Documentos |
| `53` | Cargos/descuentos | Items |
| `54` | Detracciones | Facturas detracción |
| `59` | Medios de pago | Detracción/pagos |

---

## 🔄 Estados SUNAT

Estados comunes en todos los documentos:

| Estado | Significado | Acción posible |
|--------|-------------|----------------|
| `pendiente` | Encolado | Esperar / PUT para cambios |
| `enviado` | En SUNAT | Esperar respuesta |
| `aceptado` | ✅ OK | Inmutable — usar NC/NB para cambios |
| `rechazado` | ❌ Error | PUT para corregir (auto-reenvía) |
| `anulado` | Anulada | Revisar NC si valor debe revertirse |
| `anulacion_en_proceso` | Baja en curso | Esperar |

---

## 🧭 Mini-guía por caso de uso

| Necesito... | Ir a |
|-------------|------|
| Registrar mi empresa | [01-Configuracion.md#1-registro-de-empresa](./01-Configuracion.md#1-registro-de-empresa) |
| Configurar mi régimen tributario | [02-Tasas-IGV.md](./02-Tasas-IGV.md) |
| Operar como NRUS | [03-NRUS.md](./03-NRUS.md) |
| Emitir factura | [04-Facturas.md#1-post-facturas](./04-Facturas.md#1-post-facturas--crear-factura) |
| Emitir boleta | [05-Boletas.md#1-post-boletas](./05-Boletas.md#1-post-boletas--crear-boleta) |
| Devolver una compra | [06-Notas-credito.md](./06-Notas-credito.md#ejemplo--devolución-parcial-cod_motivo06) |
| Cobrar intereses mora | [07-Notas-debito.md](./07-Notas-debito.md#ejemplo--intereses-por-mora) |
| Anular una boleta | [08-Resumen-diario.md](./08-Resumen-diario.md) |
| Anular una factura aceptada | [09-Anular.md#1-anulaciones](./09-Anular.md#1-anulaciones-comunicaciones-de-baja) |
| Emitir guía de transporte (remitente) | [10-Guia-remision-RM.md](./10-Guia-remision-RM.md#1-post-guias-remision--crear) |
| Emitir guía de transporte (transportista) | [11-Guia-transportista.md](./11-Guia-transportista.md) |
| Enviar comprobantes manualmente | [12-Envio-manual.md](./12-Envio-manual.md) |
| Corregir factura rechazada | [13-Actualizar.md](./13-Actualizar.md) |
| Verificar factura de proveedor | [14-Consultar-CPE.md#caso-1](./14-Consultar-CPE.md#caso-1--verificar-proveedor-antes-de-pagar) |
| Ver KPIs del negocio | [15-Panel-de-control.md](./15-Panel-de-control.md) |
| Cargar compras a SUNAT SIRE | [17-Sire.md](./17-Sire.md) |

---

## 🔧 Códigos HTTP comunes

| Código | Significado |
|--------|-------------|
| `200` | OK (operación síncrona exitosa) |
| `201` | Creado (nuevo recurso) |
| `202` | Aceptado (operación async, devuelve ticket/id) |
| `400` | Bad request |
| `401` | Credenciales API inválidas |
| `403` | Plan no incluye feature / gate bloqueado |
| `404` | Recurso no existe |
| `409` | Conflicto (ej: serie duplicada, archivo no listo) |
| `422` | Error de validación (SUNAT o local) |
| `429` | Rate limit excedido |
| `500` | Error interno |
| `502` | SUNAT no disponible |

---

## 📦 Colección Postman

- **`API SUNAT PRO V2.1 ✅✅✅✅✅.postman_collection.json`** — colección completa en español con cobertura 100% de las 162 rutas (240+ requests organizados en 25 folders por flujo de uso).
- Variables requeridas: `base_url`, `api_key`, `api_secret`, `ruc_empresa`, `periodo`.
- Todos los requests incluyen `Accept: application/json` en los headers.
- Para regenerar: `php tools/build-postman.php`. Las secciones modulares están en `tools/postman-sections/`.

## 📤 Formato de respuesta unificado (español)

Todas las respuestas de la API siguen este formato:

**Éxito:**
```json
{
  "estado": "exito",
  "mensaje": "OK",
  "datos": { ... },
  "meta": { ... }       // opcional (paginación u otros)
}
```

**Error:**
```json
{
  "estado": "error",
  "mensaje": "Error de validación",
  "errores": { "campo": ["mensaje"] }     // solo si aplica
}
```

**Paginado:**
```json
{
  "estado": "exito",
  "mensaje": "OK",
  "datos": {
    "datos": [...],
    "paginacion": {
      "pagina_actual": 1,
      "ultima_pagina": 5,
      "por_pagina": 15,
      "total": 67
    }
  }
}
```

Los mensajes de validación también vienen en español (locale `es`):
```json
{
  "estado": "error",
  "mensaje": "Error de validación",
  "errores": {
    "numero_documento": ["El campo numero documento es obligatorio."]
  }
}
```

## 📬 Soporte

- Para el módulo SIRE, revisar también [`18-Plan-implementacion-sire.md`](./18-Plan-implementacion-sire.md)
- Código fuente: `app/Http/Controllers/Api/V1/`, `app/Sire/`
- Configuración: `config/facturacion.php`, `config/sunat_catalogs.php`, `config/sire.php`

✨ **Documentación v1.1** — completa, en español, alineada con el formato de respuesta `{estado, mensaje, datos}`.

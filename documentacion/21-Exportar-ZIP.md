# 21 — Exportar comprobantes en ZIP

Descarga masiva de XMLs y/o PDFs de tus comprobantes en un único archivo ZIP, filtrable por rango de fechas, tipo de documento, sucursal y estado SUNAT.

---

## Tabla de contenidos

1. [GET /comprobantes/exportar-zip](#1-get-comprobantesexportar-zip)
2. [Parámetros](#2-parámetros)
3. [Estructura del ZIP](#3-estructura-del-zip)
4. [Ejemplos](#4-ejemplos)
5. [Errores comunes](#5-errores-comunes)
6. [Límites y recomendaciones](#6-límites-y-recomendaciones)

---

## 1. GET /comprobantes/exportar-zip

Genera y descarga un ZIP con los archivos XML y/o PDF de tus comprobantes.

```
GET /api/v1/comprobantes/exportar-zip
```

La respuesta es un archivo `.zip` descargable (no JSON). Si no hay archivos disponibles para los criterios dados, devuelve un error `404` en JSON.

---

## 2. Parámetros

| Parámetro | Tipo | Requerido | Default | Descripción |
|---|---|---|---|---|
| `fecha_desde` | `Y-m-d` | ✅ | — | Inicio del rango de emisión (ej: `2026-05-01`) |
| `fecha_hasta` | `Y-m-d` | ✅ | — | Fin del rango de emisión (ej: `2026-05-31`) |
| `tipo` | string | — | `xml` | Archivos a incluir: `xml`, `pdf` o `ambos` |
| `documentos` | string | — | todos | Tipos separados por coma (ver tabla abajo) |
| `sucursal_id` | int | — | — | Filtra por sucursal específica |
| `estado` | string | — | `todos` | `aceptado`, `pendiente`, `rechazado` o `todos` |

### Valores de `documentos`

| Valor | Tipo SUNAT | Descripción |
|---|---|---|
| `facturas` | 01 | Facturas electrónicas |
| `boletas` | 03 | Boletas de venta |
| `notas-credito` | 07 | Notas de crédito |
| `notas-debito` | 08 | Notas de débito |

Puedes combinar separando con comas: `facturas,boletas` o `facturas,boletas,notas-credito,notas-debito`.

> Solo se incluyen documentos que ya tienen el archivo generado en disco. Si un comprobante fue aceptado por SUNAT pero su XML no está en el servidor, ese documento se omite silenciosamente (no genera error).

---

## 3. Estructura del ZIP

Los archivos se organizan por tipo de documento y mes de emisión:

```
comprobantes_20123456789_20260501_20260531_xml.zip
│
├── facturas/
│   └── 2026-05/
│       ├── F001-000001.xml
│       ├── F001-000002.xml
│       └── F001-000003.xml
│
├── boletas/
│   └── 2026-05/
│       ├── B001-000001.xml
│       └── B001-000002.xml
│
├── notas-credito/
│   └── 2026-05/
│       └── FC01-000001.xml
│
└── notas-debito/
    └── 2026-05/
        └── FD01-000001.xml
```

**Nombre del archivo ZIP:**
```
comprobantes_{ruc}_{fecha_desde}_{fecha_hasta}_{tipo}.zip
```

Ejemplo: `comprobantes_20123456789_20260501_20260531_ambos.zip`

Si el rango cubre varios meses, aparecerán subcarpetas `2026-04/`, `2026-05/`, etc. dentro de cada tipo.

---

## 4. Ejemplos

### Descargar XMLs de todo el mes

```bash
curl -X GET "https://tu-api.com/api/v1/comprobantes/exportar-zip?fecha_desde=2026-05-01&fecha_hasta=2026-05-31" \
  -H "X-Api-Key: {api_key}" \
  -H "X-Api-Secret: {api_secret}" \
  --output comprobantes_mayo.zip
```

### Solo facturas y boletas aceptadas

```bash
curl -X GET "https://tu-api.com/api/v1/comprobantes/exportar-zip\
?fecha_desde=2026-05-01\
&fecha_hasta=2026-05-31\
&documentos=facturas,boletas\
&estado=aceptado" \
  -H "X-Api-Key: {api_key}" \
  -H "X-Api-Secret: {api_secret}" \
  --output facturas_boletas_aceptadas.zip
```

### XMLs + PDFs de una sucursal

```bash
curl -X GET "https://tu-api.com/api/v1/comprobantes/exportar-zip\
?fecha_desde=2026-05-01\
&fecha_hasta=2026-05-31\
&tipo=ambos\
&sucursal_id=2" \
  -H "X-Api-Key: {api_key}" \
  -H "X-Api-Secret: {api_secret}" \
  --output sucursal2_mayo.zip
```

### Solo PDFs de facturas del trimestre

```bash
curl -X GET "https://tu-api.com/api/v1/comprobantes/exportar-zip\
?fecha_desde=2026-04-01\
&fecha_hasta=2026-06-30\
&documentos=facturas\
&tipo=pdf" \
  -H "X-Api-Key: {api_key}" \
  -H "X-Api-Secret: {api_secret}" \
  --output facturas_pdf_trimestre.zip
```

### En Postman

| Campo | Valor |
|---|---|
| Método | `GET` |
| URL | `{{base_url}}/comprobantes/exportar-zip` |
| Param `fecha_desde` | `2026-05-01` |
| Param `fecha_hasta` | `2026-05-31` |
| Param `tipo` | `xml` |
| Headers | `X-Api-Key` / `X-Api-Secret` |
| **Send and Download** | ✅ (no "Send") |

> En Postman usa **Send and Download** (flecha al lado del botón Send) para guardar el archivo ZIP directamente.

---

## 5. Errores comunes

### `422` — Parámetros inválidos

```json
{
  "estado": "error",
  "mensaje": "Los parámetros fecha_desde y fecha_hasta son requeridos (formato Y-m-d)."
}
```

```json
{
  "estado": "error",
  "mensaje": "El rango máximo permitido es 366 días."
}
```

```json
{
  "estado": "error",
  "mensaje": "El parámetro tipo debe ser xml, pdf o ambos."
}
```

### `404` — Sin archivos

```json
{
  "estado": "error",
  "mensaje": "No se encontraron archivos para los criterios especificados. Verifique que los documentos tengan XML/PDF generados y estén dentro del rango de fechas."
}
```

Esto ocurre cuando:
- No hay documentos en el rango de fechas dado.
- Los documentos existen pero son `pendiente` (aún no fueron enviados a SUNAT y no tienen XML).
- El parámetro `estado=aceptado` y todos los documentos del período están rechazados o pendientes.
- Los archivos XML/PDF fueron generados pero ya no están en disco (servidor reconstruido, etc.).

---

## 6. Límites y recomendaciones

| Límite | Valor |
|---|---|
| Rango máximo de fechas | 366 días |
| Tipo de archivo | `xml`, `pdf` o `ambos` |
| Tamaño del ZIP | Sin límite (depende de la cantidad de archivos en disco) |

**Recomendaciones:**

- Para reportes contables, usa `estado=aceptado` para incluir solo los comprobantes válidos ante SUNAT.
- Para respaldos mensuales, programa la descarga el primer día del mes siguiente cuando todos los documentos ya hayan sido procesados.
- Si necesitas XMLs de muchos meses, prefiere descargas por mes (ej: `fecha_desde=2026-01-01&fecha_hasta=2026-01-31`) para evitar ZIPs demasiado grandes.
- Los PDFs solo están disponibles si fueron generados previamente vía `GET /facturas/{id}/pdf` (se generan on-demand y se cachean en disco).
- Para SIRE, exporta solo facturas aceptadas con `documentos=facturas&estado=aceptado` y usa el ZIP para cargar los XMLs al sistema de contabilidad.

# Reference — Todos los endpoints

Tabla compacta de las 162 rutas. Referencia rápida para Claude.

**Base URL**: `https://api.kodevo.es/sunat-api/api/v1`

**Autenticación (excepto públicos)**:
```http
Accept: application/json
Content-Type: application/json
X-Api-Key: {api_key}
X-Api-Secret: {api_secret}
```

---

## 🔓 Públicos (sin auth)

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/registro` | Registra nueva empresa. multipart/form-data con `ruc`, `razon_social`, `sol_user`, `sol_pass`, `certificado` (file). Devuelve `api_key` + `api_secret` |
| GET | `/planes` | Lista planes de suscripción (free, pro, business) |

## 🏢 Empresa / Tenant

| Método | Ruta | Body / Query | Descripción |
|---|---|---|---|
| GET | `/empresa` | — | Datos de la empresa + régimen + uso actual |
| PUT | `/empresa` | `razon_social`, `direccion`, `tax_regime`, `nrus_categoria`, `webhook_url`, `telefonos[]`, `emails[]`, `cuentas_bancarias[]`, `billeteras_digitales[]`, `mensaje_agradecimiento`, `mensaje_promocional` | Actualiza datos comerciales + régimen |
| POST | `/empresa/logo` | form-data: `logo` (jpg/png/webp, max 2MB) | Sube logo para PDFs |
| POST | `/empresa/certificado` | form-data: `certificado` (.pfx), `contrasena_certificado` | Renueva certificado |
| GET | `/buscar-documento` | `?tipo=6&numero=20512345678` | Busca RUC/DNI en BD local + SUNAT/RENIEC |

## 🏛️ Sucursales

| Método | Ruta | Body | Descripción |
|---|---|---|---|
| GET | `/sucursales` | — | Lista sucursales |
| POST | `/sucursales` | `nombre`, `cod_local` (4 dig), `direccion`, `ubigeo`, `es_principal`, `telefono`, `email` | Crear |
| GET | `/sucursales/{id}` | — | Ver |
| PUT | `/sucursales/{id}` | campos arriba | Actualizar |
| DELETE | `/sucursales/{id}` | — | Eliminar |

## 📋 Series (correlativos)

| Método | Ruta | Body | Descripción |
|---|---|---|---|
| GET | `/series` | `?tipo=factura&sucursal_id=1` | Lista series |
| POST | `/series` | `{series: [{tipo, serie, sucursal_id, correlativo_inicial}]}` | Crear (una o múltiples). Tipos: `factura`, `boleta`, `nota_credito`, `nota_debito`, `guia_remision`, `guia_transportista`, `retencion`, `percepcion` |
| GET | `/series/{id}` | — | Ver |
| PUT | `/series/{id}` | `correlativo`, `activo`, `sucursal_id` | Modificar correlativo actual o activar/desactivar |

## 👥 Clientes (catálogo local)

| Método | Ruta | Body | Descripción |
|---|---|---|---|
| GET | `/clientes` | `?buscar=&tipo_documento=&por_pagina=` | Listar con filtros |
| POST | `/clientes` | `tipo_documento`, `numero_documento`, `razon_social`, `nombre_comercial`, `direccion`, `email`, `telefono`, `ubigeo`. Alias soportados: `tipo_doc` → `tipo_documento`, `num_doc` → `numero_documento` | Crear (idempotente por tipo+numero) |
| GET | `/clientes/{id}` | — | Ver |
| PUT | `/clientes/{id}` | campos arriba | Actualizar |
| DELETE | `/clientes/{id}` | — | Eliminar |

> ⚠️ Los nombres canónicos son `tipo_documento`/`numero_documento` (aquí "cliente" es modelo propio). Desde v1 la API también acepta los alias `tipo_doc`/`num_doc` para consistencia con los otros endpoints — internamente se normalizan.

## 💳 Suscripción

| Método | Ruta | Body | Descripción |
|---|---|---|---|
| GET | `/suscripcion` | — | Estado actual + uso |
| POST | `/suscripcion` | `plan_slug` (free\|pro\|business), `ciclo_facturacion` (monthly\|yearly), `prueba` (bool), `token` | Crear/activar. Con `prueba=true` da trial 14 días |
| PUT | `/suscripcion/cambiar-plan` | `plan_slug`, `ciclo_facturacion` | Upgrade inmediato / downgrade al fin del periodo |
| PUT | `/suscripcion/cancelar` | — | Cancelar (acceso hasta fin de periodo) |
| GET | `/suscripcion/pagos` | — | Historial de pagos |
| GET | `/suscripcion/uso` | — | Contadores vs límites del plan |

---

## 📄 Facturas (tipo 01)

| Método | Ruta | Body / Notas | Descripción |
|---|---|---|---|
| POST | `/facturas` | ver schema abajo | Crear factura — encola envío a SUNAT (o `enviar_automatico: false` para guardar pendiente) |
| GET | `/facturas` | `?serie=F001&fecha_desde=&fecha_hasta=&sunat_status=&por_pagina=` | Listar |
| GET | `/facturas/{id}` | `?con=items,payments` | Ver con opción de eager-load |
| PUT | `/facturas/{id}` | mismos campos | Corregir doc rechazado/pendiente → auto-reenvía |
| GET | `/facturas/{id}/xml` | — | Descargar XML firmado |
| GET | `/facturas/{id}/cdr` | — | Descargar CDR (ZIP de SUNAT) |
| GET | `/facturas/{id}/pdf` | `?format=a4\|a5\|ticket-80\|ticket-58` | PDF representación impresa |
| POST | `/facturas/{id}/enviar` | — | Enviar manualmente (para docs en `pendiente`) |
| POST | `/facturas/{id}/reenviar` | — | Alias de `/enviar` (backward compat) |
| POST | `/facturas/{id}/pagos` | `monto`, `fecha`, `metodo`, `referencia` | Registrar pago |
| GET | `/facturas/{id}/pagos` | — | Listar pagos |
| DELETE | `/facturas/{id}/pagos/{paymentId}` | — | Eliminar pago |

### Schema mínimo POST /facturas

```json
{
  "serie": "F001",
  "fecha_emision": "2026-04-19",
  "fecha_vencimiento": "2026-05-19",
  "tipo_operacion": "0101",
  "tipo_moneda": "PEN",
  "forma_pago": "Contado",
  "cliente": {
    "tipo_doc": "6",              // obligatorio: RUC. La API rechaza DNI/CE — usar /boletas
    "num_doc": "20512345678",     // 11 dígitos, prefijo 10/15/17/20
    "razon_social": "CLIENTE DEMO SAC",
    "direccion": "AV. AREQUIPA 1234"
  },
  "items": [
    {
      "codigo": "P001",
      "descripcion": "Producto",
      "unidad": "NIU",
      "cantidad": 2,
      "precio_unitario": 118.00,
      "tip_afe_igv": "10"
    }
  ],
  "enviar_automatico": true
}
```

**Opcionales avanzados en el body de factura**: `cuotas[]` (crédito), `detraccion`, `percepcion`, `anticipos[]`, `descuentos_globales[]`, `guias[]` (relación con GRE), `extras`, `leyenda`, `observacion`.

---

## 🧾 Boletas (tipo 03)

Idéntico a facturas con estas diferencias:

| Método | Ruta | Notas |
|---|---|---|
| POST | `/boletas` | Soporta `solo_registro: true` para diferir envío al resumen diario. Soporta `tipo_doc: "0"` en cliente (consumidor final) |
| DELETE | `/boletas/{id}` | Solo si no está aceptada |

(Resto idéntico a facturas: GET, PUT, XML, CDR, PDF, enviar, reenviar, pagos)

---

## 🔄 Notas de Crédito (tipo 07) / Débito (tipo 08)

| Método | Ruta |
|---|---|
| POST | `/notas-credito` \| `/notas-debito` |
| GET | `/notas-credito` \| `/notas-debito` |
| GET | `/notas-{credito\|debito}/{id}` |
| PUT | `/notas-{credito\|debito}/{id}` |
| GET | `/notas-{credito\|debito}/{id}/xml\|cdr\|pdf` |
| POST | `/notas-{credito\|debito}/{id}/enviar\|reenviar` |

### Campos específicos NC/ND

```json
{
  "serie": "FC01",
  "fecha_emision": "2026-04-19",
  "tipo_moneda": "PEN",
  "cliente": { ... },
  "doc_afectado_tipo": "01",
  "doc_afectado_serie": "F001",
  "doc_afectado_correlativo": "123",
  "cod_motivo": "06",
  "des_motivo": "Devolución total",
  "items": [ ... ]
}
```

**Cat. 09 NC** (cod_motivo): `01`=anulación, `02`=anulación RUC erróneo, `03`=corrección descripción, `04`=descuento global, `05`=descuento ítem, `06`=devolución total, `07`=devolución parcial, `08`=bonificación, `09`=ajuste operación exportación, `10`=ajuste montos

**Cat. 10 ND** (cod_motivo): `01`=intereses por mora, `02`=aumento valor, `03`=penalidad/otros conceptos

---

## 📊 Resumen Diario de Boletas (RC)

| Método | Ruta | Body | Descripción |
|---|---|---|---|
| GET | `/resumenes` | `?mes=2026-04&tipo=envio\|anulacion&por_pagina=` | Listar |
| POST | `/resumenes` | `{fecha_resumen, anular: [{id, motivo}]}` | Envío de boletas pendientes OR anulación |
| GET | `/resumenes/{id}/estado` | — | Estado SUNAT del resumen + de cada boleta |
| GET | `/resumenes/{id}/xml\|cdr` | — | Archivos |
| POST | `/resumenes/{id}/enviar` | — | Enviar manual |

---

## ❌ Comunicación de Baja (RA)

| Método | Ruta | Body | Descripción |
|---|---|---|---|
| POST | `/anulaciones` | `{fecha_generacion, fecha_comunicacion, detalles: [{tipo_documento, serie, correlativo, motivo}]}` | Anular factura/NC/ND aceptadas |
| GET | `/anulaciones` | `?estado=&fecha_desde=&fecha_hasta=` | Listar |
| GET | `/anulaciones/{id}` | — | Ver |
| GET | `/anulaciones/{id}/estado` | — | Estado SUNAT |
| POST | `/anulaciones/{id}/enviar` | — | Manual (también para Reversiones RR) |

**Plazo**: 7 días desde emisión. **Boletas**: NO se anulan por RA — usar resumen diario de anulación.

---

## 🚚 Guía Remisión Remitente (GRR — tipo 09)

| Método | Ruta | Notas |
|---|---|---|
| POST | `/guias-remision` | Body con `destinatario`, `cod_traslado`, `mod_traslado`, `fecha_traslado`, `peso_total`, `partida_*`, `llegada_*`, `transportista`, `vehiculo`, `conductor`, `items` |
| GET | `/guias-remision` | — |
| GET | `/guias-remision/{id}` | — |
| PUT | `/guias-remision/{id}` | Solo si pendiente/rechazada |
| GET | `/guias-remision/{id}/xml\|pdf\|estado` | — |
| POST | `/guias-remision/{id}/enviar` | Manual |

---

## 🚛 Guía Remisión Transportista (GRT — tipo 31)

| Método | Ruta | Body extra |
|---|---|---|
| POST | `/guias-remision-transportista` | atajo (forza tipo_documento=31). Requiere `remitente` + `doc_relacionado` |
| POST | `/guias-remision` | con `"tipo_documento": "31"` también funciona |

**Schema mínimo GRT**:
```json
{
  "serie": "V001",
  "fecha_emision": "2026-04-19",
  "remitente": { "tipo_doc": "6", "num_doc": "20xxx", "razon_social": "..." },
  "destinatario": { "tipo_doc": "6", "num_doc": "20yyy", "razon_social": "..." },
  "doc_relacionado": { "tipo_codigo": "04", "numero": "T001-1" },
  "cod_traslado": "01",
  "mod_traslado": "01",
  "fecha_traslado": "2026-04-20",
  "peso_total": 500.00,
  "und_peso_total": "KGM",
  "partida_ubigeo": "150101",
  "partida_direccion": "...",
  "llegada_ubigeo": "040101",
  "llegada_direccion": "...",
  "vehiculo": { "placa": "ABC-123", "nro_circulacion": "TUC-12345" },
  "conductor": [{ "tipo_doc": "1", "num_doc": "12345678", "nombres": "JUAN", "apellidos": "PEREZ", "licencia": "Q12345678" }],
  "items": [{ "codigo": "M001", "descripcion": "Mercancia", "unidad": "NIU", "cantidad": 10 }]
}
```

---

## 💵 Retenciones (tipo 20) / Percepciones (tipo 40)

| Método | Ruta (retencion) | Ruta (percepcion) |
|---|---|---|
| POST | `/retenciones` | `/percepciones` |
| GET | `/retenciones` | `/percepciones` |
| GET | `/retenciones/{id}` | `/percepciones/{id}` |
| GET | `/retenciones/{id}/xml\|cdr` | `/percepciones/{id}/xml\|cdr` |
| POST | `/retenciones/{id}/enviar` | `/percepciones/{id}/enviar` |

**Retenciones — body**:
```json
{
  "serie": "R001",
  "fecha_emision": "2026-04-19",
  "proveedor": { "tipo_doc": "6", "num_doc": "20xxx", "razon_social": "..." },
  "regimen": "01",
  "tasa": 3,
  "documentos": [{
    "tipo_doc": "01", "num_doc": "F001-100",
    "fecha_emision": "2026-04-15", "imp_total": 1180.00, "moneda": "PEN",
    "pagos": [{ "fecha": "2026-04-19", "imp_total": 1180.00 }],
    "fecha_retencion": "2026-04-19"
  }]
}
```

**Percepciones — body**: idéntico cambiando `proveedor`→`cliente`, `pagos`→`cobros`, `fecha_retencion`→`fecha_percepcion`.

Solo emiten Agentes de Retención/Percepción designados por SUNAT.

---

## ⚙️ Reversión (RR)

| Método | Ruta | Body |
|---|---|---|
| POST | `/reversiones` | `{fecha_generacion, detalles: [{tipo_documento: "20"\|"40", serie, correlativo, motivo}]}` |

Anula retenciones/percepciones aceptadas. Se almacena en `voided_documents` con prefijo `RR-`. Para consultar estado: `/anulaciones/{id}/estado`.

---

## 📤 Envío Manual

Todos los endpoints `POST /xxx/{id}/enviar` para disparar envío cuando el doc se creó con `enviar_automatico: false`:

- `/facturas/{id}/enviar`
- `/boletas/{id}/enviar`
- `/notas-credito/{id}/enviar`
- `/notas-debito/{id}/enviar`
- `/guias-remision/{id}/enviar` (GRR y GRT)
- `/resumenes/{id}/enviar`
- `/anulaciones/{id}/enviar` (RA y RR)
- `/retenciones/{id}/enviar`
- `/percepciones/{id}/enviar`

---

## 🔍 Consulta CPE / CDR en SUNAT

| Método | Ruta | Query / Body |
|---|---|---|
| GET | `/consultar-cpe` | `?ruc_emisor=&tipo_doc=&serie=&correlativo=&fecha_emision=&monto_total=` |
| POST | `/consultar-cdr` | `{ruc_emisor, tipo_doc, serie, correlativo}` |

Sirve para verificar facturas de proveedores antes de pagar.

---

## 📈 Panel de Control

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/panel` | Vista completa del mes |
| GET | `/panel/indicadores` | KPIs (ventas, crecimiento) |
| GET | `/panel/estado-sunat` | Breakdown estado SUNAT |
| GET | `/panel/cobranzas` | Aging de cuentas por cobrar |
| GET | `/panel/ventas-mensuales` | Serie 12 meses |
| GET | `/panel/por-sucursal` | Ranking sucursales |
| GET | `/panel/por-moneda` | Desglose PEN/USD |
| GET | `/panel/clientes` | Top clientes |
| GET | `/panel/productos` | Top productos |
| GET | `/panel/documentos-recientes` | Últimos 20 docs |
| GET | `/panel/alertas` | Rechazos + vencimientos |

## 📊 Reportes

| Método | Ruta | Query |
|---|---|---|
| GET | `/reportes/registro-ventas` | `?desde=&hasta=` — reporte oficial SUNAT |
| GET | `/reportes/ventas-consolidado` | `?desde=&hasta=` |
| GET | `/reportes/notas` | `?desde=&hasta=` NC + ND |
| GET | `/reportes/cobranzas` | `?al_dia=` |
| GET | `/reportes/documentos-internos` | cotizaciones + notas venta |
| GET | `/reportes/por-cliente` | `?desde=&hasta=&limit=` |
| GET | `/reportes/por-sucursal` | `?desde=&hasta=` |

## 🏦 SIRE (Registro de Compras SUNAT)

```
POST /sire/activar                                  — credenciales SIRE
POST /sire/desactivar
GET  /sire/periodos
GET  /sire/rce/constancia
GET  /sire/rce/{periodo}/propuesta
POST /sire/rce/{periodo}/aceptar-propuesta
POST /sire/rce/{periodo}/registrar-preliminar
GET  /sire/rce/{periodo}/resumen
POST /sire/rce/{periodo}/reemplazar-propuesta
POST /sire/rce/{periodo}/no-domiciliados
POST /sire/rce/{periodo}/complementar-propuesta
POST /sire/rce/{periodo}/ajustes-posteriores/{variant}/cargar|enviar|descargar|eliminar
GET  /sire/rce/{periodo}/comprobantes[/{id}]
GET  /sire/rce/{periodo}/reconciliar
POST /sire/rce/{periodo}/reconciliar-async
GET  /sire/rce/{periodo}/reconciliaciones[/{id}]
GET  /sire/tickets[/{numTicket}][/archivo]
POST /sire/tickets/{numTicket}/refrescar
```

Donde `{periodo}` = YYYYMM (ej. `202604`) y `{variant}` = `actual | anterior`.

---

## 📝 Documentos Internos (NO SUNAT)

No se envían a SUNAT, solo tracking local.

### Cotizaciones

| Método | Ruta |
|---|---|
| POST | `/cotizaciones` |
| GET | `/cotizaciones` |
| GET | `/cotizaciones/{id}` |
| PUT | `/cotizaciones/{id}` |
| PUT | `/cotizaciones/{id}/estado` (borrador\|enviada\|aceptada\|rechazada\|vencida) |
| GET | `/cotizaciones/{id}/pdf` |

### Notas de Venta

| Método | Ruta |
|---|---|
| POST | `/notas-venta` |
| GET | `/notas-venta` |
| GET | `/notas-venta/{id}` |
| PUT | `/notas-venta/{id}` |
| GET | `/notas-venta/{id}/pdf` |
| POST/GET/DELETE | `/notas-venta/{id}/pagos[/{paymentId}]` |

---

## 📚 Catálogos que referenciar

| Cat. | Fichero código | Valores críticos |
|---|---|---|
| 01 (tipo doc) | `config/sunat_catalogs.php` | `01`=factura, `03`=boleta, `07`=NC, `08`=ND, `09`=GRR, `31`=GRT, `20`=retención, `40`=percepción |
| 06 (doc identidad) | — | `0`=otros, `1`=DNI, `4`=CE, `6`=RUC, `7`=pasaporte |
| 07 (afectación IGV) | — | `10`=gravado, `20`=exonerado, `30`=inafecto, `40`=exportación |
| 09 (motivos NC) | — | `01`-`10` |
| 10 (motivos ND) | — | `01`-`03` |
| 20 (motivos traslado) | — | `01`-`18` |
| 51 (tipo operación) | — | `0101`=venta interna, `0113`=NRUS, `0200`=exportación |

---

## 🔑 Plan limits (FREE / PRO / BUSINESS)

| Plan | Docs/mes | Sucursales | Team | Productos | AI msgs |
|---|---|---|---|---|---|
| `free` | 30 | 1 | 1 | 100 | 10 |
| `pro` | 200 | 3 | 5 | ∞ | 100 |
| `business` | ∞ | 10 | 15 | ∞ | ∞ |

`-1` en respuestas JSON significa **ilimitado**.

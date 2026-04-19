# 🚚 Guías de Remisión — Remitente (Tipo 09)

> Base URL: `https://tu-api.com/api/v1`
> Guía Electrónica de Remisión — Formato 2022 SUNAT.
> Serie: normalmente `T001` (cualquiera de 4 caracteres).

## 📑 Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/guias-remision` | Crear guía |
| `GET` | `/guias-remision` | Listar |
| `GET` | `/guias-remision/{id}` | Ver |
| `PUT` | `/guias-remision/{id}` | Actualizar (si pendiente o rechazada) |
| `GET` | `/guias-remision/{id}/xml` | XML firmado |
| `GET` | `/guias-remision/{id}/pdf` | PDF |
| `GET` | `/guias-remision/{id}/estado` | Consultar estado en SUNAT |

---

## 🎯 Conceptos

La GRE (Guía Remisión Electrónica) Remitente es emitida por el **remitente del bien** (quien lo transfiere). Tiene 2 **modalidades de traslado**:

| `mod_traslado` | Descripción |
|----------------|-------------|
| `01` | Transporte **público** (empresa de transporte) — requiere datos del transportista |
| `02` | Transporte **privado** (del remitente o adquiriente) — requiere vehículo + conductor |

### Motivos (`cod_traslado`) — Catálogo 20 SUNAT

| Código | Descripción |
|--------|-------------|
| `01` | Venta |
| `02` | Compra |
| `04` | Traslado entre establecimientos de la misma empresa |
| `08` | Importación |
| `09` | Exportación |
| `13` | Otros |
| `14` | Venta sujeta a confirmación del comprador |
| `18` | Traslado emisor itinerante CP |
| `19` | Traslado a zona primaria |

---

## 1. `POST /guias-remision` — Crear

### Ejemplo completo — transporte privado

```json
{
  "serie": "T001",
  "fecha_emision": "2026-04-18",
  "observacion": "Pedido #12345",

  "destinatario": {
    "tipo_doc": "6",
    "num_doc": "20555666777",
    "razon_social": "ACME CORP SAC"
  },

  "cod_traslado": "01",
  "mod_traslado": "02",
  "fecha_traslado": "2026-04-20",
  "peso_total": 150.50,
  "und_peso_total": "KGM",
  "num_bultos": 5,

  "llegada_ubigeo": "150135",
  "llegada_direccion": "AV. SAN BORJA SUR 123",
  "llegada_cod_local": "0001",

  "partida_ubigeo": "150101",
  "partida_direccion": "AV. LIMA 456",
  "partida_cod_local": "0000",

  "vehiculo": {
    "placa": "ABC-123",
    "secundarios": [
      { "placa": "DEF-456" }
    ]
  },

  "conductor": {
    "tipo_doc": "1",
    "num_doc": "12345678",
    "tipo": "Principal",
    "nombres": "JUAN CARLOS",
    "apellidos": "PEREZ LOPEZ",
    "licencia": "Q12345678"
  },

  "items": [
    {
      "codigo": "P001",
      "descripcion": "LAPTOP HP PAVILION 15",
      "cantidad": 2,
      "unidad": "NIU"
    }
  ]
}
```

### Ejemplo completo — transporte público

```json
{
  "serie": "T001",
  "fecha_emision": "2026-04-18",

  "destinatario": {
    "tipo_doc": "6",
    "num_doc": "20555666777",
    "razon_social": "ACME CORP SAC"
  },

  "cod_traslado": "01",
  "mod_traslado": "01",
  "fecha_traslado": "2026-04-20",
  "peso_total": 300.0,
  "und_peso_total": "KGM",

  "llegada_ubigeo": "080101",
  "llegada_direccion": "CUSCO - PLAZA DE ARMAS",

  "partida_ubigeo": "150101",
  "partida_direccion": "LIMA - AV. JAVIER PRADO",

  "transportista": {
    "tipo_doc": "6",
    "num_doc": "20333444555",
    "razon_social": "TRANSPORTES SEGUROS EIRL",
    "nro_mtc": "MTC-00123"
  },

  "items": [
    { "descripcion": "CAJAS DE PRODUCTOS", "cantidad": 50, "unidad": "BX" }
  ]
}
```

### Campos obligatorios

| Campo | Tipo | Notas |
|-------|------|-------|
| `serie` | string(4) | Ej: `T001`, `EG01` |
| `fecha_emision` | date | |
| `destinatario.tipo_doc` | `1` DNI \| `6` RUC | |
| `destinatario.num_doc` | string(max 15) | |
| `destinatario.razon_social` | string(max 255) | |
| `cod_traslado` | string | Catálogo 20 (arriba) |
| `mod_traslado` | `01` \| `02` | Público / Privado |
| `fecha_traslado` | date | Fecha estimada del traslado |
| `peso_total` | numeric > 0 | |
| `llegada_ubigeo` | string(6) | |
| `llegada_direccion` | string(max 500) | |
| `partida_ubigeo` | string(6) | |
| `partida_direccion` | string(max 500) | |
| `items[].descripcion` | string(max 500) | |
| `items[].cantidad` | numeric > 0 | |

### Campos condicionales

| Condición | Campos requeridos |
|-----------|-------------------|
| `mod_traslado=01` (público) — sin M1L | `transportista.*` |
| `mod_traslado=02` (privado) — sin M1L | `vehiculo.placa` + `conductor.*` |

**M1L** = "Traslado en vehículos categoría M1 o L" — excepción que permite omitir transportista/conductor. Se indica:

```json
{
  "indicadores": ["SUNAT_Envio_IndicadorTrasladoVehiculoCategoriaM1L"]
}
```

### Otros indicadores disponibles

```json
{
  "indicadores": [
    "SUNAT_Envio_IndicadorTransbordoProgramado",
    "SUNAT_Envio_IndicadorRetornoVehiculoEnvaseVacio",
    "SUNAT_Envio_IndicadorRetornoVehiculoVacio"
  ]
}
```

### Tercero (proveedor intermedio)

```json
{
  "tercero": {
    "tipo_doc": "6",
    "num_doc": "20111222333",
    "razon_social": "DISTRIBUIDORA LIMA SAC"
  }
}
```

### Comprador distinto al destinatario

```json
{
  "comprador": {
    "tipo_doc": "6",
    "num_doc": "20999888777",
    "razon_social": "COMPRADOR FINAL SAC"
  }
}
```

### Múltiples conductores

```json
{
  "conductores": [
    {
      "tipo_doc": "1",
      "num_doc": "12345678",
      "tipo": "Principal",
      "nombres": "JUAN",
      "apellidos": "PEREZ",
      "licencia": "Q12345678"
    },
    {
      "tipo_doc": "1",
      "num_doc": "87654321",
      "tipo": "Secundario",
      "nombres": "CARLOS",
      "apellidos": "GOMEZ",
      "licencia": "Q87654321"
    }
  ]
}
```

### Respuesta (201)

```json
{
  "estado": "exito",
  "mensaje": "Guía creada y encolada para envío a SUNAT.",
  "datos": {
    "id": 88,
    "tipo_documento": "09",
    "serie": "T001",
    "correlativo": "00000088",
    "numero_completo": "T001-88",
    "fecha_emision": "2026-04-18",
    "fecha_traslado": "2026-04-20",
    "destinatario": {...},
    "mod_traslado": "02",
    "peso_total": "150.50",
    "sunat_status": "pendiente",
    "sunat_ticket": null
  }
}
```

> ⚠️ **SUNAT procesa las GRE asíncronamente** — la respuesta inmediata incluye `sunat_ticket` después del primer intento de envío. Consultar con `GET /estado`.

---

## 2. `GET /guias-remision` — Listar

Filtros query: `serie`, `correlativo`, `destinatario_doc`, `estado`, `mod_traslado`, `cod_traslado`, `desde`, `hasta`.

```bash
curl "https://tu-api.com/api/v1/guias-remision?estado=aceptado&desde=2026-04-01" \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

---

## 3. `GET /guias-remision/{id}` — Ver

---

## 4. `PUT /guias-remision/{id}` — Actualizar

**Solo permitido si `sunat_status in ['pendiente', 'rechazado']`.** Si ya está aceptada, SUNAT no permite cambios (emitir una nueva).

```bash
curl -X PUT https://tu-api.com/api/v1/guias-remision/88 \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}" \
  -H "Content-Type: application/json" \
  -d '{
    "fecha_traslado": "2026-04-22",
    "vehiculo": { "placa": "XYZ-987" }
  }'
```

**Error si ya aceptada:**
```json
{
  "estado": "error",
  "mensaje": "Solo guías pendientes o rechazadas pueden editarse."
}
```

---

## 5. `GET /guias-remision/{id}/xml`

```bash
curl -o guia.xml https://tu-api.com/api/v1/guias-remision/88/xml \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

---

## 6. `GET /guias-remision/{id}/pdf`

```bash
curl -o guia.pdf https://tu-api.com/api/v1/guias-remision/88/pdf \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

Formatos: `a4` (default), `a5`.

---

## 7. `GET /guias-remision/{id}/estado`

SUNAT procesa las guías en forma asíncrona — devuelve un ticket. Este endpoint consulta ese ticket y actualiza el estado local.

```bash
curl https://tu-api.com/api/v1/guias-remision/88/estado \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

**Respuesta:**
```json
{
  "estado": "exito",
  "datos": {
    "id": 88,
    "numero_completo": "T001-88",
    "sunat_ticket": "1604394234789",
    "sunat_status": "aceptado",
    "sunat_code": "0",
    "sunat_description": "La guía ha sido aceptada"
  }
}
```

---

## 🎯 Flujos típicos

### Flujo A — Venta con traslado propio

```
1. POST /facturas                              → F001-123 ACEPTADA
2. POST /guias-remision                        → T001-88 (transporte privado, mi vehículo)
   guias: [ { tipo_doc: "09", nro_doc: "T001-88" } ]  ← en la factura
3. GET /guias-remision/88/estado               → esperar aceptación
4. GET /guias-remision/88/pdf                  → imprimir + entregar al conductor
```

### Flujo B — Venta con transportista contratado

```
1. POST /facturas                      → F001-200
2. POST /guias-remision                → T001-89 con mod_traslado=01 y transportista
3. GET /guias-remision/89/estado       → esperar aceptación
4. PDF y entregar al transportista
```

### Flujo C — Traslado entre sucursales

```
1. POST /guias-remision con:
   - cod_traslado: "04" (traslado entre establecimientos)
   - destinatario: mi propia empresa (tenant RUC + razón social)
   - mod_traslado: "02" (privado)
```

### Flujo D — Corrección de guía rechazada

```
1. Guía T001-90 RECHAZADA (ej: ubigeo inválido)
2. PUT /guias-remision/90 con ubigeo corregido
3. Sistema reenvía automáticamente
4. GET /guias-remision/90/estado → aceptado
```

---

## ⚙️ Reglas importantes

- **Unidades de peso**: `KGM` (kilogramo, default), `TNE` (tonelada métrica)
- **Placa vehicular**: formato `ABC-123` o `ABC-1234`
- **Licencia del conductor**: obligatoria para transporte privado
- **RUC transportista**: 11 dígitos; debe ser una empresa autorizada
- **Nro MTC**: opcional pero recomendado (número de registro ante el MTC)
- La GRE está **desvinculada** de la factura — no hay validación cruzada en la API (solo referencial)
- Los cambios después de aceptada requieren **emitir nueva guía** (SUNAT no permite editar)

---

## 📋 Estados SUNAT específicos para guías

| Estado | Significado |
|--------|-------------|
| `pendiente` | No enviado |
| `enviado` | Enviado, esperando ticket |
| `procesando` | SUNAT procesando |
| `aceptado` | ✅ |
| `rechazado` | ❌ — ver `sunat_description` |

---

## 🔗 Relacionados

- Facturas/Boletas pueden declarar guías relacionadas con `guias[{"tipo_doc":"09","nro_doc":"T001-88"}]`
- No existe "anulación" de guía remisión — si hay error, corregir con PUT (si no aceptada) o emitir nueva

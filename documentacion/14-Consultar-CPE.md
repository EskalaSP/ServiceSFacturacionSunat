# 🔍 Consulta Integrada de Comprobantes (CPE)

> Base URL: `https://tu-api.com/api/v1`
> Consulta el estado oficial en SUNAT de cualquier comprobante (propio o de terceros).

## 📑 Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/consultar-cpe` | Consulta CPE en SUNAT (API oficial) |
| `POST` | `/consultar-cdr` | Consulta CDR (Constancia de Recepción) |

---

## 1. `GET /consultar-cpe` — API de Consulta Integrada SUNAT

Usa la API oficial "Consulta Integrada de Comprobantes de Pago Electrónicos" de SUNAT (distinta de SIRE) para verificar el estado real de un comprobante.

### ⚙️ Requisitos previos

**Credenciales API SUNAT** generadas desde **Clave SOL → Empresas → Credenciales de API SUNAT**:
- `client_id` (UUID)
- `client_secret` (string)

Al registrar tu app en SOL, debes haber seleccionado la URI: **`Consulta Integrada CPE`**.

**⚠️ No confundir con SIRE** — son dos APIs distintas de SUNAT que requieren URIs distintas al registrar tu app (puedes activar ambas en la misma app).

Configura las credenciales en tu tenant:
```bash
curl -X PUT https://tu-api.com/api/v1/empresa \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "9cae24a9-10d7-48b0-bee0-e94bd56947e3",
    "client_secret": "xxxxxxxxxxxxxxxxxxxx"
  }'
```

### Query params

| Campo | Tipo | Obligatorio | Notas |
|-------|------|-------------|-------|
| `ruc_emisor` | string(11) | ❌ | Default: RUC del tenant |
| `tipo_doc` | string | ✅ | `01`, `03`, `04`, `07`, `08`, `R1`, `R7` |
| `serie` | string(4) | ✅ | |
| `correlativo` | integer | ✅ | |
| `fecha_emision` | string `dd/mm/yyyy` | ✅ | |
| `monto` | numeric | ✅ | Total del comprobante |

### Ejemplo — Consultar mi propia factura

```bash
curl "https://tu-api.com/api/v1/consultar-cpe?tipo_doc=01&serie=F001&correlativo=123&fecha_emision=18/04/2026&monto=7052.86" \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

### Ejemplo — Consultar factura de un proveedor

```bash
curl "https://tu-api.com/api/v1/consultar-cpe?ruc_emisor=20555666777&tipo_doc=01&serie=F001&correlativo=1&fecha_emision=15/04/2026&monto=250.00" \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

> **Regla SUNAT:** Solo puedes consultar comprobantes donde tu RUC (el del `client_id`) haya sido emisor **o** receptor. No puedes consultar comprobantes de terceros donde no participas.

### Respuesta (200)

```json
{
  "estado": "exito",
  "datos": {
    "encontrado": true,
    "estado_cp": "1",
    "estado_cp_descripcion": "ACEPTADO",
    "estado_ruc": "00",
    "estado_ruc_descripcion": "ACTIVO",
    "cond_domi_ruc": "00",
    "cond_domi_ruc_descripcion": "HABIDO",
    "observaciones": [],
    "consultado_por_ruc": "20100000001",
    "ruc_emisor": "20555666777",
    "tipo_doc": "01",
    "serie": "F001",
    "correlativo": 1
  }
}
```

### Catálogo — Tipo de comprobante

| Código | Descripción |
|--------|-------------|
| `01` | Factura |
| `03` | Boleta de venta |
| `04` | Liquidación de compra |
| `07` | Nota de crédito |
| `08` | Nota de débito |
| `R1` | Recibo por honorarios |
| `R7` | Nota de crédito de recibos |

### Catálogo — Estado del comprobante (`estado_cp`)

| Código | Descripción |
|--------|-------------|
| `0` | NO EXISTE — comprobante no informado |
| `1` | ACEPTADO — aceptado por SUNAT |
| `2` | ANULADO — comunicado en una baja |
| `3` | AUTORIZADO — con autorización de imprenta |
| `4` | NO AUTORIZADO — no autorizado por imprenta |

### Catálogo — Estado del contribuyente (`estado_ruc`)

| Código | Descripción |
|--------|-------------|
| `00` | ACTIVO |
| `01` | BAJA PROVISIONAL |
| `02` | BAJA PROV. POR OFICIO |
| `03` | SUSPENSION TEMPORAL |
| `10` | BAJA DEFINITIVA |
| `11` | BAJA DE OFICIO |
| `22` | INHABILITADO-VENT.UNICA |

### Catálogo — Condición de domicilio (`cond_domi_ruc`)

| Código | Descripción |
|--------|-------------|
| `00` | HABIDO |
| `09` | PENDIENTE |
| `11` | POR VERIFICAR |
| `12` | NO HABIDO |
| `20` | NO HALLADO |

### Errores

**401 — Credenciales inválidas:**
```json
{
  "estado": "error",
  "mensaje": "SUNAT rechazó las credenciales (client_id/client_secret). Verifica que las generaste desde Clave SOL → Empresas → Credenciales de API SUNAT → Consulta Integrada CPE. No hay entorno beta, solo producción.",
  "errores": { "sunat_code": "unauthorized_client" }
}
```

**422 — Tenant sin credenciales:**
```json
{
  "estado": "error",
  "mensaje": "El tenant no tiene configurado client_id/client_secret para consultar SUNAT."
}
```

---

## 2. `POST /consultar-cdr` — Consulta CDR

Consulta el CDR (Constancia de Recepción) de un documento en SUNAT. Requiere las credenciales `sol_user`/`sol_pass` del tenant (ya configuradas).

### Body

```json
{
  "tipo_documento": "01",
  "serie": "F001",
  "correlativo": 123
}
```

| Campo | Tipo | Notas |
|-------|------|-------|
| `tipo_documento` | string | `01`, `03`, `07`, `08` |
| `serie` | string(4) | |
| `correlativo` | integer | |

### Ejemplo

```bash
curl -X POST https://tu-api.com/api/v1/consultar-cdr \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}" \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_documento": "01",
    "serie": "F001",
    "correlativo": 123
  }'
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": {
    "accepted": true,
    "code": "0",
    "description": "La Factura numero F001-123 ha sido aceptada",
    "notes": [],
    "cdr_base64": "UEsDBBQAAAgI..."
  }
}
```

---

## 🎯 Diferencias: CPE vs CDR vs CDR-local

| Consulta | Endpoint | Qué devuelve |
|----------|----------|--------------|
| **CPE** (oficial SUNAT) | `GET /consultar-cpe` | Estado actual del comprobante **en SUNAT** |
| **CDR SUNAT** (tiempo real) | `POST /consultar-cdr` | Respuesta SUNAT al envío del XML |
| **CDR local** (archivo guardado) | `GET /facturas/{id}/cdr` | CDR previamente descargado |

**¿Cuál usar?**
- Ya emitiste el documento y quieres ver el CDR guardado: `GET /facturas/{id}/cdr`
- Quieres consultar estado actual en SUNAT (por si fue anulado): `GET /consultar-cpe`
- Perdiste el CDR y necesitas volver a obtenerlo: `POST /consultar-cdr`

---

## 🎯 Casos de uso

### Caso 1 — Verificar proveedor antes de pagar

```bash
# ¿La factura que me emitió mi proveedor es válida?
curl "https://tu-api.com/api/v1/consultar-cpe?ruc_emisor=20555666777&tipo_doc=01&serie=F001&correlativo=100&fecha_emision=10/04/2026&monto=1180.00" \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

Si `estado_cp=1` (ACEPTADO) y `estado_ruc=00` (ACTIVO) y `cond_domi_ruc=00` (HABIDO) → proveedor OK.

Si `cond_domi_ruc=12` (NO HABIDO) → ⚠️ no podrás usar esa factura para crédito fiscal.

### Caso 2 — Verificar mi propio comprobante

```bash
# ¿Mi factura F001-123 sigue vigente en SUNAT?
curl "https://tu-api.com/api/v1/consultar-cpe?tipo_doc=01&serie=F001&correlativo=123&fecha_emision=18/04/2026&monto=7052.86" \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

Si `estado_cp=2` (ANULADO) → alguien ejecutó una comunicación de baja.

### Caso 3 — Auditoría masiva

```javascript
// Consultar estado real de N facturas emitidas el mes pasado
for (const factura of misFacturasDelMesPasado) {
  const res = await fetch(`/consultar-cpe?tipo_doc=01&serie=${factura.serie}&correlativo=${factura.correlativo}&fecha_emision=${factura.fecha}&monto=${factura.total}`, { headers });
  const data = await res.json();
  if (data.data.estado_cp !== '1') {
    alertas.push({ factura, estadoSunat: data.data.estado_cp_descripcion });
  }
}
```

---

## ⚙️ Rate limits

- El API SUNAT tiene limite por RUC — tu API lo respeta internamente
- Token OAuth se cachea 1h automáticamente (no hay que pedirlo en cada consulta)
- Recomendado: batch processing con delays si consultas >100 docs

---

## 🔗 Relacionados

- Ver [`01-Configuracion.md`](./01-Configuracion.md) — cómo configurar `client_id`/`client_secret`
- Ver [`04-Facturas.md`](./04-Facturas.md) — estado local vs estado SUNAT
- Ver [`17-Sire.md`](./17-Sire.md) — análisis masivo de compras RCE (módulo diferente)

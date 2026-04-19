# 📊 Panel de Control (Dashboard)

> Base URL: `https://tu-api.com/api/v1/panel`
> 11 endpoints especializados de estadísticas y analítica para dashboards empresariales.

## 📑 Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/panel/` | Vista completa del mes |
| `GET` | `/panel/indicadores` | KPIs: hoy/semana/mes/año + crecimiento |
| `GET` | `/panel/estado-sunat` | Breakdown SUNAT + rechazos recientes |
| `GET` | `/panel/cobranzas` | Aging de cuentas por cobrar |
| `GET` | `/panel/ventas-mensuales` | Gráfico 12 meses vs año anterior |
| `GET` | `/panel/por-sucursal` | Ranking por sucursal |
| `GET` | `/panel/por-moneda` | Desglose PEN/USD |
| `GET` | `/panel/clientes` | Top + nuevos + recurrentes |
| `GET` | `/panel/productos` | Top venta/cantidad + tipo IGV |
| `GET` | `/panel/documentos-recientes` | Feed últimos 20 |
| `GET` | `/panel/alertas` | Rechazos, vencimientos, series |

## 📑 Reportes complementarios

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/reportes/registro-ventas` | Registro de ventas (PLE) |
| `GET` | `/reportes/ventas-consolidado` | Consolidado por periodo |
| `GET` | `/reportes/notas` | NC/ND del periodo |
| `GET` | `/reportes/cobranzas` | Detalle cobranzas |
| `GET` | `/reportes/documentos-internos` | Cotizaciones + notas venta |
| `GET` | `/reportes/por-cliente` | Ventas agrupadas por cliente |
| `GET` | `/reportes/por-sucursal` | Ventas por sucursal |

---

## 1. `GET /panel/` — Vista completa del mes

Periodo flexible: por mes o rango.

### Query params

- `mes` — `yyyymm` (ej: `202604`)
- `desde` / `hasta` — rango personalizado en `yyyy-mm-dd` (alternativa a `mes`)
- Si no se envía nada: mes actual

```bash
curl "https://tu-api.com/api/v1/panel?mes=202604" \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": {
    "periodo": {
      "mes": "202604",
      "desde": "2026-04-01",
      "hasta": "2026-04-30",
      "label": "Abril 2026"
    },
    "resumen_mes": {
      "facturas": { "cantidad": 45, "monto": 85000.00, "igv": 12960.00 },
      "boletas": { "cantidad": 128, "monto": 12500.00, "igv": 1906.78 },
      "notas_credito": { "cantidad": 3, "monto": 1500.00 },
      "notas_debito": { "cantidad": 1, "monto": 200.00 },
      "internas": { "cantidad": 12, "monto": 3400.00 },
      "total_neto": 98400.00
    },
    "chart_diario": [
      { "fecha": "2026-04-01", "ventas": 1200.00, "cantidad": 5 },
      { "fecha": "2026-04-02", "ventas": 2300.00, "cantidad": 8 }
    ],
    "top_productos": [
      { "codigo": "P001", "descripcion": "LAPTOP HP", "cantidad": 15, "monto": 44250 }
    ],
    "top_clientes": [
      { "num_doc": "20555666777", "razon_social": "ACME SAC", "cantidad": 8, "monto": 25000 }
    ],
    "comparacion": {
      "mes_actual": 98400.00,
      "mes_anterior": 87200.00,
      "crecimiento_porcentaje": 12.8,
      "crecimiento_monto": 11200.00
    }
  }
}
```

---

## 2. `GET /panel/indicadores` — KPIs

Métricas financieras clave con comparaciones temporales.

```bash
curl https://tu-api.com/api/v1/panel/indicadores \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": {
    "hoy": { "cantidad": 8, "monto": 3200.00 },
    "ayer": { "cantidad": 10, "monto": 4100.00 },
    "semana_actual": { "cantidad": 45, "monto": 22500.00 },
    "mes_actual": { "cantidad": 173, "monto": 98400.00 },
    "mes_anterior": { "cantidad": 165, "monto": 87200.00 },
    "anio_actual": { "cantidad": 820, "monto": 450000.00 },
    "anio_anterior": { "cantidad": 780, "monto": 420000.00 },
    "crecimiento": {
      "vs_ayer": -22.0,
      "vs_mes_anterior": 12.8,
      "vs_anio_anterior": 7.1
    }
  }
}
```

---

## 3. `GET /panel/estado-sunat` — Breakdown SUNAT

Monitoreo en tiempo real del estado de los envíos a SUNAT.

```bash
curl https://tu-api.com/api/v1/panel/estado-sunat \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": {
    "por_tipo": {
      "01_facturas": { "pendiente": 2, "enviado": 5, "aceptado": 120, "rechazado": 3, "anulado": 1 },
      "03_boletas":  { "pendiente": 8, "enviado": 12, "aceptado": 450, "rechazado": 1, "anulado": 5 },
      "07_nc":       { "pendiente": 0, "enviado": 1, "aceptado": 18, "rechazado": 0, "anulado": 0 },
      "08_nd":       { "pendiente": 0, "enviado": 0, "aceptado": 4, "rechazado": 0, "anulado": 0 }
    },
    "por_estado": {
      "pendiente": 10,
      "enviado": 18,
      "aceptado": 592,
      "rechazado": 4,
      "anulado": 6
    },
    "observados": [
      { "tipo": "01", "numero": "F001-115", "codigo": "3xxx", "descripcion": "Observación..." }
    ],
    "tasa_aceptacion": 98.67,
    "ultimos_rechazos": [
      {
        "id": 301,
        "tipo_documento": "01",
        "numero_completo": "F001-300",
        "sunat_code": "2325",
        "sunat_description": "El documento electrónico ingresado ha sido alterado",
        "fecha_emision": "2026-04-18"
      }
    ]
  }
}
```

---

## 4. `GET /panel/cobranzas` — Aging

Análisis de cuentas por cobrar con envejecimiento.

```bash
curl https://tu-api.com/api/v1/panel/cobranzas \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": {
    "resumen": {
      "pagado": { "cantidad": 150, "monto": 85000.00 },
      "parcial": { "cantidad": 12, "monto": 15000.00, "cobrado": 8000, "pendiente": 7000 },
      "pendiente": { "cantidad": 25, "monto": 32000.00 }
    },
    "aging": {
      "por_vencer": { "cantidad": 8, "monto": 18000.00 },
      "vencido_0_30": { "cantidad": 10, "monto": 8500.00 },
      "vencido_31_60": { "cantidad": 5, "monto": 3000.00 },
      "vencido_61_90": { "cantidad": 2, "monto": 1500.00 },
      "vencido_mas_90": { "cantidad": 0, "monto": 0 }
    },
    "top_deudores": [
      {
        "num_doc": "20555666777",
        "razon_social": "ACME SAC",
        "docs_pendientes": 3,
        "monto_pendiente": 8500.00,
        "dias_mora_max": 45
      }
    ]
  }
}
```

---

## 5. `GET /panel/ventas-mensuales` — Chart 12 meses vs YoY

```bash
curl https://tu-api.com/api/v1/panel/ventas-mensuales \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": {
    "meses": [
      {
        "mes": "202505", "label": "May 2025",
        "actual": 75000.00, "anterior": 68000.00,
        "cantidad_actual": 140, "cantidad_anterior": 125,
        "crecimiento_porcentaje": 10.3
      },
      { "mes": "202506", "label": "Jun 2025", "actual": 82000, "anterior": 71000, "crecimiento_porcentaje": 15.5 }
    ],
    "promedio_mensual": 85400.00,
    "total_anio": 1024800.00
  }
}
```

---

## 6. `GET /panel/por-sucursal` — Ranking

```bash
curl https://tu-api.com/api/v1/panel/por-sucursal \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": [
    {
      "sucursal_id": 1,
      "nombre": "Sede Principal",
      "cod_local": "0000",
      "cantidad_docs": 180,
      "monto_total": 65000.00,
      "share_porcentaje": 66.06
    },
    {
      "sucursal_id": 2,
      "nombre": "Sucursal Norte",
      "cod_local": "0001",
      "cantidad_docs": 95,
      "monto_total": 33400.00,
      "share_porcentaje": 33.94
    }
  ]
}
```

---

## 7. `GET /panel/por-moneda`

```bash
curl https://tu-api.com/api/v1/panel/por-moneda \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": {
    "PEN": { "cantidad": 265, "monto": 85000.00, "igv": 12960.00 },
    "USD": { "cantidad": 10, "monto": 3500.00, "igv": 534.00 },
    "EUR": { "cantidad": 0, "monto": 0, "igv": 0 }
  }
}
```

---

## 8. `GET /panel/clientes`

```bash
curl https://tu-api.com/api/v1/panel/clientes \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": {
    "top_20": [
      { "num_doc": "20555666777", "razon_social": "ACME SAC", "cantidad_docs": 12, "monto_total": 35000 }
    ],
    "clientes_nuevos": [
      { "num_doc": "20123456789", "razon_social": "NUEVO SAC", "primera_compra": "2026-04-05", "monto": 2500 }
    ],
    "clientes_recurrentes": {
      "cantidad": 85,
      "porcentaje": 62.0
    },
    "total_registrados": 137
  }
}
```

---

## 9. `GET /panel/productos`

```bash
curl https://tu-api.com/api/v1/panel/productos \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": {
    "top_por_venta": [
      { "codigo": "P001", "descripcion": "LAPTOP HP PAVILION 15", "cantidad_vendida": 15, "monto_total": 44250 }
    ],
    "top_por_cantidad": [
      { "codigo": "P002", "descripcion": "MOUSE LOGITECH", "cantidad_vendida": 230, "monto_total": 13570 }
    ],
    "por_tipo_igv": [
      { "tip_afe_igv": "10", "descripcion": "Gravado - Operación Onerosa", "cantidad_items": 420, "monto_total": 88000 },
      { "tip_afe_igv": "20", "descripcion": "Exonerado - Operación Onerosa", "cantidad_items": 35, "monto_total": 2500 }
    ]
  }
}
```

---

## 10. `GET /panel/documentos-recientes`

```bash
curl https://tu-api.com/api/v1/panel/documentos-recientes \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": [
    {
      "tipo_documento": "01",
      "tipo_descripcion": "Factura",
      "numero_completo": "F001-123",
      "cliente": "ACME SAC",
      "monto_total": 7052.86,
      "fecha_emision": "2026-04-18T14:30:00Z",
      "sunat_status": "aceptado"
    }
  ]
}
```

Devuelve los últimos 20 documentos emitidos (union de todas las tablas).

---

## 11. `GET /panel/alertas`

Alertas operativas críticas.

```bash
curl https://tu-api.com/api/v1/panel/alertas \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}"
```

### Respuesta

```json
{
  "estado": "exito",
  "datos": {
    "rechazados_7_dias": {
      "cantidad": 3,
      "docs": [
        { "numero_completo": "F001-300", "sunat_code": "2325", "fecha": "2026-04-17" }
      ]
    },
    "vencen_proximos_7_dias": {
      "cantidad": 5,
      "monto": 8500.00,
      "docs": [
        { "numero_completo": "F001-250", "cliente": "ACME SAC", "fecha_vencimiento": "2026-04-22", "saldo": 2500 }
      ]
    },
    "vencidos": {
      "cantidad": 12,
      "monto": 15000.00
    },
    "pendientes_envio_sunat": {
      "cantidad": 8,
      "docs": []
    },
    "series_por_agotar": [
      {
        "tipo": "factura",
        "serie": "F001",
        "correlativo_actual": 99000000,
        "restantes": 1000000,
        "alerta": "⚠️ Serie por agotar"
      }
    ]
  }
}
```

---

## 📊 Reportes complementarios

### `GET /reportes/registro-ventas`

Reporte estilo PLE (Programa Libros Electrónicos) para el mes.

**Query:** `mes=yyyymm` (obligatorio), `formato=json|csv|xlsx`

```bash
curl "https://tu-api.com/api/v1/reportes/registro-ventas?mes=202604&formato=xlsx" \
  -H "X-Api-Key: {k}" -H "X-Api-Secret: {s}" \
  -o registro-ventas-202604.xlsx
```

### `GET /reportes/ventas-consolidado`

**Query:** `desde`, `hasta`, `moneda`, `sucursal_id`.

### `GET /reportes/notas`

Lista NC/ND del periodo con relación al documento afectado.

### `GET /reportes/cobranzas`

Detalle de cuentas por cobrar con aging.

### `GET /reportes/documentos-internos`

Cotizaciones + Notas de venta (no SUNAT).

### `GET /reportes/por-cliente`

**Query:** `cliente_doc`, `desde`, `hasta`.

### `GET /reportes/por-sucursal`

Breakdown detallado por sucursal.

---

## 🎯 Uso típico en una UI

```javascript
// Dashboard principal — carga en paralelo
Promise.all([
  fetch('/panel/indicadores', { headers }),
  fetch('/panel/ventas-mensuales', { headers }),
  fetch('/panel/estado-sunat', { headers }),
  fetch('/panel/alertas', { headers }),
  fetch('/panel/documentos-recientes', { headers }),
]).then(renderDashboard);

// Sección específica
fetch('/panel/cobranzas', { headers }).then(renderAging);
fetch('/panel/por-sucursal', { headers }).then(renderRanking);
```

---

## ⚙️ Notas de performance

- Todos los endpoints del panel usan **raw SQL optimizado** (UNION ALL entre tablas de docs)
- Se aprovechan índices compuestos en `(tenant_id, fecha_emision)` y `(tenant_id, sunat_status)`
- Algunas respuestas están limitadas a Top 20 para mantener tiempos de respuesta <200ms
- Para periodos muy grandes (>100k docs) considerar usar `/reportes/*` que retornan archivos

---

## 🔗 Relacionados

- Indicadores financieros profundos → [`04-Facturas.md`](./04-Facturas.md) + agregaciones manuales
- Análisis de compras (RCE) → [`17-Sire.md`](./17-Sire.md) módulo SIRE
- Consultas CPE puntuales → [`14-Consultar-CPE.md`](./14-Consultar-CPE.md)

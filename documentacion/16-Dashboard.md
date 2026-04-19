# 📊 PANEL / DASHBOARD (API)

## 🚀 Endpoints disponibles

| Endpoint                          | Propósito                                                                                                   | Parámetros                        |
| --------------------------------- | ----------------------------------------------------------------------------------------------------------- | --------------------------------- |
| `GET /panel`                      | 📅 Vista general del periodo (mensual o rango personalizado)                                                | `?mes=YYYY-MM` o `?desde=&hasta=` |
| `GET /panel/indicadores`          | 📈 KPIs clave: hoy, ayer, semana, mes, año + crecimiento YoY + ticket promedio + impuestos                  | —                                 |
| `GET /panel/estado-sunat`         | 📊 Estado de documentos: aceptado / rechazado / pendiente / anulado + tasa de aceptación + últimos rechazos | `?mes=` o `?desde=&hasta=`        |
| `GET /panel/cobranzas`            | 💰 Aging de cuentas por cobrar + top 10 deudores                                                            | —                                 |
| `GET /panel/ventas-mensuales`     | 📉 Comparativa últimos 12 meses vs año anterior + crecimiento %                                             | —                                 |
| `GET /panel/por-sucursal`         | 🏢 Ranking de sucursales + participación (%) + desglose por tipo de doc                                     | `?mes=` o `?desde=&hasta=`        |
| `GET /panel/por-moneda`           | 💱 Ventas por moneda (PEN, USD, otras) + IGV por moneda                                                     | `?mes=` o `?desde=&hasta=`        |
| `GET /panel/clientes`             | 👥 Top 20 clientes + nuevos + recurrentes + total registrados                                               | `?mes=` o `?desde=&hasta=`        |
| `GET /panel/productos`            | 🛒 Top productos (ventas/cantidad) + distribución por tipo de IGV                                           | `?mes=` o `?desde=&hasta=`        |
| `GET /panel/documentos-recientes` | 🧾 Feed de últimos documentos (facturas, boletas, etc.)                                                     | `?limit=N (máx 100)`              |
| `GET /panel/alertas`              | 🚨 Alertas: rechazos, vencimientos, pendientes, series por agotar                                           | —                                 |

---

## 🧠 ¿Qué incluye este dashboard?

### 📈 Indicadores clave

* Ventas acumuladas por periodo
* Comparativas (YoY, crecimiento %)
* Ticket promedio
* Impuestos recaudados:

  * IGV
  * ICBPER
  * Operaciones gratuitas

---

### 📊 Control SUNAT

* Estado de documentos:

  * ✅ Aceptados
  * ❌ Rechazados
  * ⏳ Pendientes
  * 🚫 Anulados
* 📉 Tasa de aceptación
* ⚠️ Últimos errores con detalle

---

### 💰 Cobranzas inteligentes

* Clasificación de deuda:

  * Por vencer
  * 0–30 días
  * 31–60 días
  * 61–90 días
  * +90 días
* 🧾 Top deudores

---

### 📉 Análisis de ventas

* Últimos 12 meses
* Comparación con año anterior
* Promedio mensual
* Tendencias de crecimiento

---

### 🏢 Segmentación del negocio

#### Por sucursal

* Ranking por ventas
* Participación (%) en total
* Mix factura vs boleta

#### Por moneda

* PEN / USD / otras
* IGV separado por moneda

---

### 👥 Clientes

* Top 20 clientes
* Nuevos vs recurrentes
* Total base de clientes

---

### 🛒 Productos

* Más vendidos (monto)
* Más vendidos (cantidad)
* Distribución tributaria:

  * Gravado
  * Exonerado
  * Inafecto
  * Gratuito
  * Exportación

---

### 🧾 Actividad reciente

* Últimos documentos emitidos
* Todos los tipos incluidos
* Ideal para dashboards en tiempo real

---

### 🚨 Alertas automáticas

* Rechazos últimos 7 días
* Documentos por vencer
* Documentos vencidos
* Pendientes de envío a SUNAT
* Series próximas a agotarse

---

## ⚡ Ejemplos rápidos

### Obtener dashboard mensual

```http
GET /api/v1/panel?mes=2026-04
```

### Rango personalizado

```http
GET /api/v1/panel?desde=2026-04-01&hasta=2026-04-15
```

### Últimos documentos

```http
GET /api/v1/panel/documentos-recientes?limit=20
```

---

## 🎯 Resumen

* 📊 Dashboard completo listo para frontend
* ⚡ Optimizado para métricas en tiempo real
* 🔍 Ideal para BI, reportes y monitoreo
* 🧠 Centraliza ventas, SUNAT, clientes y cobranzas

---

✨ Perfecto para implementar dashboards tipo **SaaS, ERP o sistema contable moderno**.

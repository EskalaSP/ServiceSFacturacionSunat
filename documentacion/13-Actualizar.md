# 📄 ACTUALIZAR COMPROBANTES (API)

## 📌 Endpoints disponibles

| Documento       | Endpoint                   | Se puede editar si...        |
| --------------- | -------------------------- | ---------------------------- |
| Factura         | `PUT /facturas/{id}`       | ❌ NO está aceptado           |
| Boleta          | `PUT /boletas/{id}`        | ❌ NO está aceptado           |
| Nota de Crédito | `PUT /notas-credito/{id}`  | ❌ NO está aceptado           |
| Nota de Débito  | `PUT /notas-debito/{id}`   | ❌ NO está aceptado           |
| Guía Remisión   | `PUT /guias-remision/{id}` | ✅ Solo pendiente o rechazado |

---

## ⚙️ ¿Qué hace el `PUT` internamente?

1. 🔒 Valida que `sunat_status != 'aceptado'`
   → Si no se cumple, responde **422: "No se puede editar"**

2. ✏️ Permite modificar:

   * Cliente
   * Fechas
   * Ítems
   * Moneda
   * Forma de pago
   * Observación

3. 🧮 Si envías `items[]`:

   * Recalcula automáticamente:

     * IGV, ISC, ICBPER
     * Totales
   * Reemplaza completamente los ítems

4. 🔄 Resetea estado:

   * `sunat_status → pendiente`
   * Limpia códigos de error

5. 🚀 Reenvía automáticamente a SUNAT:

   ```
   SendDocumentToSunat::dispatch
   ```

---

## 📊 Matriz de decisiones según estado

| Estado actual            | ¿Qué puedes hacer?                              |
| ------------------------ | ----------------------------------------------- |
| pendiente / enviado      | ✅ PUT (editar y reenviar)                       |
| rechazado (2xxx)         | ✅ PUT (corregir y reenviar)                     |
| aceptado (código 0)      | ❌ No editable → emitir Nota de Crédito          |
| aceptado con observación | ❌ No editable → Nota de Crédito + nueva emisión |
| anulado                  | ❌ No se puede modificar                         |

---

## 🔁 Flujo típico de recuperación de error

1. Emites `F001-123`
2. SUNAT → ❌ **RECHAZADO (ej: 2325)**
3. Haces `PUT /facturas/{id}` corrigiendo datos
4. Sistema:

   * Recalcula
   * Resetea estado
   * Reenvía automáticamente
5. SUNAT → ✅ **ACEPTADO**

---

## 🧪 Ejemplo 1: Factura rechazada → corregir y reenviar

### 📍 Escenario

SUNAT rechaza por RUC inválido.

### 1️⃣ Verificar estado

```http
GET /api/v1/facturas/45
```

```json
{
  "sunat_status": "rechazado",
  "sunat_code": "2325",
  "client_num_doc": "2060012345"
}
```

---

### 2️⃣ Corregir con PUT

```http
PUT /api/v1/facturas/45
```

```json
{
  "cliente": {
    "tipo_doc": "6",
    "num_doc": "20600123456",
    "razon_social": "EMPRESA DEMO SAC",
    "direccion": "AV. AREQUIPA 123 - LIMA"
  },
  "observacion": "Corrección de RUC del cliente"
}
```

📌 **Nota:** Solo envías los campos a modificar.

---

### 3️⃣ Respuesta

```json
{
  "mensaje": "Factura actualizada y reenviada a SUNAT.",
  "sunat_status": "pendiente"
}
```

---

### 4️⃣ Resultado final

```json
{
  "sunat_status": "aceptado",
  "sunat_code": "0"
}
```

---

## 🧪 Ejemplo 2: Actualizar ítems (recalculo automático)

```http
PUT /api/v1/facturas/45
```

```json
{
  "items": [
    {
      "codigo": "P001",
      "descripcion": "LAPTOP HP PAVILION 15",
      "cantidad": 2,
      "mto_valor_unitario": 2500.00
    },
    {
      "codigo": "P002",
      "descripcion": "MOUSE LOGITECH",
      "cantidad": 3,
      "mto_valor_unitario": 50.00
    }
  ]
}
```

💡 La API recalcula TODO automáticamente:

* Totales
* Impuestos
* Precios
* Leyenda

---

## 🧪 Ejemplo 3: Boleta (cambio simple)

```http
PUT /api/v1/boletas/78
```

```json
{
  "fecha_vencimiento": "2026-05-15",
  "forma_pago": "Credito"
}
```

---

## 🚫 Ejemplo 4: Factura aceptada (NO permitido)

```http
PUT /api/v1/facturas/40
```

```json
{
  "mensaje": "No se puede editar una factura aceptada por SUNAT."
}
```

✅ **Solución:**

```
POST /api/v1/notas-credito
```

---

## 🧪 Ejemplo 5: Guía de remisión (rechazada)

```http
PUT /api/v1/guias-remision/12
```

```json
{
  "envio": {
    "fecha_traslado": "2026-04-20",
    "transportista": {
      "num_doc": "20555666777",
      "razon_social": "TRANSPORTES SEGUROS EIRL",
      "placa": "ABC-123"
    }
  }
}
```

---

## 🧠 Resumen rápido

* ❌ No puedes editar documentos **aceptados**
* ✅ Sí puedes editar **pendientes o rechazados**
* 🔁 El sistema **reenvía automáticamente**
* 🧮 No necesitas calcular totales
* 📄 Usa Nota de Crédito para correcciones legales

---

✨ Documento listo para usar en README o documentación técnica.

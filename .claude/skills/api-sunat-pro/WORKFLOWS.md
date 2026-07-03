# Workflows — Flujos típicos paso a paso

Cómo combinar los endpoints en flujos completos. Guía para que Claude entienda el uso real.

---

## Workflow 1: Onboarding (registrar empresa desde cero)

```
1. POST /registro (pública)
   ↓ devuelve api_key + api_secret
2. GUARDAR credenciales en lugar seguro (env vars)
3. POST /sucursales
   → al menos 1 sucursal principal (cod_local=0000, es_principal=true)
4. POST /series
   → múltiples: F001 factura, B001 boleta, FC01 NC, FD01 ND
5. POST /clientes (opcional)
   → precargar catálogo inicial
6. ✅ Listo para emitir
```

**Ejemplo curl**:
```bash
# Paso 1
RESP=$(curl -X POST $BASE_URL/registro \
  -F "ruc=20100000001" \
  -F "razon_social=MI EMPRESA SAC" \
  -F "direccion=AV. LIMA 123" \
  -F "ubigeo=150101" \
  -F "sol_user=MODDATOS" \
  -F "sol_pass=MODDATOS" \
  -F "certificado=@cert-beta.pfx" \
  -F "contrasena_certificado=123456" \
  -F "tax_regime=general")

API_KEY=$(echo $RESP | jq -r .datos.api_key)
API_SECRET=$(echo $RESP | jq -r .datos.api_secret)

# Paso 3
curl -X POST $BASE_URL/sucursales \
  -H "X-Api-Key: $API_KEY" -H "X-Api-Secret: $API_SECRET" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"nombre":"Principal","cod_local":"0000","direccion":"AV. LIMA 123","ubigeo":"150101","es_principal":true}'

# Paso 4
curl -X POST $BASE_URL/series \
  -H "X-Api-Key: $API_KEY" -H "X-Api-Secret: $API_SECRET" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"series":[
    {"tipo":"factura","serie":"F001","sucursal_id":1},
    {"tipo":"boleta","serie":"B001","sucursal_id":1},
    {"tipo":"nota_credito","serie":"FC01","sucursal_id":1},
    {"tipo":"nota_debito","serie":"FD01","sucursal_id":1}
  ]}'
```

---

## Workflow 2: Emitir factura B2B — flujo completo

```
1. POST /facturas
   ↓ devuelve id=123, sunat.estado="enviado" (encolado)
2. (background) Job envía a SUNAT → status pasa a "aceptado" o "rechazado"
3. Opción A: webhook → recibes POST con status final
   Opción B: polling → GET /facturas/123 cada N segundos
4. Una vez "aceptado":
   - GET /facturas/123/pdf?format=a4   → descargar PDF
   - GET /facturas/123/xml             → XML firmado (archivar)
   - GET /facturas/123/cdr             → CDR SUNAT (archivar)
5. (opcional) enviar PDF al cliente por correo
```

**Tip**: el `numero_completo` (F001-000123) ya viene en la respuesta del paso 1 — puedes mostrarlo en UI inmediatamente aunque aún no esté aceptado.

---

## Workflow 3: Emitir boleta con resumen diario (NRUS o bodega)

NRUS + cualquier negocio que emite muchas boletas prefiere agruparlas en un RC diario:

```
Durante el día:
  ... N veces ...
  POST /boletas con "solo_registro": true
  ↓ queda en status="pendiente" (NO se envía individualmente)

Al cierre del día:
  POST /resumenes { "fecha_resumen": "2026-04-19" }
  ↓ agrupa todas las boletas pendientes del día y las envía en lote
  ↓ devuelve ticket → consultar con /resumenes/{id}/estado
```

**Ventaja**: solo 1 envío a SUNAT por día en vez de N. SUNAT permite RC hasta 7 días después.

---

## Workflow 4: Corregir factura rechazada

```
1. Cliente recibe webhook "document.rejected" o polling detecta status="rechazado"
2. GET /facturas/123 → revisar sunat.descripcion y sunat.codigo
3. Corregir el problema (ej. RUC mal, monto mal)
4. PUT /facturas/123 { campos corregidos }
   ↓ auto-reenvía a SUNAT (cambia a "enviado" nuevo ciclo)
5. Esperar webhook / pollear
```

**No se permite**: editar facturas **aceptadas** (usar NC en ese caso).

---

## Workflow 5: Anular factura aceptada

Dos caminos según antigüedad:

**A) Mismo día — 7 días**: Comunicación de Baja (RA)
```
POST /anulaciones {
  "fecha_generacion": "2026-04-19",
  "detalles": [{
    "tipo_documento": "01",
    "serie": "F001", "correlativo": "123",
    "motivo": "Error en datos del cliente"
  }]
}
↓ devuelve id → consultar /anulaciones/{id}/estado
```

**B) Más de 7 días**: emitir Nota de Crédito
```
POST /notas-credito {
  "serie": "FC01", "fecha_emision": "2026-04-19",
  "cliente": { ... mismo cliente ... },
  "doc_afectado_tipo": "01",
  "doc_afectado_serie": "F001",
  "doc_afectado_correlativo": "123",
  "cod_motivo": "01",           // anulación
  "des_motivo": "Anulación por error en monto",
  "items": [ ... los mismos items a revertir ... ]
}
```

---

## Workflow 6: Anular boleta aceptada

Las boletas SOLO se anulan vía **resumen diario de anulación** (SUNAT rechaza RA para boletas):

```
POST /resumenes {
  "fecha_resumen": "2026-04-19",
  "anular": [
    { "id": 45, "motivo": "Error en importe" },
    { "id": 46, "motivo": "Cliente solicitó anulación" }
  ]
}
```

**Plazo**: 7 días desde emisión. Si ya pasó → usar NC.

---

## Workflow 7: Guía de Remisión Remitente (GRR)

Cuando TÚ envías mercancía y contratas (o tú mismo haces) el transporte:

```
1. POST /guias-remision {
     "serie": "T001", "fecha_emision": "2026-04-19",
     "destinatario": { ... RUC del que recibe ... },
     "cod_traslado": "01",              // 01=venta, 02=compra, 04=entre establecimientos
     "mod_traslado": "01",              // 01=privado, 02=público
     "fecha_traslado": "2026-04-20",
     "peso_total": 500.00, "und_peso_total": "KGM",
     "partida_ubigeo": "150101", "partida_direccion": "...",
     "llegada_ubigeo": "040101", "llegada_direccion": "...",
     "transportista": { ... si mod=02 (público) ... },
     "vehiculo": { "placa": "ABC-123" },
     "conductor": { "tipo_doc": "1", "num_doc": "...", ... },
     "items": [ ... lo que transportas ... ]
   }
2. GET /guias-remision/{id}/estado   → consultar hasta aceptado
3. GET /guias-remision/{id}/pdf      → imprimir para acompañar la carga
```

---

## Workflow 8: Guía de Remisión Transportista (GRT)

Cuando eres empresa de transporte y llevas mercancía **de un tercero**:

```
POST /guias-remision-transportista {
  "serie": "V001", "fecha_emision": "2026-04-19",
  "remitente":    { "tipo_doc": "6", "num_doc": "20xxx", "razon_social": "..." },
  "destinatario": { "tipo_doc": "6", "num_doc": "20yyy", "razon_social": "..." },
  "doc_relacionado": { "tipo_codigo": "04", "numero": "F001-500" },    // factura del remitente
  "cod_traslado": "01", "mod_traslado": "01",
  "fecha_traslado": "2026-04-20",
  "peso_total": 500.00, "und_peso_total": "KGM",
  ... partida/llegada/vehiculo/conductor ...
}
```

**⚠️ GRT funciona SOLO en producción de SUNAT**. La beta no la valida correctamente.

---

## Workflow 9: Webhook handler (recomendado)

Para no hacer polling, configura webhook:

```
1. PUT /empresa { "webhook_url": "https://tu-app.com/sunat-webhook" }
2. Implementa handler en tu app:

POST https://tu-app.com/sunat-webhook
Body:
{
  "event": "document.sent" | "document.rejected",
  "tenant_id": 15,
  "model": "Invoice",
  "id": 123,
  "data": {
    "numero": "F001-123",
    "sunat_status": "aceptado",
    "sunat_code": "0",
    "hash_cpe": "..."
  }
}

3. Procesa (actualiza tu BD, envía email al cliente, etc.)
4. Responde 200 OK
```

**Importante**: responde rápido (< 5s) y maneja idempotencia (pueden llegar duplicados).

---

## Workflow 10: Panel + reportes para dashboard interno

```
GET /panel                         → vista completa mes actual
GET /panel/indicadores             → KPIs (ventas, crecimiento)
GET /panel/ventas-mensuales        → gráfico 12 meses
GET /panel/cobranzas               → aging
GET /panel/documentos-recientes    → feed

GET /reportes/registro-ventas?desde=2026-04-01&hasta=2026-04-30
   → reporte mensual para SUNAT
```

Útil para construir dashboard con Chart.js, Recharts, etc.

---

## Workflow 11: Consultar factura de proveedor antes de pagar

Para evitar pagar facturas inventadas o con datos incorrectos:

```
GET /consultar-cpe?ruc_emisor=20XXX&tipo_doc=01&serie=F001&correlativo=123&fecha_emision=2026-04-18&monto_total=1180.00
```

Respuesta te dice si SUNAT tiene registrado ese comprobante y su estado.

---

## Workflow 12: Búsqueda inteligente RUC/DNI

Antes de crear un cliente o en UI de búsqueda:

```
GET /buscar-documento?tipo=6&numero=20512345678
```

1. Busca en BD local del tenant (si ya existe)
2. Si no está, llama a SUNAT/RENIEC
3. Guarda localmente para siguiente vez (cache)

---

## Workflow 13: Upgrade de plan (self-service)

```
1. Usuario alcanza límite → recibe 429 con codigo_error=limite_alcanzado y mejora_plan
2. Redirect a checkout en tu UI
3. Cliente paga con gateway (Stripe, Culqi, MercadoPago)
4. Tu backend obtiene el token de pago
5. PUT /suscripcion/cambiar-plan { plan_slug, ciclo_facturacion, token }
6. API ejecuta cambio + cobro + plan activo inmediato (si es upgrade)
```

---

## Workflow 14: NRUS — negocio pequeño

Flujo típico para una bodega, kiosko, etc.:

```
1. Registro con tax_regime="nrus" + nrus_categoria="1" (S/ 20 mensuales)
2. Setup: 1 sucursal (la tienda), 1 serie B001 (solo boletas)
3. Operación diaria:
   - POST /boletas con solo_registro=true (durante el día)
   - Al cierre: POST /resumenes para enviar todas
4. La API automáticamente:
   - Aplica IGV=0 y tipo_operacion=0113 (NRUS)
   - Bloquea cualquier intento de emitir factura/NC/ND
5. Mensualmente el contribuyente paga directamente a SUNAT S/20 o S/50 fijos
```

---

## Workflow 15: MYPE Restaurantes (Ley 31556)

Restaurantes y hoteles reciben tasa reducida:

```
1. Registro con tax_regime="mype_restaurantes"
2. La API aplica según fecha:
   - 2022-2024: 8% IGV
   - 2025: 18% (régimen normal - Ley vencida)
   - 2026-2029: 10.5% (extensión Ley 31556-B)
3. Emisión normal (facturas + boletas) — nada especial en el body
4. El monto IGV viene calculado con la tasa reducida automáticamente
```

---

## Errores comunes y cómo recuperarse

| Problema | Causa | Solución |
|---|---|---|
| 422 "El campo cliente.tipo doc seleccionado no es válido" | Mandaste `tipo_doc` fuera del enum | Usar `1`, `4`, `6`, `7`, `0` o `A` |
| 422 `codigo_error=documento_aceptado_no_editable` | PUT sobre factura/boleta/NC/ND aceptada | Leer `siguiente_accion` — típicamente `POST /notas-credito` o `POST /anulaciones` |
| 422 `codigo_error=boletas_no_soportadas_en_ra` | POST /anulaciones con tipo `03` | Reemplazar por `POST /resumenes` con `{"anular":[...]}` |
| 422 "Las facturas requieren cliente con RUC (tipo_doc=6)" | POST /facturas con DNI/CE | Cambiar a `POST /boletas` para consumidor final |
| 422 en `/clientes` con `num_doc`/`tipo_doc` | Compatibilidad — el endpoint acepta ambas convenciones | Si sigue fallando, revisar versión de la API — `tipo_doc`/`num_doc` es alias soportado |
| 429 `codigo_error=limite_alcanzado` | Plan agotado del mes | Upgrade con `PUT /suscripcion/cambiar-plan` |
| 401 "Credenciales API inválidas" | key/secret mal o expirados | Verificar env vars |
| SUNAT código 2017 | Cliente RUC no coincide con SUNAT | `GET /buscar-documento` primero |
| SUNAT código 2800 | Correlativo duplicado (rara) | Reintentar — la API ya maneja con lock |
| SUNAT código 3208 | Total no coincide con sumatoria items | Revisar cálculos locales antes de enviar |
| `advertencias: [{codigo:"grt_beta_no_soportado"}]` | GRT emitida en entorno beta | No es error — advertencia. Para pruebas reales cambiar a `entorno=production` |
| 500 "GD extension not installed" (solo PDF) | Extensión PHP en server | Admin del servidor instala php-gd |

---

## Anti-patterns (NO hacer)

- ❌ Guardar api_secret en frontend (JS, app móvil) → servidor a servidor siempre
- ❌ Esperar síncrono la respuesta de SUNAT bloqueando UI
- ❌ Re-crear una factura si falló — usar `/reenviar` o `/enviar`
- ❌ Emitir múltiples boletas sin resumen diario al final
- ❌ Ignorar el webhook y hacer polling cada segundo (sobrecarga)
- ❌ Hardcodear códigos SUNAT como integers (`"0"` es string)
- ❌ Modificar factura aceptada por SUNAT (legalmente prohibido)

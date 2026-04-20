# cURL / bash — Integración rápida

Para pruebas, scripts, CLI, o cuando el lenguaje del usuario no tiene guía dedicada.

---

## Variables base

```bash
export SUNAT_BASE_URL="https://api.kodevo.es/sunat-api/api/v1"
export API_KEY="tu_api_key"
export API_SECRET="tu_api_secret"

# Header helper
HEADERS=(
  -H "Accept: application/json"
  -H "Content-Type: application/json"
  -H "X-Api-Key: $API_KEY"
  -H "X-Api-Secret: $API_SECRET"
)
```

---

## 1. Registrar empresa (sin auth)

```bash
curl -X POST $SUNAT_BASE_URL/registro \
  -F "ruc=20100000001" \
  -F "razon_social=MI EMPRESA SAC" \
  -F "direccion=AV. LIMA 123" \
  -F "ubigeo=150101" \
  -F "sol_user=MODDATOS" \
  -F "sol_pass=MODDATOS" \
  -F "certificado=@cert.pfx" \
  -F "contrasena_certificado=123456" \
  -F "tax_regime=general"
```

Guarda el `api_key` y `api_secret` del response.

---

## 2. Setup básico

```bash
# Sucursal principal
curl -X POST $SUNAT_BASE_URL/sucursales "${HEADERS[@]}" \
  -d '{"nombre":"Principal","cod_local":"0000","direccion":"AV. LIMA 123","ubigeo":"150101","es_principal":true}'

# Series (todas en un solo request)
curl -X POST $SUNAT_BASE_URL/series "${HEADERS[@]}" \
  -d '{"series":[
    {"tipo":"factura","serie":"F001","sucursal_id":1},
    {"tipo":"boleta","serie":"B001","sucursal_id":1},
    {"tipo":"nota_credito","serie":"FC01","sucursal_id":1},
    {"tipo":"nota_debito","serie":"FD01","sucursal_id":1}
  ]}'

# Cliente
curl -X POST $SUNAT_BASE_URL/clientes "${HEADERS[@]}" \
  -d '{"tipo_documento":"6","numero_documento":"20512345678","razon_social":"CLIENTE DEMO SAC"}'
```

---

## 3. Emitir factura

```bash
curl -X POST $SUNAT_BASE_URL/facturas "${HEADERS[@]}" \
  -d '{
    "serie": "F001",
    "fecha_emision": "2026-04-19",
    "tipo_operacion": "0101",
    "tipo_moneda": "PEN",
    "forma_pago": "Contado",
    "cliente": {
      "tipo_doc": "6",
      "num_doc": "20512345678",
      "razon_social": "CLIENTE DEMO SAC",
      "direccion": "AV. AREQUIPA 1234"
    },
    "items": [{
      "codigo": "P001",
      "descripcion": "Producto",
      "unidad": "NIU",
      "cantidad": 2,
      "precio_unitario": 118.00,
      "tip_afe_igv": "10"
    }]
  }'
```

---

## 4. Consultar estado

```bash
curl $SUNAT_BASE_URL/facturas/123 "${HEADERS[@]}" | jq .datos.sunat
```

---

## 5. Descargar archivos

```bash
# PDF
curl -o factura.pdf $SUNAT_BASE_URL/facturas/123/pdf?format=a4 \
  -H "X-Api-Key: $API_KEY" -H "X-Api-Secret: $API_SECRET"

# XML
curl -o factura.xml $SUNAT_BASE_URL/facturas/123/xml \
  -H "X-Api-Key: $API_KEY" -H "X-Api-Secret: $API_SECRET"

# CDR
curl -o cdr.zip $SUNAT_BASE_URL/facturas/123/cdr \
  -H "X-Api-Key: $API_KEY" -H "X-Api-Secret: $API_SECRET"
```

---

## 6. Boleta NRUS (régimen NRUS)

Asumiendo que cambiaste la empresa a NRUS:
```bash
curl -X PUT $SUNAT_BASE_URL/empresa "${HEADERS[@]}" \
  -d '{"tax_regime":"nrus","nrus_categoria":"1"}'
```

Luego:
```bash
curl -X POST $SUNAT_BASE_URL/boletas "${HEADERS[@]}" \
  -d '{
    "serie": "B001",
    "fecha_emision": "2026-04-19",
    "cliente": {"tipo_doc":"1","num_doc":"12345678","razon_social":"CLIENTE"},
    "items": [{"codigo":"P","descripcion":"Prod","unidad":"NIU","cantidad":1,"precio_unitario":50,"tip_afe_igv":"10"}]
  }'
# → IGV=0 y tipo_operacion=0113 automático
```

---

## 7. Resumen diario (envío en lote)

```bash
# Durante el día: boletas con solo_registro
curl -X POST $SUNAT_BASE_URL/boletas "${HEADERS[@]}" \
  -d '{"serie":"B001","fecha_emision":"2026-04-19","cliente":{...},"items":[...],"solo_registro":true}'

# Al cierre: enviar resumen diario
curl -X POST $SUNAT_BASE_URL/resumenes "${HEADERS[@]}" \
  -d '{"fecha_resumen":"2026-04-19"}'
```

---

## 8. Anular factura (RA)

```bash
curl -X POST $SUNAT_BASE_URL/anulaciones "${HEADERS[@]}" \
  -d '{
    "fecha_generacion": "2026-04-19",
    "detalles": [{
      "tipo_documento": "01",
      "serie": "F001",
      "correlativo": "123",
      "motivo": "Error en datos del cliente"
    }]
  }'
```

---

## 9. Nota de Crédito (devolución)

```bash
curl -X POST $SUNAT_BASE_URL/notas-credito "${HEADERS[@]}" \
  -d '{
    "serie": "FC01",
    "fecha_emision": "2026-04-19",
    "cliente": {"tipo_doc":"6","num_doc":"20xxx","razon_social":"..."},
    "doc_afectado_tipo": "01",
    "doc_afectado_serie": "F001",
    "doc_afectado_correlativo": "123",
    "cod_motivo": "06",
    "des_motivo": "Devolución total",
    "items": [...]
  }'
```

---

## 10. Panel

```bash
curl $SUNAT_BASE_URL/panel/indicadores "${HEADERS[@]}" | jq .datos
curl $SUNAT_BASE_URL/panel/ventas-mensuales "${HEADERS[@]}" | jq .datos
curl $SUNAT_BASE_URL/panel/cobranzas "${HEADERS[@]}" | jq .datos
```

---

## 11. Helper script — emisión + wait hasta aceptado

```bash
#!/bin/bash
# emitir-factura.sh

set -e

RESP=$(curl -s -X POST $SUNAT_BASE_URL/facturas "${HEADERS[@]}" -d @factura.json)
ID=$(echo $RESP | jq -r .datos.id)

echo "Factura ID $ID creada. Esperando SUNAT..."

for i in {1..10}; do
  sleep 3
  ESTADO=$(curl -s $SUNAT_BASE_URL/facturas/$ID "${HEADERS[@]}" | jq -r .datos.sunat.estado)
  echo "Intento $i: estado=$ESTADO"
  if [ "$ESTADO" = "aceptado" ] || [ "$ESTADO" = "rechazado" ]; then
    break
  fi
done

# Descargar PDF si aceptado
if [ "$ESTADO" = "aceptado" ]; then
  curl -o "factura-$ID.pdf" $SUNAT_BASE_URL/facturas/$ID/pdf?format=a4 \
    -H "X-Api-Key: $API_KEY" -H "X-Api-Secret: $API_SECRET"
  echo "✅ PDF guardado: factura-$ID.pdf"
fi
```

---

## 12. Ver logs de errores

Cuando falla una request, parsear el error:

```bash
curl -s -X POST $SUNAT_BASE_URL/facturas "${HEADERS[@]}" -d @factura.json | jq .

# Si error de validación:
# {
#   "estado": "error",
#   "mensaje": "Error de validación",
#   "errores": {
#     "serie": ["El campo serie es obligatorio."]
#   }
# }

# Ver solo los errores
curl -s -X POST ... | jq .errores
```

---

## 13. Automatizar con `jq`

```bash
# Listar últimas facturas rechazadas
curl -s "$SUNAT_BASE_URL/facturas?sunat_status=rechazado&por_pagina=50" "${HEADERS[@]}" \
  | jq '.datos.datos[] | {numero: .numero_completo, error: .sunat.descripcion}'

# Total ventas del mes
curl -s "$SUNAT_BASE_URL/panel/indicadores" "${HEADERS[@]}" \
  | jq .datos.mes.total_ventas
```

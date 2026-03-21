#!/bin/bash
# Test all invoice types via platform-api proxy (same path as frontend)

# Get fresh token
TOKEN=$(cd /c/laragon/www/platform-api && php artisan tinker --execute="\$u=\App\Models\User::first();\$t=\$u->createToken('t');echo \$t->plainTextToken;" 2>/dev/null | tail -1)
BIZ=1
BASE="http://localhost:8001/api/businesses/$BIZ"
PASS=0
FAIL=0
OBS=0
TOTAL=0

send() {
  local NAME="$1"
  local ENDPOINT="$2"
  local DATA="$3"
  TOTAL=$((TOTAL + 1))

  RESP=$(curl -s -X POST "$BASE/$ENDPOINT" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "$DATA" 2>&1)

  ID=$(echo "$RESP" | python -c "import sys,json; d=json.load(sys.stdin); print(d.get('id','') or d.get('data',{}).get('id',''))" 2>/dev/null)
  ERR=$(echo "$RESP" | python -c "import sys,json; d=json.load(sys.stdin); print(d.get('message','') or d.get('error',''))" 2>/dev/null)

  if [ -n "$ID" ] && [ "$ID" != "" ] && [ "$ID" != "None" ]; then
    echo "PASS [$TOTAL] $NAME -> id=$ID"
    PASS=$((PASS + 1))
  else
    echo "FAIL [$TOTAL] $NAME -> $ERR"
    FAIL=$((FAIL + 1))
  fi
}

echo "Testing via platform-api proxy (port 8001)"
echo "Token: ${TOKEN:0:20}..."
echo ""

# 1. Factura Gravada (10)
send "1. Gravado (10)" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Servicio desarrollo web","unidad":"ZZ","cantidad":1,"precio_unitario":100.00,"tip_afe_igv":"10"}]
}'

# 2. Factura Credito con cuotas (total=5000, cuotas deben sumar 5000)
send "2. Credito + Cuotas" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Credito",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Laptop HP ProBook","unidad":"NIU","cantidad":2,"precio_unitario":2500.00,"tip_afe_igv":"10"}],
  "cuotas":[{"monto":2500.00,"fecha_pago":"2026-04-15"},{"monto":2500.00,"fecha_pago":"2026-05-15"}]
}'

# 3. Exonerado (20)
send "3. Exonerado (20)" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Servicio educativo","unidad":"ZZ","cantidad":1,"precio_unitario":500.00,"tip_afe_igv":"20"}]
}'

# 4. Inafecta (30)
send "4. Inafecta (30)" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Alquiler de inmueble","unidad":"ZZ","cantidad":1,"precio_unitario":1200.00,"tip_afe_igv":"30"}]
}'

# 5. Gratuita Gravada (11)
send "5. Gratuita Gravada (11)" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Muestra gratuita producto","unidad":"NIU","cantidad":5,"precio_unitario":50.00,"tip_afe_igv":"11"}]
}'

# 6. Exportacion (40) USD
send "6. Exportacion (40)" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"USD","tipo_operacion":"0200","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Software development","unidad":"ZZ","cantidad":1,"precio_unitario":5000.00,"tip_afe_igv":"40"}]
}'

# 7. Detraccion
send "7. Detraccion" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"1001","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Servicio mantenimiento","unidad":"ZZ","cantidad":1,"precio_unitario":5000.00,"tip_afe_igv":"10"}],
  "detraccion":{"tipo_operacion":"1001","cod_bien":"022","cod_medio_pago":"001","cta_banco":"00-123-456789","porcentaje":12,"monto":708.00},
  "leyendas":[{"code":"2006","value":"Operacion sujeta al SPOT"}]
}'

# 8. Percepcion (base=1500=total factura, percep 2%=30.00, mto_total=1530.00)
send "8. Percepcion" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"2001","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Venta de combustible","unidad":"GLL","cantidad":100,"precio_unitario":15.00,"tip_afe_igv":"10"}],
  "percepcion":{"tipo_operacion":"2001","cod_reg":"51","porcentaje":2,"mto_base":1500.00,"mto":30.00,"mto_total":1530.00},
  "leyendas":[{"code":"2000","value":"Comprobante de percepcion"}]
}'

# 9. Descuento Global tipo 02 (10% de 2400 base sin IGV = 240, recalc by backend)
send "9. Desc. Global (02)" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Monitor Samsung 27p","unidad":"NIU","cantidad":3,"precio_unitario":800.00,"tip_afe_igv":"10"}],
  "descuentos_globales":[{"cod_tipo":"02","monto_base":2033.90,"factor":0.10,"monto":203.39}]
}'

# 10. Retencion 3% (tipo 62)
send "10. Retencion 3% (62)" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Consultoria empresarial","unidad":"ZZ","cantidad":1,"precio_unitario":10000.00,"tip_afe_igv":"10"}],
  "descuentos_globales":[{"cod_tipo":"62","monto_base":10000.00,"factor":0.03,"monto":300.00}]
}'

# 11. Anticipos (total_anticipos must be set)
send "11. Anticipos" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Obra de construccion","unidad":"ZZ","cantidad":1,"precio_unitario":50000.00,"tip_afe_igv":"10"}],
  "anticipos":[{"tipo_doc_rel":"02","nro_doc_rel":"F001-100","total":10000.00}],
  "total_anticipos":10000.00
}'

# 12. ICBPER (bolsas: 3 x 0.50 factor)
send "12. ICBPER" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[
    {"descripcion":"Producto A","unidad":"NIU","cantidad":2,"precio_unitario":50.00,"tip_afe_igv":"10"},
    {"descripcion":"Bolsa plastica","unidad":"NIU","cantidad":3,"precio_unitario":0.50,"tip_afe_igv":"10","icbper":1.50,"factor_icbper":0.50}
  ]
}'

# 13. Gratuita Inafecta (31)
send "13. Gratuita Inafecta (31)" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Donacion medicinas","unidad":"NIU","cantidad":10,"precio_unitario":25.00,"tip_afe_igv":"31"}]
}'

# 14. ISC (24 x 3.50 = 84 base, ISC=8.40, baseIgv=92.40, IGV=16.63)
send "14. ISC" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Bebida gaseosa 500ml","unidad":"NIU","cantidad":24,"precio_unitario":3.50,"tip_afe_igv":"10","isc":8.40}]
}'

# 15. IVAP (17) - 4% rate auto-detected
send "15. IVAP (17)" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Arroz pilado en sacos","unidad":"KGM","cantidad":1000,"precio_unitario":2.50,"tip_afe_igv":"17"}]
}'

# 16. Con Guias
send "16. Con Guias" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Cemento Portland","unidad":"NIU","cantidad":50,"precio_unitario":28.00,"tip_afe_igv":"10"}],
  "guias":[{"tipo_doc":"09","nro_doc":"T001-00000001"}]
}'

# 17. Mixta (10 + 20)
send "17. Mixta (10+20)" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[
    {"descripcion":"Producto gravado","unidad":"NIU","cantidad":2,"precio_unitario":200.00,"tip_afe_igv":"10"},
    {"descripcion":"Servicio educativo","unidad":"ZZ","cantidad":1,"precio_unitario":300.00,"tip_afe_igv":"20"}
  ]
}'

# 18. USD
send "18. USD" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"USD","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Consulting services","unidad":"ZZ","cantidad":1,"precio_unitario":1500.00,"tip_afe_igv":"10"}]
}'

# 19. Descuento por Item (10% off)
send "19. Desc. por Item" "invoices" '{
  "serie":"F001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"6","num_doc":"20538856674","razon_social":"ARTROSCOPICTRAUMA S.A.C.","direccion":"AV. GRAL.GARZON NRO. 2320"},
  "items":[{"descripcion":"Impresora Laser HP","unidad":"NIU","cantidad":1,"precio_unitario":1500.00,"tip_afe_igv":"10",
    "descuentos":[{"cod_tipo":"00","monto_base":1271.19,"factor":0.10,"monto":127.12}]
  }]
}'

echo ""
echo "=== BOLETAS ==="

# 20. Boleta Gravada (10)
send "20. Boleta (10)" "boletas" '{
  "serie":"B001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"1","num_doc":"72243561","razon_social":"CHAVEZ HUINCHO, JORGE"},
  "items":[{"descripcion":"Articulo limpieza","unidad":"NIU","cantidad":3,"precio_unitario":15.00,"tip_afe_igv":"10"}]
}'

# 21. Boleta sin doc (<700)
send "21. Boleta sin doc" "boletas" '{
  "serie":"B001","fecha_emision":"2026-03-15","tipo_moneda":"PEN","tipo_operacion":"0101","forma_pago":"Contado",
  "cliente":{"tipo_doc":"0","num_doc":"00000000","razon_social":"CLIENTE VARIOS"},
  "items":[{"descripcion":"Producto generico","unidad":"NIU","cantidad":1,"precio_unitario":50.00,"tip_afe_igv":"10"}]
}'

echo ""
echo "RESULTS: PASS=$PASS  OBS=$OBS  FAIL=$FAIL  TOTAL=$TOTAL"
echo ""

# Wait for SUNAT responses (queue processes async)
echo "Waiting 30s for SUNAT responses..."
sleep 30

echo ""
echo "=== SUNAT RESULTS ==="
cd /c/laragon/www/API-PRO && php artisan tinker --execute="
\$invoices = \App\Models\Invoice::where('tenant_id', 2)
    ->orderBy('id', 'desc')
    ->take(19)
    ->get(['id', 'serie', 'correlativo', 'sunat_status', 'sunat_code', 'sunat_description']);
\$pass = 0; \$obs = 0; \$fail = 0; \$pending = 0;
foreach (\$invoices->reverse() as \$inv) {
    \$desc = substr(\$inv->sunat_description ?? '', 0, 60);
    \$code = \$inv->sunat_code;
    if (\$inv->sunat_status === 'aceptado' && \$code == '0') {
        \$status = 'PASS';
        \$pass++;
    } elseif (\$inv->sunat_status === 'aceptado') {
        \$status = 'OBS ';
        \$obs++;
    } elseif (\$inv->sunat_status === 'rechazado') {
        \$status = 'FAIL';
        \$fail++;
    } else {
        \$status = 'PEND';
        \$pending++;
    }
    echo \"{\$status} F001-{\$inv->correlativo} (id={\$inv->id}) code={\$code} {\$desc}\" . PHP_EOL;
}
\$boletas = \App\Models\Boleta::where('tenant_id', 2)->orderBy('id', 'desc')->take(2)->get(['id','serie','correlativo','sunat_status','sunat_code']);
foreach (\$boletas->reverse() as \$b) {
    \$code = \$b->sunat_code;
    if (\$b->sunat_status === 'aceptado' && \$code == '0') { \$pass++; \$s='PASS'; }
    elseif (\$b->sunat_status === 'aceptado') { \$obs++; \$s='OBS '; }
    else { \$pending++; \$s='PEND'; }
    echo \"{\$s} B001-{\$b->correlativo} (id={\$b->id}) code={\$code}\" . PHP_EOL;
}
echo PHP_EOL . \"SUNAT: PASS={\$pass} OBS={\$obs} FAIL={\$fail} PENDING={\$pending}\" . PHP_EOL;
" 2>/dev/null

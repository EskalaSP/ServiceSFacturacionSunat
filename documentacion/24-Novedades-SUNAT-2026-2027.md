# 🆕 Novedades SUNAT 2026 / 2027

Cambios normativos de SUNAT implementados en la API (R.S. 000048-2026 y actualización de
Reglas de Validación al **24.07.2026**). Este documento resume **toda la funcionalidad nueva**
y sus fechas de vigencia. Los detalles por comprobante están en sus docs respectivos
(`04-Facturas.md`, `05-Boletas.md`, `06-Notas-credito.md`, `07-Notas-debito.md`).

> **Regla de oro:** todos los campos nuevos son **opcionales**. Si no los envías, tus comprobantes
> se emiten exactamente igual que antes. Agrégalos solo cuando los necesites.

---

## 📅 Vigencias

| Cambio | Vigencia | ¿Ya funciona en SUNAT? |
|--------|----------|------------------------|
| Código de producto SUNAT (envío + validación) | Disponible ya (opcional) | ✅ Aceptado por SUNAT |
| Contrato de colaboración empresarial | Disponible ya (opcional) | ✅ Aceptado por SUNAT |
| NC: monto no puede superar el documento | Disponible ya | ✅ |
| Catálogo 54 — nuevos códigos de detracción | 01/09/2026 | — |
| Catálogo 51 — descripción del `0101` | 01/08/2026 | — |
| **ND motivo 13 (Penalidades)** | **01/01/2027** | ⏳ SUNAT lo rechaza hasta esa fecha |
| Código de producto **obligatorio** (padrón) / ERR-3496 | 01/01/2027 | — |

> ⚠️ La API **ya soporta** el motivo 13 y el código obligatorio, pero SUNAT no los activa hasta el
> **1 de enero de 2027**. Si emites una ND motivo 13 hoy, SUNAT la rechaza con
> `2172: Valor no se encuentra en el catalogo: 10 valor '13'` — es **esperado**, no un error de la API.

---

## 1. Código de Producto SUNAT (UNSPSC)

Código internacional de 8 dígitos (estándar UNSPSC v14, Catálogo N.° 25) que clasifica cada ítem.
Se envía por ítem en `cod_producto_sunat` y viaja al XML como `cbc:ItemClassificationCode`.

**Reglas de validación:**
- Debe tener **exactamente 8 dígitos** numéricos.
- Debe **existir** en el catálogo UNSPSC v14 (la API valida contra ~52.800 códigos cargados).
- **No** puede terminar en `0000` (debe llegar mínimo a nivel *clase*, regla OBS-4337).

**¿Es obligatorio?** Hoy **no**. Solo será obligatorio (desde 01/01/2027) para los RUC que SUNAT
incluya en el padrón *"Obligado a enviar código de producto"* (`ind_padron = 12`). Cuando eso pase,
se marca el flag del emisor y la API exigirá el campo en cada ítem.

**Caso especial — tipo de operación `0112`** (gastos deducibles persona natural): exige que al menos un
ítem tenga código `84121901` (crédito hipotecario) o `80131501` (arrendamiento de inmuebles).

```json
{
  "items": [
    {
      "codigo": "SERV-001",
      "cod_producto_sunat": "78181500",
      "descripcion": "Mantenimiento de vehiculo",
      "unidad": "ZZ",
      "cantidad": 1,
      "precio_unitario": 354,
      "tip_afe_igv": "10"
    }
  ]
}
```

Aplica a **facturas, boletas, notas de crédito y notas de débito**. Algunos códigos de ejemplo:
`78181500` (mantenimiento vehículos), `43211508` (computadoras), `50181900` (panadería),
`82121500` (impresión), `53101800` (ropa).

> 🔎 **¿Cómo encuentro el código de mi producto?** La API tiene un **buscador** (por texto, por código y
> drill-down jerárquico como el Excel oficial). Ver [25-Buscar-codigo-producto.md](25-Buscar-codigo-producto.md).

---

## 2. Contrato de Colaboración Empresarial

Para **consorcios sin contabilidad independiente** (RS 048-2026). Bloque **opcional** a nivel raíz del
comprobante. Viaja al XML como `cac:ContractDocumentReference`.

| Campo | Tipo | Notas |
|-------|------|-------|
| `tipo` | string | `1` = Ventas, `2` = Adquisiciones (ERR-3497) |
| `numero` | string(50) | Número/denominación del contrato (ERR-3501) |
| `descripcion` | string(250) | Descripción del contrato (ERR-3502) |
| `porcentaje` | numeric | % de participación, 0–99.99 (ERR-3500) |

**Regla (ERR-3499):** si envías `numero`, entonces `tipo`, `descripcion` y `porcentaje` son obligatorios.
Si no envías el bloque, no pasa nada.

```json
{
  "contrato_colaboracion": {
    "tipo": "1",
    "numero": "CONS-2026-014",
    "descripcion": "Consorcio vial sin contabilidad independiente",
    "porcentaje": 40
  }
}
```

Aplica a **facturas, boletas, notas de crédito y notas de débito**.

---

## 3. Nota de Débito motivo 13 — Penalidades

**Vigente desde 01/01/2027.** El Catálogo 10 cambia:
- El código `03` pasa a llamarse solo **"Otros conceptos"** (las penalidades salen de ahí).
- Se crea el código **`13` = "Penalidades"**.

**Reglas del motivo 13:**
- **Solo operaciones inafectas** del IGV/IVAP: los ítems deben ir con `tip_afe_igv: "30"` y sin IGV
  (ERR-3507 rechaza si hay IGV > 0).
- El **documento afectado es OPCIONAL** (a diferencia de los otros motivos, que sí lo exigen). Una
  penalidad puede no referenciar ningún comprobante previo.

```json
{
  "serie": "FD01",
  "fecha_emision": "2027-01-15",
  "tipo_moneda": "PEN",
  "cod_motivo": "13",
  "des_motivo": "Penalidad por incumplimiento contractual",
  "cliente": { "tipo_doc": "6", "num_doc": "20605145648", "razon_social": "ACME SAC" },
  "items": [
    {
      "descripcion": "Penalidad contractual",
      "unidad": "ZZ",
      "cantidad": 1,
      "precio_unitario": 500,
      "tip_afe_igv": "30"
    }
  ]
}
```

---

## 4. Nota de Crédito — monto ≤ documento

La API valida que el **Importe Total de la NC no supere** el del documento que modifica
(factura o boleta emitida por esta misma API):

- **Factura (01):** NC ≤ documento (estricto).
- **Boleta (03):** NC ≤ documento + 1 (tolerancia SUNAT).

Si el monto se pasa → **HTTP 422** con `ERR-3286/3503`. No aplica si la moneda de la NC difiere de la del
documento, ni si el documento fue emitido en otro sistema (SUNAT lo valida en recepción).

```
422: "El importe total de la nota de crédito (500) no puede superar el del documento
      que modifica F001-1 (118) (ERR-3286/3503)."
```

---

## 5. Catálogos actualizados

**Catálogo 54 — Detracciones** (nuevos códigos, vig. 01/09/2026):
`038` Espectáculos públicos, `042` Ladrillos/cerámica, `043` Estructuras metálicas,
`046` Cobre gravado, `047` Plata gravada.

**Catálogo 51 — Tipo de operación:** el `0101` pasa a *"Venta interna no sujeta a Detracción o Percepción"*.

**Catálogo 10 — Tipo de ND:** `03` → "Otros conceptos"; nuevo `13` → "Penalidades".

---

## ✅ Resumen para el integrador

| Quiero… | Campo | Dónde |
|---------|-------|-------|
| Clasificar productos (SUNAT) | `items[].cod_producto_sunat` | FAC / BOL / NC / ND |
| Emitir por consorcio | `contrato_colaboracion` (raíz) | FAC / BOL / NC / ND |
| Emitir penalidad (desde 2027) | `cod_motivo: "13"` + inafectas | ND |
| — | Nada obligatorio hoy | Todo sigue funcionando sin estos campos |

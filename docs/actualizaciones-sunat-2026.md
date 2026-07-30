# Plan de actualización API SUNAT — Normativa 2026 / 2027

> Análisis basado en: `actualizaciones-sunat/Reglas de validación - actualizado al 24.07.2026.xlsx`,
> `CCNU_MOD_2.xlsm` (catálogo producto UNSPSC v14), `RESOLUCIÓN DE SUPERINTENDENCIA.pdf` (R.S. 000075-2026),
> y contraste con `cpe.sunat.gob.pe`. Fecha de análisis: 30/07/2026.

## Estado de implementación (30/07/2026)

Todo el P0 y P1 quedó **implementado y probado** en local (migraciones corridas en BD local; falta correr en producción):

- ✅ **P0** — Cat 54 (5 códigos detracción), Cat 51 (0101).
- ✅ **P1.1 Código de producto SUNAT (UNSPSC v14)** — tabla `unspsc_codes` (52.840 códigos) + seeder desde
  `database/data/unspsc_v14.csv`, regla `SunatProductCode`, columna en tablas de ítems, cableado al XML
  (`cbc:ItemClassificationCode`), flag `tenants.obligado_codigo_producto` (padrón 12), caso 0112.
- ✅ **P1.2 ND motivo 13 (Penalidades)** — Cat 10 (03→"Otros conceptos", +13), ERR-3507 (inafectas),
  documento afectado opcional + remoción del `BillingReference` vacío al firmar.
- ✅ **P1.3 NC montos ≤ documento** (ERR-3286/3503) — trait `ValidatesNotaCreditoMonto`.
- ✅ **P1.4 Contrato de colaboración empresarial** (ERR-3497..3502) — trait `ValidatesContratoColaboracion`,
  columna `contrato_colaboracion`, inyección de `cac:ContractDocumentReference` al firmar.

**Pasos de despliegue:** `php artisan migrate` + `php artisan db:seed --class=UnspscCodeSeeder`. Marcar
`obligado_codigo_producto=true` en tenants del padrón 12. Confirmar 5 tasas SPOT del Cat 54.

**Pendiente (P2, según demanda):** tipos de doc 25/28/56 (DAE), liquidación de compra, onboarding R.S. 000075-2026.

## TL;DR

**Nada del flujo core (factura / boleta / NC / ND) se rompe el 1 de agosto de 2026.**
La actualización oficial del **24.07.2026** (hoja *Control de Cambios*, entrada [562]) **postergó del
01/08/2026 al 01/01/2027** casi todos los cambios críticos, y **retiró los anexos 25.1/25.2/25.3**.
Lo que te resumieron por blogs corresponde a la versión de abril (R.S. 000048-2026) y quedó desactualizado.

## Cronograma real de vigencias

| Cambio | Vigencia real | ¿Impacta flujo core? |
|---|---|---|
| Tasa IGV 10% → **10.5%** (especial Restaurantes/Hoteles, OBS-4439) | **Ya vigente** (13/02/2026) | Solo emisores en el padrón |
| Emisor electrónico desde inscripción RUC + SIRE (R.S. 000075-2026) | **Ya vigente** (01/06/2026) | Onboarding, no emisión |
| Nuevos tipos de doc **25 (DAE), 28 (DAEE), 56 (DAE-SEAE)** | 01/08/2026 | No (consorcios / aeroportuario) |
| Catálogo 51: descripción del 0101 | 01/08/2026 | Cosmético |
| Catálogo 54 (detracción): códigos 038, 042, 043, 046, 047 | **01/09/2026** | Sí, si usan detracción |
| **Código producto SUNAT obligatorio (ERR-3496)** | **01/01/2027** (postergado) | Sí — futuro |
| **NC solo modifica 1 documento (ERR-3261)** | 01/01/2027 (postergado) | Ya cumplido (ver abajo) |
| **ND motivo 13 Penalidades (ERR-2524/3194/3507)** | 01/01/2027 (postergado) | Sí — futuro |
| **Contrato de colaboración empresarial (ERR-3497..3502)** | 01/01/2027 (postergado) | Sí — futuro |
| Código producto en Liquidación de compra (ERR-3506) | 01/01/2027 (postergado) | No hay LC en la API |

Entradas postergadas a 2027-01-01 (col. 1 del Control de Cambios): 516, 517, 518, 522, 524, 525, 526,
527, 528, 530, 531, 532, 533, 534, 536, 537, 538, 539, 541, 542, 543, 544, 545.

## Estado de la API vs cada cambio

- **NC cardinalidad 1 → YA CUMPLE.** `StoreCreditNoteRequest.php:42-44` usa `doc_afectado_tipo/serie/correlativo`
  como objeto único (no array); `NoteBuilder.php:47-49` arma un solo `NumDocfectado`. Estructuralmente la API
  no puede emitir notas consolidadas.
- **Código producto SUNAT → cableado a medias (brecha real).** Se valida (`nullable|string|max:8`) pero:
  - Se descarta en `DocumentCalculationService::calculateItems` (`:164-181`) — no se persiste.
  - No hay columna en tablas de ítems (`..._create_credit_note_items_table.php:14`).
  - `InvoiceBuilder::buildItem` (`:327`) nunca llama `setCodProductoSunat` → en facturas/boletas se pierde.
  - Solo `NoteBuilder.php:293-294` intenta usarlo, pero no lo recibe en el flujo normal.
  - **No existe catálogo UNSPSC cargado** (`config/sunat_catalogs.php` no tiene Cat 25).
- **ND motivo 13 → no soportado.** Cat 10 en `config/sunat_catalogs.php:99` no incluye el código 13.
- **Contrato de colaboración → no existe** (campos ni validaciones).
- **Liquidación de compra → no existe** (sin builder/action/modelo/request).
- **Tasa IGV 10.5% → OK** (la API acepta `porcentaje_igv` arbitrario por ítem).
- **Parseo CDR:** `GreenterService.php:371-416` trata códigos `3xxx` como observación aceptada; los `ERR-xxxx`
  no se parsean por prefijo (genéricos `error_code`/`error_message`).

## Plan de acción priorizado

### P0 — Esta semana (bajo esfuerzo, ya vigente / inminente)
1. **Catálogo 54 detracciones** — agregar códigos `038, 042, 043, 046, 047` en `config/sunat_catalogs.php:227`
   (vigencia 01/09/2026). Trivial.
2. **Verificar tasa IGV especial 10.5%** — confirmar que un cliente restaurante/hotel puede enviar
   `porcentaje_igv: 10.5`. Solo pruebas; probablemente ya funciona.
3. (Opcional) Catálogo 51: actualizar descripción del `0101` a "Venta interna no sujeta a Detracción o
   Percepción" en `config/sunat_catalogs.php:165` — cosmético.

### P1 — Preparación para 01/01/2027 (el trabajo real, 5 meses de margen)
1. **Código de producto SUNAT (UNSPSC v14)** — la brecha más grande:
   - Cargar el catálogo desde `CCNU_MOD_2.xlsm` hoja *Bienes y Servicios* (~49k códigos, 8 dígitos) a una
     tabla/seed o índice de validación (jerarquía Segmento→Familia→Clase→Producto).
   - Propagar `cod_producto_sunat` en `DocumentCalculationService::calculateItems`.
   - Agregar columna `cod_producto_sunat` a las tablas de ítems (invoice/boleta/credit_note/debit_note).
   - Cablear `InvoiceBuilder::buildItem` → `setCodProductoSunat` (Greenter `SaleDetail` lo soporta).
   - Regla de validación nueva: `regex:/^\d{8}$/` + existencia en catálogo (mín. nivel clase, OBS-4337).
   - Feature-flag por fecha: opcional hasta 31/12/2026, `required` desde 01/01/2027 (ERR-3496).
   - **Antes de implementar:** confirmar en hoja *Factura2_0* si será obligatorio para todos los ítems o
     solo bienes específicos (los anexos 25.1/25.2/25.3 fueron retirados → alcance cambió).
2. **ND motivo 13 (Penalidades)** — agregar `13` a Cat 10 (`config/sunat_catalogs.php:99`); validar que la ND
   por penalidad sea solo operaciones inafectas (ERR-3507); sacar penalidades del motivo 03.
3. **NC — montos ≤ documento** (ERR-3503/3286) — validación cruzada de que el importe de la NC no supere el
   documento referenciado.
4. **Contrato de colaboración empresarial** (ERR-3497..3502) — campos opcionales: tipo (1 venta/2 adquisición),
   número, descripción, porcentaje de participación, con validaciones estrictas por subcampo.

### P2 — Según demanda de clientes
1. **Tipos de doc 25 / 28 / 56 (DAE / DAEE / DAE-SEAE)** — solo si tienes clientes consorcios o de servicios
   aeroportuarios. Alta complejidad (nuevos builders, series, catálogos). No lo necesita el cliente típico.
2. **Liquidación de compra electrónica** — si algún cliente compra a proveedores sin RUC. Nuevo builder/flujo.
3. **Onboarding R.S. 000075-2026** — revisar `EmissionPolicyService`: un RUC nuevo (MYPE/RER/General) nace
   obligado a emitir electrónico desde su inscripción; salida de Nuevo RUS obliga desde el 1er día del mes
   siguiente. Ajustar mensajes/lógica de alta si aplica.

## Códigos de error nuevos (referencia CDR)

| Código | Mensaje | Comprobante |
|---|---|---|
| ERR-3496 | El Código producto de SUNAT no es válido | FAC/BOL/NC/ND |
| ERR-3506 | El XML no contiene ItemClassificationCode | LC |
| ERR-3261 | No se puede modificar más de un comprobante con la nota | NC |
| ERR-3286 | Monto total de la NC ≤ monto del documento que modifica | NC |
| ERR-3503 | El monto consignado supera al importe del documento que modifica | NC |
| ERR-2524 | Debe indicar el documento afectado por la nota | ND |
| ERR-3194 | Solo es permitido registrar un documento que modifica | ND |
| ERR-3507 | Las penalidades son operaciones inafectas del IGV | ND |
| ERR-3497..3502 | Validaciones de Contrato de colaboración empresarial | FAC/BOL/NC/ND |
| OBS-4439 | Emisor no está en Padrón de Tasa especial IGV (Restaurantes/hoteles) | FAC/BOL/NC/ND |

## Fuentes oficiales

- Catálogo Código de Producto: https://cpe.sunat.gob.pe/informacion_general/codigoproducto (UNSPSC v14_0801, sin anexos)
- Normas legales CPE: https://cpe.sunat.gob.pe/node/98
- Reglas de validación (fuente de verdad): https://cpe.sunat.gob.pe/guias-y-manuales
- R.S. 000141-2026/SUNAT (29/07/2026): postergación a 01/01/2027

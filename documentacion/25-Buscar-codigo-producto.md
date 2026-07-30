# 🔎 Buscador de Código de Producto SUNAT (UNSPSC)

Endpoints para que tus clientes **encuentren el código de producto SUNAT** de sus ítems sin abrir el Excel
oficial de SUNAT. Replica —y mejora— la búsqueda del archivo `CCNU` (drill-down Segmento → Familia →
Clase → Producto) y agrega **búsqueda por texto**.

> Todos requieren autenticación normal (`X-Api-Key` / `X-Api-Secret`). Son de solo lectura.
> El catálogo está en **español** (busca "computador", no "laptop").

---

## 1. Buscar por texto o código

`GET /api/v1/catalogos/producto-sunat?q={texto|codigo}&per_page=20&page=1`

- Si `q` son **solo dígitos** → busca por **prefijo de código** (2 a 8 dígitos).
- Si `q` es **texto** → busca en la descripción; con varias palabras, todas deben aparecer.
  Ordena por relevancia (primero las que empiezan con tu término).

**Ejemplo — texto:**
```
GET /api/v1/catalogos/producto-sunat?q=aceite%20motor&per_page=5
```
```json
{
  "estado": "exito",
  "total": 3,
  "pagina": 1,
  "por_pagina": 5,
  "datos": [
    { "codigo": "15121504", "descripcion": "Aceites de motor", "clase": "151215" }
  ]
}
```

**Ejemplo — código (prefijo):**
```
GET /api/v1/catalogos/producto-sunat?q=4321&per_page=3
```
```json
{
  "estado": "exito",
  "total": 116,
  "datos": [
    { "codigo": "43211500", "descripcion": "Computadores", "clase": "432115" },
    { "codigo": "43211501", "descripcion": "Servidores de computador", "clase": "432115" }
  ]
}
```

---

## 2. Validar / ver detalle de un código

`GET /api/v1/catalogos/producto-sunat/{codigo}` (8 dígitos)

Útil para **confirmar** un código antes de emitir. Devuelve la jerarquía completa.

```
GET /api/v1/catalogos/producto-sunat/78181500
```
```json
{
  "estado": "exito",
  "valido": true,
  "datos": {
    "codigo": "78181500",
    "descripcion": "Servicios de mantenimiento y reparación de vehículos",
    "jerarquia": {
      "segmento": { "codigo": "78", "nombre": "Servicios de Transporte, Almacenaje y Correo" },
      "familia":  { "codigo": "7818", "nombre": "Servicios de mantenimiento o reparaciones de transportes" },
      "clase":    { "codigo": "781815", "nombre": "Servicios de mantenimiento y reparación de vehículos" }
    }
  }
}
```

Si el código no existe → **HTTP 404** con `"valido": false`. Si no tiene 8 dígitos → **HTTP 422**.

---

## 3. Navegación jerárquica (drill-down como el Excel)

Para armar un selector en cascada Segmento → Familia → Clase → Producto:

| Paso | Endpoint | Devuelve |
|------|----------|----------|
| 1 | `GET /catalogos/producto-sunat/segmentos` | 56 segmentos `{codigo(2), nombre}` |
| 2 | `GET /catalogos/producto-sunat/familias?segmento=78` | familias del segmento `{codigo(4), nombre}` |
| 3 | `GET /catalogos/producto-sunat/clases?familia=7818` | clases de la familia `{codigo(6), nombre}` |
| 4 | `GET /catalogos/producto-sunat/productos?clase=781815` | **productos** de la clase `{codigo(8), descripcion}` |

**Ejemplo — productos de una clase:**
```
GET /api/v1/catalogos/producto-sunat/productos?clase=781815
```
```json
{
  "estado": "exito",
  "total": 9,
  "datos": [
    { "codigo": "78181501", "descripcion": "Servicio de pintura o reparación de carrocerías de vehículos" },
    { "codigo": "78181503", "descripcion": "Servicio de cambio fluidos de transmisión o de aceite" }
  ]
}
```

---

## Flujo recomendado para el integrador

1. **Caso simple:** el cliente escribe lo que vende → `GET ...?q=aceite motor` → elige el código.
2. **Verificación:** antes de emitir, `GET .../{codigo}` para confirmar que existe.
3. **Selector visual:** usa los 4 endpoints de drill-down para un combo en cascada.

Una vez que tienes el código, se envía en cada ítem como `cod_producto_sunat` (ver
[04-Facturas.md](04-Facturas.md) y [24-Novedades-SUNAT-2026-2027.md](24-Novedades-SUNAT-2026-2027.md)).

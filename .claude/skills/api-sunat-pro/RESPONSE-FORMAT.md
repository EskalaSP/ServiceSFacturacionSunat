# Formato de respuesta — español unificado

**TODAS** las respuestas de la API siguen esta estructura. Sin excepciones.

## Estructura base

```json
{
  "estado": "exito" | "error",
  "mensaje": "texto humano en español",
  "datos": { ... } | [ ... ] | null,
  "meta":  { ... },              // opcional (paginación, etc.)
  "errores": { ... },            // solo cuando estado=error con detalle por campo
  "codigo_error": "slug",        // opcional — código estable machine-readable
  "siguiente_accion": { ... },   // opcional — endpoint sugerido para desbloquear
  "advertencias": [ ... ]        // opcional — no bloquean éxito
}
```

### Campos extendidos (opcionales)

- **`codigo_error`** — slug estable en snake_case para mapear en el cliente sin parsear `mensaje`. Ejemplos: `limite_alcanzado`, `caracteristica_no_disponible`, `boletas_no_soportadas_en_ra`, `documento_aceptado_no_editable`.
- **`siguiente_accion`** — objeto con `operacion`, `endpoint` y (opcional) `doc_afectado`. Le dice al cliente qué llamar después para desbloquear el flujo. Ver más abajo.
- **`advertencias`** — array de `{codigo, mensaje}`. La API **sí procesó** la request (`estado=exito`), pero avisa de algo que puede salir mal (ej. GRT en beta SUNAT que no valida bien).

## Éxito (200, 201)

```json
{
  "estado": "exito",
  "mensaje": "Factura creada y encolada para envío a SUNAT.",
  "datos": {
    "id": 123,
    "numero_completo": "F001-000123",
    "sunat": {
      "estado": "enviado",
      "codigo": null,
      "descripcion": null,
      "hash_cpe": null
    }
  }
}
```

## Error de validación (422)

```json
{
  "estado": "error",
  "mensaje": "Error de validación",
  "errores": {
    "serie": ["El campo serie es obligatorio."],
    "cliente.tipo_doc": ["El cliente.tipo doc seleccionado no es válido."],
    "items.0.precio_unitario": ["El campo items.0.precio unitario es obligatorio."]
  }
}
```

## Error 404 (recurso no existe)

```json
{
  "estado": "error",
  "mensaje": "Recurso no encontrado."
}
```

## Error 401 (credenciales)

```json
{
  "estado": "error",
  "mensaje": "Credenciales de API inválidas."
}
```

## Error 403 (plan / permisos)

```json
{
  "estado": "error",
  "mensaje": "Desbloquea Finanzas por S/79 al mes.",
  "codigo_error": "caracteristica_no_disponible",
  "caracteristica": "finanzas",
  "mejora_plan": { "slug": "business", "price": 79 }
}
```

## Error 429 (límite plan / rate limit)

```json
{
  "estado": "error",
  "mensaje": "Has alcanzado el límite de documentos. Más por S/29/mes.",
  "codigo_error": "limite_alcanzado",
  "recurso": "documents_month",
  "actual": 30,
  "limite": 30,
  "mejora_plan": { "slug": "pro", "price": 29 }
}
```

## Error accionable (422) — con `codigo_error` + `siguiente_accion`

Cuando un flujo se bloquea por reglas de negocio SUNAT, la API responde con guía concreta:

```json
{
  "estado": "error",
  "mensaje": "No se puede editar una factura aceptada por SUNAT. Emita una Nota de Crédito para corregir o anular.",
  "codigo_error": "documento_aceptado_no_editable",
  "siguiente_accion": {
    "operacion": "emitir_nota_credito",
    "endpoint": "POST /api/v1/notas-credito",
    "doc_afectado": {
      "tipo": "01",
      "serie": "F001",
      "correlativo": "123"
    }
  }
}
```

**Códigos de error conocidos**:

| `codigo_error` | HTTP | Origen | Siguiente acción sugerida |
|---|---|---|---|
| `limite_alcanzado` | 429 | Plan | `PUT /suscripcion/cambiar-plan` |
| `caracteristica_no_disponible` | 403 | Plan | Upgrade a plan superior |
| `documento_aceptado_no_editable` | 422 | Edición post-aceptación | `POST /notas-credito` (facturas/boletas) o `POST /anulaciones` (NC/ND) |
| `boletas_no_soportadas_en_ra` | 422 | Anulación de boleta por RA | `POST /resumenes` con `{"anular":[...]}` |

**Regla del cliente**: si viene `siguiente_accion`, mostrarlo como CTA en UI en vez de un mensaje seco.

## Advertencias en respuestas exitosas

Cuando `estado=exito` incluye `advertencias`, la operación se completó pero hay algo a saber:

```json
{
  "estado": "exito",
  "mensaje": "Guía de Remisión Transportista creada y encolada para envío a SUNAT.",
  "datos": { "id": 12, "numero_completo": "V001-000005", "sunat": {...} },
  "advertencias": [
    {
      "codigo": "grt_beta_no_soportado",
      "mensaje": "SUNAT beta no valida completamente las Guías de Remisión Transportista. Para pruebas realistas cambia entorno a \"production\" en PUT /empresa."
    }
  ]
}
```

Códigos de advertencia conocidos: `grt_beta_no_soportado`.

## Error 500 (interno)

```json
{
  "estado": "error",
  "mensaje": "Error interno del servidor."
}
```

En modo debug (`APP_DEBUG=true`) incluye también `excepcion`, `detalle`, `archivo`.

## Paginación

Los endpoints de listado (GET con colecciones) devuelven paginación anidada:

```json
{
  "estado": "exito",
  "mensaje": "OK",
  "datos": {
    "datos": [
      { "id": 1, ... },
      { "id": 2, ... }
    ],
    "paginacion": {
      "pagina_actual": 1,
      "ultima_pagina": 5,
      "por_pagina": 15,
      "total": 67
    }
  }
}
```

**Query params para paginar**: `?por_pagina=50&pagina=2`

## Códigos HTTP que debes manejar

| Código | Significado | Acción del cliente |
|---|---|---|
| 200 | OK | procesar `datos` |
| 201 | Creado | procesar `datos` con el ID del recurso nuevo |
| 202 | Aceptado (async) | guardar ticket y consultar estado después |
| 400 | Bad request | revisar body enviado |
| 401 | No autenticado | credenciales ausentes o incorrectas |
| 403 | Prohibido | plan insuficiente o cuenta desactivada |
| 404 | No existe | recurso/endpoint inválido |
| 409 | Conflicto | duplicado (serie, correlativo) |
| 422 | Validación | revisar `errores` (objeto con errores por campo) |
| 429 | Rate/plan limit | esperar o upgrade de plan |
| 500 | Server error | reintentar con backoff |
| 502/503/504 | SUNAT caído | reintentar (backoff exponencial) |

## Headers que la API SIEMPRE devuelve

```http
Content-Type: application/json
X-Api-Version: v1
X-Rate-Limit-Remaining: 59        # requests restantes en el minuto
X-Plan-Documents-Remaining: 170   # documentos SUNAT que te quedan del mes
```

## Headers que el cliente DEBE enviar

```http
Accept: application/json          # obligatorio — sin esto puede haber redirects HTML
Content-Type: application/json    # para POST/PUT con body JSON
X-Api-Key: {api_key}
X-Api-Secret: {api_secret}
```

Para uploads (logo, certificado): `Content-Type: multipart/form-data` y los campos como form-data.

## Patrones de manejo de errores por lenguaje

### PHP

```php
try {
    $factura = $sunat->facturas->crear($data);
} catch (SunatValidationException $e) {
    // $e->errors() → array con errores por campo
    return response()->json(['errores' => $e->errors()], 422);
} catch (SunatLimitException $e) {
    // $e->upgrade → info del siguiente plan
    return response()->json(['mensaje' => $e->getMessage()], 429);
} catch (SunatApiException $e) {
    Log::error('SUNAT error', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);
    throw $e;
}
```

### TypeScript

```ts
try {
  const factura = await sunat.facturas.crear(data);
} catch (err) {
  if (err instanceof SunatValidationError) {
    return NextResponse.json({ errores: err.errores }, { status: 422 });
  }
  if (err instanceof SunatLimitError) {
    return NextResponse.json({ mensaje: err.message, mejora_plan: err.upgrade }, { status: 429 });
  }
  console.error('SUNAT error', err);
  throw err;
}
```

### Python

```python
try:
    factura = sunat.facturas.crear(data)
except SunatValidationError as e:
    return JsonResponse({"errores": e.errores}, status=422)
except SunatLimitError as e:
    return JsonResponse({"mensaje": e.mensaje, "mejora_plan": e.upgrade}, status=429)
except SunatAPIError as e:
    logger.error(f"SUNAT error: {e}")
    raise
```

## Reglas para el parser del cliente

1. **Siempre revisar `estado`** antes de leer `datos`:
   ```ts
   if (response.estado === 'error') throw mapError(response);
   return response.datos;
   ```

2. **NO asumir el campo existe**. Ejemplo: `errores` solo aparece en 422, `meta` solo en listados, `codigo_error`/`siguiente_accion` solo en errores accionables, `advertencias` solo cuando aplica.

3. **`datos` puede ser `null`** (ej. DELETE exitoso) — aceptar eso.

4. **Los códigos SUNAT (`sunat.codigo`) son strings**, no integers: `"0"`, `"2335"`, `"100"`.

5. **Preferir `codigo_error` sobre `mensaje`** al ramificar la lógica del cliente: el mensaje puede cambiar por localización o tono; el código es contrato.

6. **Renderizar `siguiente_accion` como CTA**: mostrar botón "Emitir Nota de Crédito" en vez del error crudo.

7. **`advertencias` no bloquean éxito**: procesar `datos` normalmente y mostrar los avisos como banner secundario.

5. **`sunat.estado` tiene valores propios** distintos del wrapper:
   - `pendiente` — creado, aún no enviado
   - `enviado` — job despachó a SUNAT, esperando respuesta
   - `aceptado` — SUNAT OK (código `0` o `3xxx` con observación)
   - `rechazado` — SUNAT rechazó (código `2xxx` o `4xxx`)
   - `anulacion_en_proceso` — baja en curso
   - `anulado` — baja confirmada

## Ejemplo completo de error con tipos

```ts
// response.json() después de 422
{
  "estado": "error",
  "mensaje": "Error de validación",
  "errores": {
    "serie": ["El campo serie es obligatorio."],
    "items.0.cantidad": ["El campo items.0.cantidad debe ser al menos 0.01."]
  }
}
```

El cliente debería exponer esto al consumidor como:
```ts
class SunatValidationError extends Error {
  constructor(
    public mensaje: string,
    public errores: Record<string, string[]>
  ) {
    super(mensaje);
  }
}
```

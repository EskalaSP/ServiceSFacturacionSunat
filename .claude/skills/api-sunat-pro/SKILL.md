---
name: api-sunat-pro
description: Integra la API SUNAT PRO (facturación electrónica Perú) en cualquier proyecto. Soporta PHP, TypeScript/JavaScript, Python, Go, Ruby, Java, C#. Detecta el stack del usuario, propone arquitectura y genera cliente HTTP + ejemplos + tests. Usar cuando el usuario quiere emitir facturas, boletas, notas, guías de remisión o consumir el panel de control de SUNAT desde su app.
version: 1.0.0
---

# API SUNAT PRO — Integración universal

Este skill enseña a Claude Code a integrar la **API SUNAT PRO** (facturación electrónica para Perú) en **cualquier proyecto** y **cualquier lenguaje**.

La API vive en `https://api.kodevo.es/sunat-api/api/v1`. Es multi-tenant: cada empresa tiene su `X-Api-Key` + `X-Api-Secret`. Todas las respuestas siguen el formato español `{estado, mensaje, datos}`.

---

## Cuándo invocar este skill

Usa este skill cuando el usuario:

- Quiere **emitir facturas, boletas, notas de crédito/débito, guías de remisión** en Perú
- Menciona "SUNAT", "facturación electrónica", "comprobantes electrónicos"
- Pide integrar su app (ERP, e-commerce, POS, marketplace) con facturación peruana
- Necesita el panel de indicadores / reportes de ventas
- Tiene un proyecto existente y quiere agregarle facturación SUNAT
- Arranca un proyecto nuevo y pide "hazme un sistema de facturación"

**NO uses este skill** si el usuario:
- Pregunta sobre facturación de otros países (Ecuador, Colombia, México, Chile)
- Pide desarrollo que no toque SUNAT

---

## Workflow obligatorio (sigue estos pasos EN ORDEN)

### Paso 1 — Entender el contexto del usuario

Pregunta (si no está claro):

1. **¿Qué proyecto tienes?** — (a) ninguno aún / (b) proyecto existente (pide `ls` / `tree` para ver el stack)
2. **¿Qué lenguaje + framework?** (si hay proyecto existente, detéctalo automáticamente leyendo `package.json`, `composer.json`, `requirements.txt`, `go.mod`, `pom.xml`, etc.)
3. **¿Qué necesitas integrar?** — (a) solo emitir facturas/boletas / (b) flujo completo (CRUD + webhooks + dashboard) / (c) una operación específica (mencionarla)
4. **¿Producción o pruebas?** — default: apuntar a la beta de SUNAT (`entorno=beta` + credenciales `MODDATOS`)

### Paso 2 — Leer el contexto del skill que corresponde

**Siempre lee primero:**

1. `REFERENCE.md` — listado de TODOS los endpoints (tabla compacta)
2. `RESPONSE-FORMAT.md` — formato `{estado, mensaje, datos}` + manejo de errores
3. `SUNAT-CONCEPTS.md` — conceptos Perú indispensables (RUC, DNI, tipo_operacion, tip_afe_igv, regímenes NRUS/MYPE/general)
4. `WORKFLOWS.md` — los flujos típicos (registro → setup → emisión → descarga)

**Luego lee SOLO el archivo de tu lenguaje:**

- `languages/php.md` — PHP / Laravel / Symfony
- `languages/typescript.md` — Node.js / Next.js / vanilla JS
- `languages/python.md` — Django / FastAPI / Flask
- `languages/go.md` — net/http, chi, fiber
- `languages/ruby.md` — Rails / Sinatra
- `languages/java.md` — Spring Boot
- `languages/csharp.md` — .NET
- `languages/curl.md` — si el usuario pide bash/CLI, o el lenguaje no tiene guía

**Si la arquitectura es especial, lee también:**

- `architectures/monolito.md` — default, lo más simple
- `architectures/microservicios.md` — servicio dedicado de SUNAT
- `architectures/serverless.md` — Lambda / Vercel / Cloudflare Workers
- `architectures/queue-based.md` — procesamiento asíncrono con colas

### Paso 3 — Proponer un plan

Presenta al usuario un **plan de implementación** con:

1. **Qué archivos vas a crear** (cliente HTTP, servicio, modelos DTO, controllers, tests)
2. **Dónde van** en su estructura de proyecto
3. **Variables de entorno** que necesitará (`.env`)
4. **Flujo de uso** (cómo llamar al cliente desde su código existente)

**Confirma antes de implementar.** Si el usuario dice "adelante", proceder. Si pide cambios, ajustar.

### Paso 4 — Implementar en fases

Nunca intentes todo de golpe. Divide en fases:

1. **Fase 1**: Cliente HTTP base + helpers de auth + manejo de errores
2. **Fase 2**: Métodos para el comprobante más importante (normalmente facturas)
3. **Fase 3**: Resto de comprobantes (boletas, NC, ND, guías)
4. **Fase 4**: Extras (webhook handler, panel, reportes, SIRE)
5. **Fase 5**: Tests (unitarios + integración)

Marca cada fase como `in_progress` / `completed` con TaskCreate/TaskUpdate.

### Paso 5 — Verificar con un ejemplo real

Al terminar, **ejecuta una prueba end-to-end** contra la API beta:

1. `GET /planes` (público, sin auth — prueba que la URL responde)
2. Si el usuario tiene credenciales: `GET /empresa` (autenticado)
3. Si quiere emitir: crear una factura con RUC de prueba `20512345678` y los datos del doc de SUNAT

---

## Principios de diseño — mantén la integración PROFESIONAL

### 1. Cliente HTTP reutilizable (nunca cURL inline)

Crea una clase/módulo dedicado, NO dejes `fetch` o `curl_exec` esparcidos por el código del usuario.

**Bien:**
```php
$sunat = new SunatClient($apiKey, $apiSecret);
$factura = $sunat->facturas->crear([...]);
```

**Mal:**
```php
$response = Http::withHeaders([...])->post(...);  // en un controller
```

### 2. Separación en capas

- **Cliente HTTP** (bajo nivel, lanza excepciones si hay error)
- **Servicio de dominio** (lógica de negocio del usuario — ej. `FacturacionService::emitirVenta()`)
- **DTOs / Modelos** tipados para request y response
- **Tests** (mock del HTTP, no golpear API en unit tests)

### 3. Manejo de errores en español

La API devuelve `{estado: "error", mensaje: "...", errores: {...}}`. Mapea esto a excepciones tipadas del lenguaje:

- `SunatValidationException` (422) — con `errores` por campo
- `SunatAuthException` (401/403) — credenciales inválidas
- `SunatLimitException` (429) — plan/rate limit
- `SunatServerException` (500) — error del servidor

### 4. Tipado fuerte donde se pueda

- PHP 8.3+: usa `readonly`, `enum`, property types, `promoted properties`
- TypeScript: genera interfaces desde `REFERENCE.md`
- Python: `dataclass` / `Pydantic`
- Go: structs + JSON tags
- Rust: `serde`
- Java: Records

### 5. Async/queue por defecto para emisión

Emitir un comprobante **no debe bloquear** la request del usuario. El cliente del usuario debe:
1. Crear el comprobante (la API responde inmediato)
2. El job va a SUNAT en background
3. Status cambia: `pendiente` → `enviado` → `aceptado`/`rechazado`
4. Recomienda al usuario implementar un **webhook handler** para recibir el status final

### 6. Variables de entorno, nunca hardcoded

```
SUNAT_BASE_URL=https://api.kodevo.es/sunat-api/api/v1
SUNAT_API_KEY=xxx
SUNAT_API_SECRET=yyy
SUNAT_WEBHOOK_SECRET=zzz     # para validar webhooks entrantes
```

---

## Modos de operación

### Modo A — Integrar en proyecto existente

Cuando el usuario ya tiene código:

1. **Explora su estructura**: `ls`, `cat package.json`, etc.
2. **Detecta su convenciones**: ¿usa DI container? ¿dónde viven los services? ¿cómo maneja HTTP? ¿qué hace con los errores?
3. **Adapta el cliente al estilo del usuario**. NO impongas tu estilo.
4. **Reusa su infraestructura**: su logger, su HTTP client, su queue, su DI.

### Modo B — Construir desde cero

Cuando el usuario no tiene proyecto:

1. **Pregunta el stack** si no lo dijo (o proponlo basado en el caso de uso)
2. **Arma el proyecto** desde `init` (`composer init`, `npm init`, `cargo new`, etc.)
3. **Estructura mínima viable**: cliente + un controller de ejemplo + tests + Docker compose + README
4. **Apunta a la API beta** por default; el usuario cambia a producción cuando esté listo

---

## Checklist final antes de entregar

Antes de decir "listo":

- [ ] El cliente HTTP funciona sin errores de tipo/sintaxis
- [ ] Los ejemplos de código son ejecutables (no pseudocódigo)
- [ ] Las excepciones están mapeadas a los tipos del lenguaje
- [ ] Hay al menos 1 test que prueba el happy path
- [ ] El README tiene el flujo de 3 pasos: instalar → configurar `.env` → emitir
- [ ] Ejecutaste al menos 1 request a la API para validar que todo conecta
- [ ] Los mensajes de usuario están en español (región Perú)

---

## Archivos de referencia del skill

| Archivo | Propósito | Cuándo leer |
|---|---|---|
| `REFERENCE.md` | Todos los endpoints + campos | SIEMPRE (primero) |
| `RESPONSE-FORMAT.md` | `{estado, mensaje, datos}` + errores | SIEMPRE |
| `SUNAT-CONCEPTS.md` | RUC, DNI, tipo_operacion, regímenes | SIEMPRE |
| `WORKFLOWS.md` | Flujos típicos paso a paso | SIEMPRE |
| `languages/{lang}.md` | Cliente HTTP + ejemplos | Uno solo — el del stack del usuario |
| `architectures/{arch}.md` | Patrones arquitectónicos | Solo si aplica |
| `patterns/webhook-handler.md` | Recibir notificaciones SUNAT | Si el usuario lo menciona |
| `patterns/testing.md` | Estrategia de tests | Al terminar |

---

## Ejemplo de interacción típica

**Usuario**: "Tengo un Next.js y quiero agregar facturación electrónica para Perú"

**Tú** (Claude con este skill):
1. Leo `REFERENCE.md`, `RESPONSE-FORMAT.md`, `SUNAT-CONCEPTS.md`, `WORKFLOWS.md` (4 archivos)
2. Leo `languages/typescript.md`
3. Exploro el proyecto del usuario: `ls src/`, `cat package.json`, etc.
4. Detecto: Next.js 15 App Router + TypeScript + Prisma
5. Propongo: `src/lib/sunat/client.ts` + `src/lib/sunat/types.ts` + `src/app/api/sunat/webhook/route.ts` + ejemplo en un server action
6. Confirmo con el usuario
7. Implemento en fases (cliente → factura → webhook → tests)
8. Ejecuto `curl` a la API beta para verificar
9. Entrego README con flujo de 3 pasos

---

## Lo que NUNCA debes hacer

- ❌ Inventar endpoints que no estén en `REFERENCE.md`
- ❌ Inventar campos — si algo no está en el doc, preguntar o dejar un comentario TODO
- ❌ Escribir código en inglés para los mensajes al usuario (tipo "Invoice created" → debe ser "Factura creada")
- ❌ Usar el formato viejo `{success, message, data}` — la API actual usa `{estado, mensaje, datos}`
- ❌ Hardcodear `api_key` / `api_secret` — siempre vía env vars
- ❌ Saltarte el paso de "confirmar el plan" — el usuario debe saber qué vas a tocar
- ❌ Entregar sin ejecutar al menos 1 request real contra la API beta

---

**Última actualización**: 2026-04-19
**Version API objetivo**: v1 (formato respuesta español)
**URL producción**: `https://api.kodevo.es/sunat-api/api/v1`
**URL beta SUNAT**: configurada automáticamente cuando el tenant tiene `entorno=beta`

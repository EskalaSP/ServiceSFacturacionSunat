# 🧾 PROMPT UNIVERSAL — Construir CUALQUIER sistema con Facturación Electrónica SUNAT (Perú)

> **Sirve para cualquier tipo de sistema o aplicación y para cualquier rubro**, en **cualquier
> lenguaje/framework**. Copia **todo** este archivo y pégalo en tu asistente de IA (Claude, Cursor,
> Copilot, v0, etc.). El asistente **primero te hará unas preguntas** (tipo de sistema, rubro,
> documentos que necesitas, stack, base de datos, UI, impresión, escala) y **recién entonces**
> construirá el sistema adaptado a tus respuestas.
>
> El contrato de la API que aparece abajo fue **extraído y verificado del código fuente real**
> (controllers, FormRequests, Resources, middleware) y es **HTTP/JSON puro**: aplica igual en
> PHP, TypeScript/JavaScript, Python, Go, Java, C#, Ruby, Kotlin/Flutter, etc.

---

## 0) LO PRIMERO: haz estas preguntas y ESPERA respuesta (obligatorio)

> **Estilo: sé breve y directo.** Haz las preguntas de forma **corta**, sin explicaciones largas ni
> ensayos. El flujo es: **preguntas → plan corto → (mi OK) → codificar directo.**
>
> 🖱️ **MUY IMPORTANTE — preséntalas como OPCIONES SELECCIONABLES, no para escribir a mano:**
> - Si tu interfaz lo permite (p. ej. Claude con su herramienta de preguntas de opción múltiple /
>   *multiple choice*), **usa esa función**: muéstrame cada pregunta con sus opciones para
>   **marcar/seleccionar** (selección **única** donde corresponda, y **múltiple** en “¿qué
>   comprobantes?”). Yo solo hago clic; **no** me hagas teclear el nombre del stack ni de las opciones.
> - Si tu interfaz NO soporta opciones clicables, entonces preséntalas como **lista enumerada/letras**
>   (a, b, c…) para que yo responda solo con la **letra o número** de cada opción, nunca escribiendo
>   el texto completo.
> - Ofrece siempre una opción **“Otro”** por si mi elección no está en la lista.

No escribas una sola línea de código hasta tener estas respuestas. **Presenta cada pregunta como
opciones para seleccionar/marcar** (según lo de arriba):

**A. ¿Proyecto NUEVO o sistema YA EXISTENTE?** (define todo lo demás) — *marca una*
1. **¿Es un proyecto nuevo (desde cero) o hay que ACOPLAR la facturación a un sistema ya
   desarrollado?** *(opciones: `Nuevo desde cero` · `Acoplar a sistema existente`)*
   - Si es **existente/brownfield**: pídeme **acceso o descripción del código** (repo, stack real,
     estructura de carpetas, framework, base de datos y las entidades clave: venta/pedido/orden,
     cliente, producto). **Analiza el proyecto antes de proponer nada** y luego sigue el
     **“Modo ACOPLE” (sección 2)**: integras como módulo aislado, respetando sus convenciones, sin
     reescribir lo que ya funciona.

**B. Sobre el sistema a construir/integrar**
2. **¿Qué tipo de sistema/aplicación?** — *marca una* (opciones: POS/punto de venta · e‑commerce/
   tienda online · ERP · restaurante · farmacia · ferretería · servicios/consultorio · hotelería ·
   transporte/logística · facturador simple · app de suscripciones/cobros recurrentes · app móvil ·
   Otro).
3. **¿Rubro/industria?** — respuesta corta (texto libre; define catálogo, unidades, etc.).
4. **¿Qué comprobantes necesitas emitir?** — *marca todas las que apliquen* (opciones: Boletas ·
   Facturas · Notas de crédito · Notas de débito · Guías de remisión (remitente/transportista) ·
   Resumen diario · Comunicación de baja · Retenciones · Percepciones · SIRE (registro de compras) ·
   Panel/Reportes).
5. **Escala** — *marca las que apliquen* (opciones: Una empresa · Multi‑empresa · Multi‑sucursal ·
   Modo offline/contingencia) y dime el **volumen aprox** (docs/día).

**C. Sobre el stack** (si es existente, confírmalo contra el código real; no lo cambies sin pedirlo)
6. **¿Con qué stack lo construyo?** — *marca una de frontend y una de backend (o una fullstack)*.
   ⚠️ **SIEMPRE pregunta esto y MUÉSTRAME el menú COMPLETO como opciones SELECCIONABLES — NUNCA lo
   asumas ni pongas un default.** Preséntame TODAS estas opciones (agrupadas) para marcar y espera mi
   elección:

   **Solo frontend / web sin backend propio**
   - **HTML + CSS + JavaScript puro** (vanilla, sin framework)
   - jQuery · Alpine.js · HTMX · Astro · Lit / Web Components
   - **React** (Next.js · Vite · Remix) · **Vue** (Nuxt · Vite) · **Angular** ·
     **Svelte/SvelteKit** · SolidJS · Preact
   - CSS/UI: Tailwind · Bootstrap · Material · shadcn/ui · sin librería

   **Backend (lenguaje / framework)**
   - PHP: **Laravel** · Symfony · CodeIgniter · Slim · PHP puro
   - Node.js: **NestJS** · **Express** · Fastify · AdonisJS · Koa
   - Python: **Django** · **FastAPI** · Flask
   - **Go**: Gin · Echo · Fiber · net/http
   - Java: **Spring Boot** · Quarkus
   - C# / **.NET** (ASP.NET Core)
   - **Ruby on Rails** · Sinatra
   - Rust: Actix · Axum · Kotlin: Ktor · Elixir: Phoenix
   - Fullstack: Next.js · Nuxt · SvelteKit · Laravel + Inertia · Remix

   **Móvil**
   - **Flutter** · **React Native** · Kotlin (Android) · Swift (iOS) · Ionic

   **Escritorio**
   - Electron · Tauri · .NET (WPF/WinForms) · JavaFX · Qt

   - **Otro** (indícalo)
7. **¿Base de datos?** — *marca una* (PostgreSQL · MySQL · SQLite · SQL Server · MongoDB · Firebase ·
   Ninguna).
8. **¿Impresión/formato?** — *marca las que apliquen* (Térmica 80mm · Térmica 58mm · A4 · A5 · PDF ·
   Email · Ninguno).

> 🚫 **Regla dura sobre el stack**: el **stack (pregunta 6) se pregunta SIEMPRE, mostrando el menú
> COMPLETO de arriba**, y **esperas mi respuesta**. Está **prohibido** asumir un stack por defecto o
> decir “doy por default …”. Solo puedes aplicar defaults a lo secundario (base de datos,
> impresión) si no respondo, y avisándolo.
>
> ⚠️ **Nota si elijo un frontend sin backend** (HTML/CSS/JS puro, SPA sola, etc.): el `api_secret`
> **no puede** ir ahí. Adviérteme y propón una de estas: (a) un mini‑backend/proxy (function
> serverless, PHP simple, Node…) que guarde el secret y reenvíe las llamadas, o (b) usarlo solo en
> `beta`/demo asumiendo el riesgo. **Nunca** pongas el `api_secret` en código que corre en el
> navegador.

Con esas respuestas, **adapta TODO este documento** a ese caso, manteniendo intactos: el contrato
de la API (secciones 6–7), los principios de arquitectura (sección 4), las reglas de diseño
(sección 5) y el checklist (sección 11). **Construye solo los módulos que el tipo de sistema y los
comprobantes elegidos requieran** (no metas guías de remisión si es una cafetería, ni SIRE si no
lo pidieron).

### Antes de codificar: confirma el plan (obligatorio, pero CORTO)

Tras recibir las respuestas (y, si es brownfield, tras analizar el código), **devuelve un plan
breve** (idealmente media pantalla, en viñetas — **sin ensayos ni relámpagos fiscales**) y espera
mi OK:
1. Qué entendiste en 1–2 líneas (tipo de sistema, comprobantes, stack).
2. Módulos/endpoints que usarás y pantallas/piezas que crearás (lista corta).
3. Migraciones/archivos principales (lista corta). La tabla de mapeo detallada solo si te la pido.
4. Supuestos clave y 1–2 preguntas abiertas si las hay.

**No escribas el sistema hasta que apruebe.** Cuando diga “dale/OK”, **empieza a codificar directo**
por el esqueleto (migraciones + cliente HTTP + servicio de emisión + pantalla principal), sin más
preámbulos.

---

## 1) Rol y objetivo

Eres un desarrollador full‑stack senior, experto en integraciones de facturación electrónica en
Perú. Vas a construir el sistema que el usuario definió en la sección 0, que **consume una API de
facturación SUNAT ya existente** (no la construyes: solo la consumes vía HTTP) para emitir
comprobantes electrónicos **reales** y gestionar su ciclo de vida (emitir, consultar estado,
imprimir/descargar, corregir/anular).

Prioriza: **corrección fiscal**, **cero errores de integración**, **buena UX** y una **interfaz
limpia y bonita**.

---

## 2) Modo ACOPLE — integrar a un sistema YA existente (brownfield)

Si el sistema ya existe, **no reescribas**: acopla la facturación como un módulo aislado.

1. **Analiza primero** el proyecto: stack real, estructura de carpetas, convenciones (naming,
   capas, estilo), base de datos y **entidades clave** (venta/pedido/orden, cliente, producto/
   servicio, pago). No propongas nada hasta entender cómo funciona hoy.
2. **Respeta sus convenciones**: usa el mismo lenguaje, patrones, capa de acceso a datos y estilo
   de código que ya tiene. Nada de introducir un framework/paradigma nuevo sin pedirlo.
3. **Integra como módulo/servicio aislado** (`FacturacionSunat`/`billing`): un **adaptador** que
   contiene el cliente HTTP + la capa de mapeo, con una interfaz clara
   (`emitirComprobante(operacion) → resultado`). El resto del sistema lo llama; no esparzas
   llamadas HTTP por todo el código.
4. **Cambios mínimos y reversibles**: agrega, no rompas. Idealmente detrás de un **feature flag**.
   Migraciones **aditivas** (columnas nuevas, no alterar/renombrar existentes).
5. **Enlaza con lo que ya existe**: guarda en la entidad de venta/pedido los campos nuevos
   (`comprobante_id`, `numero_completo`, `sunat_estado`, `pdf_url`), sin duplicar su modelo.
6. **Punto de enganche**: define claramente cuándo se emite (al confirmar la venta, al pagar el
   pedido, al cerrar la comanda, vía botón manual, o por webhook/evento del sistema).
7. **No toques** la lógica de negocio existente más allá de lo necesario para emitir; si hay que
   cambiar algo del core, **avísalo en el plan** y pide confirmación.

### 2.1 Tabla de mapeo (entidades del sistema → payload SUNAT)

Antes de codificar, entrega esta tabla (rellena con los nombres reales del sistema):

| Campo SUNAT (payload) | Origen en el sistema existente | Notas |
|---|---|---|
| `serie` / correlativo | serie configurada por tipo | boleta `B…`, factura `F…` (autoincrementa) |
| `fecha_emision` | fecha de la venta/pedido | `YYYY-MM-DD` |
| `cliente.tipo_doc` / `num_doc` / `razon_social` / `direccion` | cliente del sistema | `0/1/6…`; RUC obligatorio en factura |
| `items[].descripcion` / `cantidad` / `precio_unitario` | líneas de la venta | `precio_unitario` **incluye IGV** |
| `items[].unidad` | unidad del producto | `NIU/KGM/ZZ…` (Cat 65) |
| `items[].tip_afe_igv` | afectación IGV del producto | `10` gravado / `20` exonerado… |
| `forma_pago` / `pagos[]` | condición y pagos del sistema | `Contado/Credito` |
| (retorno) `comprobante_id`, `numero_completo`, `sunat_estado`, `pdf_url` | se guardan en la venta | para reconsulta/impresión |

---

## 3) Principios generales (para cualquier stack y rubro)

- **Idioma**: español (Perú). Moneda por defecto `PEN`, zona horaria `America/Lima`.
- **Alcance ajustado al pedido**: construye lo necesario para el tipo de sistema; no
  sobre‑ingenierices.
- **Fuente de verdad fiscal = la API SUNAT.** En tu BD local guarda solo lo necesario para operar
  y reconsultar (id del comprobante, número, estado, total, referencias).
- **Todo tipado/validado** según el lenguaje (tipos, DTOs, schemas). Sin valores “mágicos”.
- **Comenta** la integración con SUNAT (el porqué, no solo el qué).
- **Comunicación concisa**: explica lo justo, ve al grano y **prioriza entregar código** sobre
  escribir texto. Nada de muros de texto ni análisis largos salvo que los pida.

---

## 4) Arquitectura robusta y escalable (agnóstica)

Implementa estas capas, con los nombres/idiomas del stack elegido:

1. **Cliente HTTP de la API SUNAT (pieza única reutilizable)**
   - Encapsula `base_url` + cabeceras de auth; **un solo lugar** arma las peticiones.
   - **Timeouts** (30–60s en emisión) y **reintentos con backoff** solo para red/5xx (nunca
     reintentar 4xx de validación).
   - **Idempotencia**: guarda el `id` devuelto y **reconsulta** en vez de reemitir; nunca dupliques
     una emisión.
   - Parsea el **envelope** (6.2) y **normaliza los errores** a un tipo propio.

2. **Servicios / casos de uso** (VentaService, ComprobanteService, ClienteService…): arman payloads
   y aplican reglas de negocio (S/700, RUC en factura, régimen) **antes** de llamar a la API.

3. **Persistencia local** (repositorios): catálogo, ventas/operaciones, config.

4. **Presentación / UI** (o API pública si no hay UI).

5. **Configuración por entorno**: `beta` (pruebas) y `production` (real) separados; credenciales por
   variables de entorno / secretos.

6. **Observabilidad**: log de cada emisión (número, estado SUNAT, código de error). **No** loguees
   el `api_secret`.

7. **Escalabilidad** (si el volumen lo pide): emisión **asíncrona** (cola/worker) reflejando
   `pendiente → aceptado` con reconsulta; cachea catálogos; **pagina siempre** los listados;
   soporta multi‑empresa/multi‑sucursal si aplica.

### 🔒 Seguridad (no negociable, cualquier lenguaje)

- **El `api_secret` NUNCA en el cliente/navegador/app.** Vive solo en el **backend** (variables de
  entorno o secreto cifrado). Si el frontend es SPA/móvil, **todas** las llamadas a la API SUNAT
  pasan por **tu** backend, que agrega las cabeceras. El cliente nunca habla directo con la API SUNAT.
- Credenciales cifradas en reposo si van a BD; nunca al repositorio.

---

## 5) Reglas de diseño (OBLIGATORIAS, agnósticas de UI)

Estilo **sobrio, limpio y bonito**. NADA de “dashboard recargado”.

- ❌ **Sin degradados**. ❌ **Sin sombras exageradas** (bordes finos; máximo una sombra muy sutil).
  ❌ **Nada de colores chillones** ni muchos colores a la vez.
- ✅ **Paleta sobria**: **un** color de acento adecuado al rubro sobre **neutros** (grises). Fondo
  claro, texto gris oscuro, bordes gris claro.
- ✅ **Iconos bonitos y consistentes**, con la librería idiomática del stack (Web: Lucide,
  Heroicons, Tabler, Feather · Flutter: Material Symbols/Lucide · React Native: lucide-react-native).
- ✅ Tipografía limpia (Inter o similar), buen espaciado y jerarquía; bordes redondeados moderados.
- ✅ **Badges de estado suaves**: verde tenue (aceptado), ámbar (pendiente/enviado), rojo tenue
  (rechazado), gris (anulado).
- ✅ Responsive y **usable con teclado** (crítico en un POS).
- ✅ Modo oscuro **opcional**, con la misma sobriedad.
- ✅ Componentes: una librería de UI **bonita y sobria** del ecosistema (shadcn/ui o Radix en React;
  PrimeVue/Naive en Vue; Angular Material; Material en Flutter).

---

## 6) Contrato REAL de la API (verificado del código fuente) — HTTP/JSON

### 6.1 Base URL y autenticación

- **Base URL** por variable de entorno: `https://TU-DOMINIO/api/v1`
  (ejemplo real: `https://api.kodevo.es/sunat-api/api/v1`).
- **Toda** petición autenticada lleva (también aceptadas como query `?api_key=&api_secret=`):

  ```http
  X-Api-Key: {api_key}
  X-Api-Secret: {api_secret}
  Accept: application/json
  Content-Type: application/json      # excepto multipart/form-data en subidas
  ```

- Rutas **públicas** (sin auth): `POST /registro`, `GET /planes`, recuperación de credenciales.

### 6.2 Envelope estándar

```jsonc
{
  "estado": "exito",            // "exito" | "error"
  "mensaje": "texto legible",
  "datos": { },                  // objeto, array o null
  "meta": { },                   // opcional
  "errores": { },                // solo en validación (422)
  "codigo_error": "...",         // solo en algunos errores de negocio
  "siguiente_accion": { }        // solo en algunos errores (acción sugerida)
}
```

> ⚠️ **Dos excepciones reales**: (1) en **listados** la paginación va anidada en
> `datos.paginacion` (no en `meta`); (2) los endpoints de **pagos** devuelven **JSON plano sin
> envelope** (ver 6.12).

### 6.3 Errores (formas reales)

| HTTP | Cuándo | Cuerpo |
|---|---|---|
| **401** | Faltan cabeceras o credenciales inválidas | `{estado:"error", mensaje:"..."}` |
| **403** | Empresa desactivada / suscripción vencida | vencida: `{codigo_error:"subscription_expired", plan_actual, recurso}` |
| **422** | Validación | `{mensaje:"Error de validación", errores:{ "campo.con.punto":["msg"] }}` |
| **429** | Cupo del plan alcanzado | `{codigo_error:"limit_reached", plan_actual, recurso, actual, limite, mejora_plan:{…}}` |
| **404/405/500** | No encontrado / método / interno | `{estado:"error", mensaje:"..."}` |

- `pendiente`/`enviado` en `datos.sunat.estado` **NO son errores** (envío asíncrono).

### 6.4 Registrar la empresa (UNA vez) → api_key / api_secret

`POST /registro` — **multipart/form-data**, SIN auth. Reglas reales:

| Campo | Regla |
|---|---|
| `ruc` | requerido, 11 dígitos, único |
| `razon_social` | requerido, máx 255 |
| `nombre_comercial` | opcional, máx 255 |
| `direccion` | requerido, máx 500 |
| `ubigeo` | requerido, 6 dígitos |
| `departamento`/`provincia`/`distrito` | opcional, máx 100 |
| `sol_user` | requerido, máx 20 (usuario SOL) |
| `sol_pass` | requerido, máx 50 (clave SOL) |
| `entorno` | opcional, **`beta`** \| `production` (default `beta`) |
| `plan` | opcional, **`free`** \| `pro` \| `business` (default `free`) |
| `tax_regime` | opcional, `rus`\|`rer`\|`mype`\|**`general`** (default `general`) |
| `nrus_categoria` | opcional, `1`\|`2` (solo si `tax_regime=rus`) |
| `certificado` | **requerido, archivo, MÁX 100 KB**, `.pfx/.p12/.pem/.cer/.crt` |
| `contrasena_certificado` | opcional, máx 100 (**obligatorio si `.pfx`/`.p12`**) |
| `logo` | opcional, imagen jpg/png, máx 2 MB |
| `client_id`/`client_secret` | opcional (para guías GRE) |

**Respuesta 201** `datos`: `tenant_id, ruc, razon_social, entorno, plan, tax_regime, **api_key**,
**api_secret** (texto plano, ÚNICA vez), importante`. **Guárdalos en el servidor de inmediato.**
El secret no se recupera; para rotarlo: `POST /empresa/credenciales/regenerar` (invalida los
anteriores). `GET /empresa/credenciales` devuelve el `api_key` con secret oculto.

### 6.5 Configuración mínima

- **Series**: atajo `POST /series/init-defaults`
  `{serie_factura:"F001", correlativo_factura:0, serie_boleta:"B001", correlativo_boleta:0}`.
  `GET /series` lista. El correlativo autoincrementa si lo omites. (`POST /series` en 6.13.)
- **Sucursales** (si multi‑sucursal): `POST/GET/PUT/DELETE /sucursales` (`nombre`, `cod_local`
  4 dígitos, `direccion`, `ubigeo`…).
- **Clientes**: `POST /clientes` (idempotente por tenant+doc): `tipo_doc` ∈ `0,1,4,6,7,A`,
  `num_doc`, `razon_social`, … Listado en `datos.datos` + `datos.paginacion`.
- **Buscar RUC/DNI**: `GET /buscar-documento?tipo=6&numero=20512345678`
  (`1`=DNI,`4`=CE,`6`=RUC,`7`=Pasaporte,`0`=sin RUC) → `datos:{tipo_doc,num_doc,razon_social,
  direccion,...}`; 404 si no existe.

### 6.6 Emitir BOLETA (`POST /boletas`)

```json
{
  "serie": "B001", "fecha_emision": "2026-06-27", "tipo_moneda": "PEN", "forma_pago": "Contado",
  "cliente": { "tipo_doc": "0", "num_doc": "00000000", "razon_social": "CLIENTES VARIOS" },
  "items": [ { "codigo": "P001", "descripcion": "Producto", "unidad": "NIU",
    "cantidad": 2, "precio_unitario": 5.00, "tip_afe_igv": "10" } ],
  "enviar_automatico": true
}
```

Reglas reales: `serie` `/^B[A-Z0-9]{3}$/`; `cliente.tipo_doc` Cat 06; **si total > S/700 el
`tipo_doc` no puede ser `0`** (exige DNI 8 díg o RUC 11 díg); `precio_unitario` **incluye IGV**;
`tip_afe_igv` Cat 07 (`10` gravado, `20` exonerado…); `unidad` Cat 65 (`NIU`,`KGM`,`ZZ`…);
`forma_pago` `Contado|Credito` (Credito exige `cuotas`); puedes **omitir montos** (el backend
recalcula); `enviar_automatico` default true; `solo_registro` para resumen diario. Puedes incluir
`pagos:[…]`.

### 6.7 Emitir FACTURA (`POST /facturas`)

Igual que boleta salvo: `serie` `/^F[A-Z0-9]{3}$/`; `tipo_operacion` Cat 51 (`0101` venta interna);
**cliente RUC obligatorio** (`tipo_doc:"6"`, 11 dígitos prefijo 10/15/17/20, `direccion`); NRUS no
factura. Existe `POST /facturas/masivo` (array `facturas`, máx 100).

### 6.8 Respuesta de un comprobante (`datos`)

`id, tipo_documento ("01"/"03"), serie, correlativo, numero_completo, fecha_emision, tipo_moneda,
forma_pago, cliente{…}, totales{gravadas,exoneradas,inafectas,exportacion,gratuitas,igv,isc,icbper,
total_impuestos,valor_venta,sub_total,total}, items[…] (si ?con=items), sunat{estado,codigo,
descripcion,notas,hash_cpe}, archivos{xml,cdr,pdf}, estado_pago, monto_pagado, pagos[…] (si
?con=payments), enviado_en, creado_en`.

### 6.9 Estados SUNAT (asíncrono)

`datos.sunat.estado` ∈ `pendiente·enviado·aceptado·rechazado·anulado·anulacion_en_proceso`.
`aceptado`=OK; `enviado/pendiente`=procesando (reconsulta con `GET /{tipo}/{id}` o reenvía
`POST /{tipo}/{id}/reenviar`); `rechazado`=corrige y reenvía (`PUT /{tipo}/{id}`). ⚠️ `PUT` sobre
documento **aceptado** → **422 accionable** `codigo_error:"documento_aceptado_no_editable"` con
`siguiente_accion` = emitir **nota de crédito**.

### 6.10 Descargas / impresión

`GET /{tipo}/{id}/pdf?format=` (válidos: `a4, a5, ticket-80, ticket-58`) · `/xml` · `/cdr`.
Descarga desde **tu backend** (con cabeceras) y reenvía el binario; no expongas el secret.

### 6.11 Listados

`GET /boletas` · `/facturas` (y análogos): `datos:{ datos:[…], paginacion:{pagina_actual,
ultima_pagina,por_pagina,total} }`. Query: `por_pagina` (máx 100), `con=items,payments`,
`fecha_desde/hasta`, `sunat_status` (coma), `estado_pago`, `serie`, `correlativo`, `sucursal_id`,
`cliente`, `client_num_doc`, `search`/`q`, `ordenar_por`, `orden`.

### 6.12 Pagos (JSON plano, SIN envelope)

`POST /{boletas|facturas|notas-venta}/{id}/pagos`: `metodo` ∈ `efectivo,yape,plin,transferencia,
tarjeta,cheque,otro`; `monto` min 0.01; `monto_recibido` (vuelto en efectivo); `referencia`,
`notas`. Respuesta: `{mensaje, estado_pago, monto_pagado, pagos:[{…,vuelto}]}`. `GET`/`DELETE`
recalculan.

### 6.13 Series (detalle)

`POST /series`: `{tipo:"boleta"|"factura"|…, serie:"B001", sucursal_id:1, correlativo_inicial:0}`.
`serie` = `/^[A-Z][A-Z0-9]{3}$/` con prefijo por tipo. Respuesta `datos:{creadas:[…],errores?:[…]}`.

---

## 7) Catálogo COMPLETO de módulos de la API (elige según el tipo de sistema)

Todos bajo `…/api/v1` con las cabeceras de auth. **Usa solo los que tu sistema necesite.**

- **Configuración**: `POST /registro` · `GET/PUT /empresa` · `POST /empresa/logo` ·
  `POST /empresa/certificado` · `GET /empresa/credenciales` · `POST /empresa/credenciales/regenerar`
  · `GET /buscar-documento` · `GET /planes`.
- **Sucursales**: CRUD `…/sucursales`. **Series**: CRUD `…/series` + `POST /series/init-defaults`.
  **Clientes**: CRUD `…/clientes`.
- **Régimen tributario**: vía `PUT /empresa` (`tax_regime`, `igv_rate_override`, `nrus_categoria`).
- **Suscripción y planes**: `GET/POST /suscripcion`, `PUT /suscripcion/cambiar-plan`,
  `PUT /suscripcion/cancelar`, `GET /suscripcion/pagos`, `GET /suscripcion/uso`.
- **Facturas (01)**: `POST/GET/PUT /facturas`, `POST /facturas/masivo`, `/{id}/enviar|reenviar`,
  `/{id}/pagos`, `/{id}/xml|cdr|pdf`.
- **Boletas (03)**: `POST/GET/PUT/DELETE /boletas`, `/{id}/enviar|reenviar`, `/{id}/pagos`,
  `/{id}/xml|cdr|pdf`.
- **Notas de crédito (07)**: `POST/GET/PUT /notas-credito`, `/{id}/enviar|reenviar|xml|cdr|pdf`
  (anular/devolver/descontar un comprobante).
- **Notas de débito (08)**: `POST/GET/PUT /notas-debito` (intereses/penalidades/cargos).
- **Resumen diario (RC)**: `POST/GET /resumenes`, `/{id}/estado|enviar|xml|cdr` (envío de boletas
  en lote y anulación de boletas).
- **Comunicación de baja (RA/RR)**: `POST/GET /anulaciones`, `/{id}/estado|enviar` (anular
  facturas/NC/ND emitidas).
- **Guías de remisión remitente (09)**: `POST/GET/PUT /guias-remision`,
  `/{id}/enviar|estado|xml|pdf`.
- **Guía de remisión transportista (31)**: `POST /guias-remision-transportista` (o
  `/guias-remision` con `tipo_documento=31`).
- **Retenciones (20)** / **Percepciones (40)**: `POST/GET /retenciones` · `/percepciones`,
  `/{id}/enviar|xml|cdr|pdf`.
- **Envío manual / actualizar**: `POST /{tipo}/{id}/enviar` · `PUT /{tipo}/{id}`.
- **Consultas**: `GET /consultar-cpe` (valida un CPE en SUNAT) · `POST /consultar-cdr` ·
  `GET /comprobantes/exportar-zip` (XML/PDF por rango).
- **Panel/Dashboard** (GET, en `datos`): `/panel`, `/panel/indicadores`, `/panel/estado-sunat`,
  `/panel/cobranzas`, `/panel/ventas-mensuales`, `/panel/por-sucursal`, `/panel/por-moneda`,
  `/panel/clientes`, `/panel/productos`, `/panel/documentos-recientes`, `/panel/alertas`.
- **Reportes**: `/reportes/registro-ventas`, `/reportes/ventas-consolidado`, `/reportes/notas`,
  `/reportes/cobranzas`, `/reportes/documentos-internos`, `/reportes/por-cliente`,
  `/reportes/por-sucursal`.
- **SIRE (Registro de Compras RCE)**: `POST /sire/activar|desactivar`, `GET /sire/periodos`,
  `/sire/rce/{periodo}/propuesta|resumen|aceptar-propuesta|…`, `/sire/tickets/…` (contabilidad de
  compras — solo si el sistema lo necesita).

---

## 8) Cómo adaptar por TIPO de sistema (ejemplos)

- **POS / retail / minimarket**: catálogo + carrito → **boletas** (consumidor final) y **facturas**
  (RUC); pagos con vuelto; ticket 80mm; historial; panel del día. Notas de crédito para
  devoluciones.
- **Restaurante / cafetería**: mesas/comandas → boleta/factura; propina opcional; ticket 80mm.
- **Farmacia / ferretería**: catálogo grande con código, unidades variadas (`NIU`,`KGM`,`LTR`);
  boletas/facturas; reportes.
- **E‑commerce**: al confirmar pago del pedido → emitir factura/boleta automáticamente
  (`enviar_automatico:true`), enviar PDF por email, webhook de estado.
- **Servicios / consultorio / suscripciones**: emisión por servicio o **cobro recurrente**;
  `unidad:"ZZ"`; facturas con RUC; notas de crédito por anulación.
- **Transporte / logística**: **guías de remisión** (remitente/transportista) además de la venta.
- **Facturador simple / integración a un sistema existente**: solo el cliente HTTP + endpoints de
  emisión y consulta; sin UI propia (o UI mínima).

Construye catálogo, flujos y pantallas **según el tipo elegido**; reutiliza el mismo cliente HTTP y
el mismo manejo de estados/errores.

---

## 9) Modelo de datos local (mínimo, agnóstico)

- **Catálogo** (Producto/Servicio): `id, codigo, nombre, precio, unidad, tip_afe_igv, categoria,
  activo`.
- **Operación/Venta**: `id, tipo, comprobante_id (id de la API), numero_completo, total,
  sunat_estado, cliente_doc?, cliente_nombre?, metodo_pago, creado_en`.
- **OperacionItem**: `id, operacion_id, codigo, descripcion, cantidad, precio_unitario, unidad`.
- **ConfigEmpresa**: `ruc, razon_social, entorno, series, configurada`.
  (`api_key`/`api_secret` en variables de entorno o cifrados; **nunca** en el cliente.)

---

## 10) Flujo de emisión (paso a paso, sin errores)

1. El usuario confirma la operación (venta/pedido/servicio).
2. El **cliente/UI** envía la operación a **tu backend**, sin cabeceras SUNAT.
3. El **backend** arma el payload (boleta/factura/…), valida reglas (S/700, RUC en factura,
   régimen), agrega las 2 cabeceras y hace `POST` a la API (timeout + reintento solo red/5xx +
   idempotencia).
4. Interpreta la respuesta: `exito` → guarda local con `datos.id` y `sunat.estado` y devuelve el
   resultado + URL de descarga; `error` → devuelve `mensaje`/`errores`/`codigo_error` (maneja
   `429 limit_reached` y `403 subscription_expired` con mensajes claros).
5. La UI muestra el resultado y, si aceptado/pendiente, ofrece **imprimir/descargar/enviar**.
6. `pendiente`/`rechazado` → historial permite reconsultar/reenviar/corregir.

### Prueba de humo (hazla al terminar, en `entorno: beta`)

Verifica el circuito completo end‑to‑end antes de dar por listo:
1. `POST /registro` (beta) → recibes `api_key`/`api_secret` y los guardas en el servidor.
2. `POST /series/init-defaults` → `F001`/`B001` creadas.
3. `POST /boletas` (consumidor final) → respuesta `estado:"exito"` y `datos.sunat.estado` en
   `aceptado`/`enviado`/`pendiente` (no error).
4. `GET /boletas/{id}/pdf?format=ticket-80` → descarga el PDF.
5. `POST /facturas` con un RUC de prueba → aceptada/pendiente.
6. Fuerza un error controlado (ej. factura con `tipo_doc:"1"`) y comprueba que muestras el `422`
   con `errores` correctamente.
Documenta el resultado en el README.

---

## 11) Criterios de aceptación (checklist — DEBE cumplirse)

- [ ] Se preguntó y respetó **tipo de sistema, rubro, comprobantes, stack, BD, UI, impresión**
      (sección 0), y **solo** se construyeron los módulos necesarios.
- [ ] Se entregó y aprobó el **plan** antes de codificar; en brownfield se **analizó el código
      existente** y se respetaron sus convenciones.
- [ ] Si es acople (brownfield): integración como **módulo aislado** con **tabla de mapeo**,
      cambios **aditivos/reversibles** y sin romper la lógica existente.
- [ ] **Prueba de humo** en `beta` pasada de punta a punta (registro → serie → emitir → PDF).
- [ ] El `api_secret` **nunca** llega al cliente; todas las llamadas salen del backend con las 2
      cabeceras.
- [ ] Cliente HTTP **reutilizable** con timeout, reintento solo ante red/5xx e **idempotencia**.
- [ ] Registro (multipart, certificado ≤ 100 KB) OK y credenciales persistidas en el servidor.
- [ ] Series creadas (`/series/init-defaults` o `/series`).
- [ ] Emisión correcta de los comprobantes elegidos; se respeta la **regla S/700** y el **RUC
      obligatorio en factura**.
- [ ] Se maneja el envelope, la **paginación anidada** en `datos.paginacion` y los **pagos sin
      envelope**.
- [ ] Estados SUNAT con badges; `pendiente`/`enviado` **no** se tratan como error.
- [ ] `PUT` sobre documento aceptado → se muestra la `siguiente_accion` (nota de crédito).
- [ ] Descarga/impresión en el formato pedido (`ticket-80/58`, `a4/a5`) o email/PDF.
- [ ] Errores 401/403/422/429 manejados con mensajes útiles (incl. límite de plan).
- [ ] Diseño cumple la sección 5 (sin degradados, sin sombras exageradas, sobrio, iconos bonitos).
- [ ] Código tipado/validado, sin valores “mágicos”, compila/pasa lint sin errores.
- [ ] `README` con: cómo correr, variables de entorno, cómo registrar la empresa y crear series,
      y cómo cambiar `beta`→`production`.

---

## 12) Apéndice — Enums reales (del código)

- **`cliente.tipo_doc` (Cat 06)**: `0` sin doc · `1` DNI (8 díg) · `4` CE · `6` RUC (11 díg) ·
  `7` Pasaporte · `A` Céd. Diplomática.
- **`tip_afe_igv` (Cat 07)**: `10` gravado · `20` exonerado · `30` inafecto · `40` exportación
  (+ variantes 11-17, 31-37).
- **`tipo_operacion` (Cat 51)**: `0101` venta interna · `0112` sustenta gastos PN · `0113` NRUS ·
  `0200+` exportación · `1001+` detracción · `2001` percepción.
- **`tipo_moneda`**: `PEN, USD, EUR`. · **`forma_pago`**: `Contado, Credito`.
- **métodos de pago**: `efectivo, yape, plin, transferencia, tarjeta, cheque, otro`.
- **formatos PDF**: `a4, a5, ticket-80, ticket-58`.
- **`unidad` (Cat 65, típicas)**: `NIU` unidad · `KGM` kg · `GRM` gramo · `LTR` litro · `PK`
  paquete · `DZN` docena · `ZZ` servicio.
- **`sunat_status`**: `pendiente, enviado, aceptado, rechazado, anulado, anulacion_en_proceso`.
- **`payment_status`**: `pagado, parcial, pendiente`.
- **`tax_regime`**: `rus, rer, mype, general` (rus solo boletas). · **`entorno`**: `beta,
  production`. · **`plan`**: `free, pro, business`. · **`nrus_categoria`**: `1, 2`.
- **tipos de serie**: `factura`→F(01) · `boleta`→B(03) · `nota_credito`→(07) · `nota_debito`→(08)
  · `guia_remision`→T(09) · `guia_transportista`→V(31) · `retencion`→R(20) · `percepcion`→P(40).

---

## 13) Referencia detallada por endpoint (verificada del código, con RESPUESTAS)

> La colección de Postman trae los requests pero **no las respuestas**. Esta sección documenta, del
> código fuente real, el **request y la respuesta** de cada módulo (más allá de boletas/facturas de
> la sección 6). Úsala como fuente de verdad para no fallar. Convención: `datos` = objeto dentro del
> envelope, salvo donde se indique.

### 13.0 Reglas de negocio de Facturas/Boletas (mensajes EXACTOS de error 422)

Estas validaciones corren en el servidor y devuelven 422 con `errores`:
- **Factura requiere RUC**: si `cliente.tipo_doc !== "6"` → error en `cliente.tipo_doc`: *"Las facturas
  requieren cliente con RUC (tipo_doc=6). Para clientes con DNI u otros documentos use POST /boletas."*
- **RUC 11 díg / prefijo**: `num_doc` 11 dígitos; prefijo `10|15|17|20`.
- **NRUS no factura**: régimen `rus` → error `tax_regime` *"Los contribuyentes del régimen NRUS solo
  pueden emitir boletas de venta, no facturas."*
- **Boleta > S/700**: si total > 700 y `cliente.tipo_doc === "0"` → error `cliente.tipo_doc` *"Para
  boletas mayores a S/ 700.00 es obligatorio consignar el documento de identidad del cliente…"*
- **DNI 8 díg** (tipo_doc 1), **RUC 11 díg** (tipo_doc 6).
- **Crédito exige cuotas**: `forma_pago="Credito"` sin `cuotas[]` → error `cuotas`.
- **Correlativo manual duplicado** → error `correlativo`.
- Campos avanzados válidos en el request: `descuentos[]` (item, Cat 53), `descuentos_globales[]`
  (**porcentaje en escala factor 0–1**, no 0–100), `detraccion{cod_bien Cat54, porcentaje, cta_banco,
  cod_medio_pago Cat59, monto}`, `anticipos[]{tipo_doc Cat12, serie, correlativo, monto}`,
  `percepcion{cod_regimen Cat22, porcentaje, monto, base}`, `cuotas[]{monto, fecha_pago}`,
  `leyendas[]{code Cat52, value}`, `guias[]{tipo_doc Cat01, nro_doc}`, `isc/tip_sis_isc(Cat08)`,
  `icbper/factor_icbper`, `pagos[]` (aquí `metodo` SIN enum), `contrato_colaboracion`.
- **`POST /facturas/masivo`**: `{facturas:[…]}` máx **100**; respuesta
  `datos:{resumen:{total_enviadas,creadas,errores}, facturas:[{indice,id,numero_completo,total,estado_sunat}],
  errores:[{indice,mensaje,data}]}`; **201 si ≥1 creada, 422 si 0**.
- **`DELETE /boletas/{id}`**: solo si `sunat_status ∈ {pendiente,rechazado}` y sin `hash_cpe`; si no →
  422 *"…anularla vía resumen RC."* (facturas NO tienen DELETE).

### 13.1 Notas de Crédito (07) — `POST /notas-credito`
Request: `serie`(size4), `correlativo`(nullable), `fecha_emision`, `tipo_moneda`(PEN,USD,EUR),
`cliente{tipo_doc Cat06, num_doc, razon_social, direccion?}`, **documento afectado**:
`doc_afectado_tipo` **in:01,03,12**, `doc_afectado_serie`(size4), `doc_afectado_correlativo`,
`cod_motivo` **Cat 09**, `des_motivo`(max250); `items[]` (igual que factura). Descuentos_globales solo
tipo 04.
- **Cat 09 (motivos NC)**: `01` anulación operación · `02` anulación error RUC · `03` corrección
  descripción · `04` descuento global · `05` descuento por ítem · `06` devolución total/parcial ·
  `07` bonificación/descuento · `08` disminución valor · `09` otros. **(no hay 10–13.)**
- Regla 422: **importe NC ≤ documento afectado** (factura estricto; boleta +1.0 tolerancia; ERR-3286/3503).
  NRUS solo NC contra boletas (03).
- Respuesta `datos`: `id, tipo_documento:"07", serie, correlativo, numero_completo, fecha_emision,
  tipo_moneda, cliente{}, doc_afectado{tipo,serie,correlativo,motivo_codigo,motivo_descripcion},
  totales{…}, items[] (whenLoaded), sunat{estado,codigo,descripcion,notas,hash_cpe}, archivos{xml,cdr,pdf},
  leyenda, observacion, enviado_en, creado_en`.
- `PUT` sobre NC aceptada → 422 `codigo_error:"documento_aceptado_no_editable"`, `siguiente_accion` =
  **anular por comunicación de baja** (`POST /anulaciones`).
- Rutas: `GET/PUT`, `/{id}/xml|cdr|pdf`, `/{id}/enviar|reenviar`. Índice: `datos:{datos:[],paginacion{}}`.

### 13.2 Notas de Débito (08) — `POST /notas-debito`
Igual que NC salvo:
- `cod_motivo` usa **Cat 10**: `01` intereses mora · `02` aumento valor · `03` otros conceptos ·
  `04` ajuste export · `05` ajuste moneda · `06` ajuste cantidad · `07` ajuste descuentos no aplicados ·
  `08` cargos adicionales · `09` otros · `11` ajuste export · `12` ajuste IVAP · `13` penalidades.
- **doc afectado OPCIONAL solo si `cod_motivo=13`** (penalidades); motivo 13 exige IGV/IVAP = 0.
- No valida tope de importe. `tipo_documento:"08"`.

### 13.3 Resumen Diario (RC) — `POST /resumenes`  ⚠️ también anula BOLETAS
**Las boletas NO se anulan por `/anulaciones`; se anulan aquí con el array `anular`.**
Request (`StoreSummaryRequest`): `fecha_resumen`(required date; ventana hoy-7d…hoy Lima),
`anular?[]{id, motivo}` (si viene → modo anulación; si no → envío de boletas pendientes de esa fecha),
`enviar_automatico`(default true).
- Modo **envío**: toma auto todas las boletas del tenant con esa `fecha_emision` y `sunat_status=pendiente`.
- Modo **anulación**: solo boletas `aceptado`; pasan a `anulacion_en_proceso`.
- Respuesta `datos` (**plano**): `id_resumen, identifier:"RC-YYYYMMDD-001", fecha_envio, fecha_documentos,
  correlativo, accion:"envio"|"anulacion", total_documentos, estado_sunat:"enviado"|"pendiente",
  documentos:[{id,numero,total}], consulta_estado:URL`.
- `GET /resumenes` (query `mes=YYYY-MM`, `tipo`, `por_pagina`), `GET /resumenes/{id}/estado` (poll:
  `estado_sunat` pasa `enviado`→`aceptado`/`rechazado` vía ticket), `POST /{id}/enviar`, `GET /{id}/xml|cdr`.

### 13.4 Comunicación de Baja (RA) — `POST /anulaciones`  (facturas, NC, ND — NO boletas)
Request (`StoreVoidedRequest`): `fecha_generacion`(required date), `fecha_comunicacion?`(default hoy),
`detalles[]{tipo_documento in:01,07,08 (03 se rechaza), serie(size4), correlativo, motivo(max255)}`,
`enviar_automatico`(default true).
- Si mandas tipo `03` (boleta) → 422 `codigo_error:"boletas_no_soportadas_en_ra"` con `siguiente_accion`
  → `POST /resumenes`. Reglas 422: doc no existe / no aceptado / plazo 7 días / factura con NC / duplicado.
- Respuesta `datos` (plano): `id_anulacion, identifier:"RA-YYYYMMDD-001", correlativo, fecha_comunicacion,
  total_documentos, estado_sunat, consulta_estado:URL`.
- `GET /anulaciones` (query `estado`, `fecha_desde/hasta`, `per_page`; paginación en `meta`),
  `GET /{id}` y `/{id}/estado` (consulta SUNAT en vivo vía ticket → `aceptado`/`rechazado`;
  códigos 0/187 = aún procesando), `POST /{id}/enviar`. NO tiene rutas xml/cdr.

### 13.5 Guías de Remisión — Remitente (09) y Transportista (31)
`POST /guias-remision` (GRR, `tipo_documento` default 09) · `POST /guias-remision-transportista` (GRT, 31).
Request base (`StoreDispatchGuideRequest`): `serie`(size4), `fecha_emision`,
`destinatario{tipo_doc in:1,6, num_doc, razon_social}`, **traslado**: `cod_traslado`(Cat20),
`mod_traslado` **in:01(público),02(privado)**, `fecha_traslado`, `peso_total`(gt0), `und_peso_total`(def KGM),
`num_bultos?`; **direcciones**: `partida_ubigeo`(size6), `partida_direccion`, `llegada_ubigeo`(size6),
`llegada_direccion`; `items[]{descripcion, cantidad, unidad?, codigo?}`.
- **GRR público (01)**: `transportista{tipo_doc,num_doc,razon_social,nro_mtc?}` requerido; desde 2026-06-01
  exige `fecha_entrega_transportista`; `transportista.num_doc` ≠ RUC del emisor.
- **GRR privado (02)**: `vehiculo{placa …}` + al menos un `conductor{tipo_doc,num_doc,licencia,nombres…}`.
- **GRT (31)**: `remitente{…}` **obligatorio** y ≠ RUC emisor; `doc_relacionado[]` **obligatorio ≥1**
  (`tipo_codigo ∈ {01,03,09,31,50,52}`, con `ruc_emisor` si 09/31); `vehiculo`+`conductor` **siempre**;
  `datos_subcontratador?`, `datos_pagador_flete{tipo in:remitente,subcontratador,tercero}` si subcontrata.
- Respuesta `datos` (`DispatchGuideResource`): `id, serie, correlativo, numero_completo, fecha_emision,
  destinatario{}, envio{cod_traslado,mod_traslado,fecha_traslado,fecha_entrega_transportista,peso_total,
  und_peso_total}, direcciones{llegada{ubigeo,direccion},partida{…}}, observacion, transportista, vehiculo,
  conductor, num_bultos, items[], sunat{estado,codigo,descripcion,ticket}, archivos{xml,cdr,pdf}, creado_en`.
  Si GRT + entorno beta → añade `advertencias:[{codigo:"grt_beta_no_soportado", …}]`.
- **GRE usa OAuth2** (`client_id`/`client_secret`): no se valida en el request → si faltan, el 201 sale
  igual pero luego `sunat_status:"rechazado"`. Consultar `GET /guias-remision/{id}/estado` (202 si sin
  ticket, con `siguiente_accion`). `PUT` solo si pendiente/rechazado. PDF/XML normales.

### 13.6 Retenciones (20) y Percepciones (40) — `datos` PLANO (sin `sunat{}`/`archivos`)
**Retención `POST /retenciones`**: `serie` regex `^R…`, `fecha_emision`,
`proveedor{tipo_doc Cat06, num_doc, razon_social, direccion?}`, `regimen` **Cat 23** (`01`=3%, `02`=6%),
`tasa`(numérico obligatorio 0–100), `documentos[]{tipo_doc in:01,03,12, num_doc, fecha_emision, imp_total,
moneda?, pagos[]{importe,fecha,moneda?}, fecha_retencion, imp_retenido?, imp_pagar?, tipo_cambio?}`.
- Respuesta `datos`: `id, serie, correlativo, numero, fecha_emision, proveedor{}, regimen, tasa,
  imp_retenido, imp_pagado, sunat_status, sunat_code, created_at` (+ `documentos[]` en show).
- Rutas: `GET`, `/{id}`, `/{id}/enviar`, `/{id}/xml|cdr|pdf` (a4,a5,ticket-80,ticket-58).

**Percepción `POST /percepciones`**: igual con `serie ^P…`, `cliente{…}`, `regimen` **Cat 22**
(`01`=Venta interna **2%**, `02`=Combustible **1%**, `03`=Agente especial **0.5%**), `documentos[]` con
`cobros[]` (en vez de `pagos`), `fecha_percepcion`, `imp_percibido?`, `imp_cobrar?` (**suma** al total).
- Respuesta `datos`: `id, serie, correlativo, numero, fecha_emision, cliente{}, regimen, tasa,
  imp_percibido, imp_cobrado, sunat_status, sunat_code, created_at`.
- **Percepciones NO tiene endpoint `/pdf`** (solo retenciones). Anulación de ret./perc. = **Reversión**:
  `POST /reversiones`.
- (También existe percepción embebida en factura: `percepcion{cod_regimen Cat22, porcentaje, monto, base}`.)

### 13.7 Consultas
- **`GET /consultar-cpe`** (valida un CPE en SUNAT): query `tipo_doc` in:01,03,04,07,08,R1,R7, `serie`,
  `correlativo`, `fecha_emision` (**dd/mm/yyyy**), **`monto`** (¡no `monto_total`!), `ruc_emisor?`
  (default = tu RUC). Requiere que el tenant tenga credenciales de Consulta Integrada CPE. `datos`:
  `{encontrado, estado_cp, estado_cp_descripcion (0 no existe,1 aceptado,2 anulado,3 autorizado,4 no
  autorizado), estado_ruc, cond_domi_ruc, observaciones[], …}`.
- **`POST /consultar-cdr`**: body `{tipo_documento in:01,03,07,08, serie, correlativo}`. `datos`:
  `{success, cdr_zip(base64), code, description, notes[], accepted}` o `{success:false, error_code, error_message}`.

### 13.8 Exportar y Reportes
- **`GET /comprobantes/exportar-zip`**: query `fecha_desde`,`fecha_hasta`(≤366 días), `tipo`
  (`xml|pdf|ambos`), `documentos` (CSV: `facturas,boletas,notas-credito,notas-debito`), `sucursal_id?`,
  `estado?`. **Descarga ZIP síncrona** (`application/zip`), no JSON.
- **Reportes** (`GET /reportes/…`): params comunes `fecha_desde`,`fecha_hasta`,`sucursal_id?`,`serie?`,
  `client_num_doc?`,`estado_sunat?`,`estado_pago?`,`tipo_moneda?`,`formato(json|pdf)`,`agrupacion(dia|semana|mes)`.
  Endpoints: `registro-ventas`, `ventas-consolidado` (KPIs + desgloses + top clientes/productos),
  `notas`, `cobranzas` (aging + `vencido?`), `documentos-internos`, `por-cliente` (**`client_num_doc`
  obligatorio**), `por-sucursal`. Cada uno devuelve `datos:{titulo, periodo, …}`.

### 13.9 Suscripción y planes
- **`GET /planes`** (público): `datos:[{slug, nombre, precio_mensual, precio_anual, limites{documents_month,
  ai_messages_month, team_members, sucursales, productos (-1=ilimitado)}, caracteristicas{}}]`.
- `GET /suscripcion`, `POST /suscripcion` (`plan_slug`, `ciclo_facturacion` monthly|yearly, `token?`,
  `prueba?`=trial 14 días), `PUT /suscripcion/cambiar-plan`, `PUT /suscripcion/cancelar`,
  `GET /suscripcion/pagos`, `GET /suscripcion/uso` (`datos:{plan, usage{documents_month{current,limit,
  unlimited}, …}, features, next_upgrade}`).

### 13.10 Empresa — subidas (multipart)
- **`POST /empresa/logo`**: campo `logo` (image jpg,jpeg,png,webp, máx **2048 KB**) → `datos:{logo_path, logo_url}`.
- **`POST /empresa/certificado`**: `certificado` (file, máx **100 KB**, ext `pfx,p12,pem,cer,crt`),
  `contrasena_certificado` (obligatoria si `.pfx/.p12`). Convierte a PEM. `datos:null`.

### 13.11 Documentos internos (NO son SUNAT — no se envían a SUNAT)
- **Cotizaciones** `POST /cotizaciones`: `fecha_emision`, `cliente{tipo_doc,num_doc,razon_social}`,
  `items[]{descripcion,cantidad,precio_unitario,unidad}`; estados `vigente/aceptada/rechazada/vencida`
  (`PUT /cotizaciones/{id}/estado`); `GET /{id}/pdf`.
- **Notas de venta** `POST /notas-venta`: igual + `forma_pago?`, `items.*.tip_afe_igv?`; soporta pagos
  (`/{id}/pagos`) y `estado_pago`. Útiles como comprobante interno / pre-venta antes de emitir a SUNAT.

### 13.12 SIRE (Registro de Compras RCE) — solo si el sistema lo necesita
`POST /sire/activar` (valida credenciales SIRE del tenant) · `GET /sire/periodos` ·
`GET /sire/rce/{periodo}/propuesta` (**async → 202 con `num_ticket`**) · `GET /sire/tickets/{numTicket}`
(+ `/archivo`) · `aceptar-propuesta` · `registrar-preliminar` · `resumen`/`constancia` · reconciliación.
Flujo asíncrono basado en tickets (poll de `/sire/tickets/{numTicket}`).

---

### Notas finales para la IA

- **Empieza SIEMPRE preguntando** (sección 0): tipo de sistema, rubro, comprobantes y stack.
  Adapta todo a esas respuestas y construye **solo lo necesario**.
- **No inventes endpoints ni campos**: usa exactamente los de las secciones 6, 7, 12 y 13 (verificados
  del código fuente, con sus respuestas reales). Si necesitas algo no listado, pídelo primero.
- Trabaja en **`entorno: beta`** para pruebas antes de `production`.
- Entrega algo **simple, robusto, seguro y bonito**: correcto fiscalmente, con el secret protegido y
  manejo de errores sólido.

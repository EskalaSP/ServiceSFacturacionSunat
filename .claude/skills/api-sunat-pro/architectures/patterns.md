# Patrones arquitectónicos — cómo estructurar la integración

Según el tamaño del proyecto y sus necesidades, elige el patrón que mejor encaja.

---

## 1. Monolito simple (default, lo más común)

Apropiado para: apps chicas-medianas, POS, e-commerce básico, SaaS vertical.

```
┌──────────────────────────────────────────────┐
│  Tu App (Laravel / Django / Rails / Next)    │
│                                              │
│  [Controller] → [Service] → [SunatClient]    │
│                                ↓             │
│                            HTTP → API SUNAT  │
│                                PRO            │
└──────────────────────────────────────────────┘
```

- Cliente HTTP inyectado via DI
- Services llaman al cliente
- Controllers son delgados
- Tests unitarios con mock del cliente

**Pro**: simple, rápido de implementar, fácil de debuggear.
**Contra**: si tu app se cae, no puedes emitir facturas (aunque API PRO siga).

---

## 2. Queue-based (async por defecto)

Apropiado para: cuando emitir > 100 comprobantes/día, o picos de tráfico.

```
┌────────────────┐       ┌───────────┐        ┌──────────────┐
│  Controller    │ push  │   Queue   │ pop    │   Worker     │
│  (Request)     │ ───>  │  (Redis/  │ ─────> │  (Sidekiq/   │
│                │       │   DB)     │        │  Bull/Celery)│
└────────────────┘       └───────────┘        └──────┬───────┘
                                                      │
                                                      ↓
                                              [SunatClient]
                                                      │
                                                      ↓
                                           HTTP → API SUNAT PRO
```

**Flujo**:
1. Usuario clickea "Emitir" → controller guarda en BD local (status=pendiente)
2. Controller pushea job a cola
3. Worker procesa job → llama SunatClient → guarda resultado
4. Si webhook → escucha el evento final de SUNAT
5. UI hace polling O usa WebSocket/SSE para actualizar status

**Ventajas**:
- UI responsive aunque SUNAT tarde
- Retry automático en caso de falla temporal
- Backpressure natural

**Librerías**:
- PHP: Laravel Queues (con Redis/SQS)
- Node: BullMQ, Bee-Queue
- Python: Celery, RQ, Dramatiq
- Go: River, Asynq
- Ruby: Sidekiq

---

## 3. Microservicio dedicado (Sunat Service)

Apropiado para: varios equipos/apps dentro de una empresa, múltiples tenants.

```
┌──────────────┐    ┌──────────────┐    ┌─────────────┐
│  POS App     │    │  E-commerce  │    │  CRM        │
│              │    │              │    │             │
└──────┬───────┘    └──────┬───────┘    └──────┬──────┘
       │                   │                    │
       └─────────┬─────────┴────────────────────┘
                 │
                 ↓   (REST / gRPC / RPC)
       ┌─────────────────────┐
       │  Sunat Service      │  ← tu microservicio
       │  (wrapper interno)  │
       └──────────┬──────────┘
                  │
                  ↓
          [SunatClient]
                  │
                  ↓
           HTTP → API SUNAT PRO
```

**Cuándo**:
- Varias apps necesitan facturar
- Quieres abstracción adicional (tu API no expone SUNAT directamente)
- Necesitas límites/auth propios por app interna

**Estructura del microservicio**:
- `/emitir-factura`, `/emitir-boleta` → endpoints propios
- Internamente: BD local + queue + SunatClient
- Autenticación propia (JWT, API keys internas)
- Opcionalmente: cache de clientes, retry personalizado, analytics

---

## 4. Serverless / Edge

Apropiado para: Vercel, Cloudflare Workers, AWS Lambda, spiky traffic.

```
┌──────────────────────────────┐
│  Next.js / Astro / SvelteKit │
│  (frontend estático)          │
└──────────────┬───────────────┘
               │
               ↓ fetch
┌──────────────────────────────┐
│  Edge Function / Lambda      │
│  (Vercel route / Workers)    │
│                              │
│  [SunatClient inline]        │
└──────────────┬───────────────┘
               │
               ↓ HTTP
     API SUNAT PRO
```

**Consideraciones**:
- Cold starts: la primera request tarda más
- Sin estado persistente: NO guardes `api_key` en memoria (usa KV store / env)
- Timeout típico 10-30s: puede ser corto para descargar PDF → stream o redirect
- Webhook: mismo endpoint funciona bien en serverless
- Queue: usa Upstash, CloudFlare Queues, SQS

**Ejemplo Vercel**:
```typescript
// app/api/emitir-factura/route.ts
export const runtime = 'edge';  // o 'nodejs' si usas Buffer

export async function POST(req: Request) {
  const client = new SunatClient({
    baseUrl: process.env.SUNAT_BASE_URL!,
    apiKey: process.env.SUNAT_API_KEY!,
    apiSecret: process.env.SUNAT_API_SECRET!,
  });
  const body = await req.json();
  const factura = await client.facturas.crear(body);
  return Response.json({ factura });
}
```

---

## 5. Multi-tenant (tu app es SaaS)

Apropiado para: construyes un SaaS donde cada cliente tiene su propio tenant de API SUNAT PRO.

```
┌──────────────────────────────────────────┐
│  Tu SaaS                                 │
│                                          │
│  Request del usuario X                   │
│        ↓                                 │
│  [Detectar su tenant] → BD: api_key_X    │
│        ↓                                 │
│  [SunatClient con api_key_X]             │
│        ↓                                 │
│  HTTP → API SUNAT PRO (tenant X)         │
└──────────────────────────────────────────┘
```

**Estrategia**:
1. Guardar `api_key` + `api_secret` cifrados por cada empresa en tu BD
2. Factory del SunatClient que acepta las credenciales del tenant actual
3. NO hacer singleton global — instanciar por request
4. Rotar credenciales si el tenant las cambia

**Ejemplo Laravel**:
```php
public function getSunatClient(Tenant $tenant): SunatClient
{
    return new SunatClient(
        config('sunat.base_url'),
        decrypt($tenant->sunat_api_key),
        decrypt($tenant->sunat_api_secret),
    );
}
```

---

## 6. Híbrido: main app + worker dedicado

Apropiado para: app con frontend usable aunque el emisor esté ocupado.

```
┌──────────────────────────────────────┐
│  Main App (Laravel/Rails)             │
│  - CRUD local                         │
│  - Usuario ve UI instantánea          │
│  - Guarda en BD status=pendiente      │
│  - Push a cola                        │
└──────────────┬───────────────────────┘
               │
               ↓
       ┌───────────────┐
       │   Queue       │
       └───────┬───────┘
               │
               ↓
┌──────────────────────────────────────┐
│  Sunat Worker (process separado)     │
│  - Pop jobs                          │
│  - SunatClient.crear(...)            │
│  - Update status en BD               │
│  - Broadcast evento por WebSocket    │
└──────────────────────────────────────┘
```

**Setup**:
- Supervisor / systemd / Kubernetes Deployment para el worker
- Un contenedor por proceso
- Shared BD (MySQL/Postgres) + queue (Redis)
- WebSocket opcional (Pusher, Reverb, socket.io) para update en tiempo real

---

## 7. Webhook-first (receiver only)

Si solo consumes eventos (no emites desde aquí):

```
┌─────────────────────────────┐
│  API SUNAT PRO              │
│  dispara webhook            │
└──────────────┬──────────────┘
               │
               ↓  POST /webhook/sunat
┌─────────────────────────────┐
│  Tu App                     │
│  [Webhook Handler]          │
│  - Valida signature         │
│  - Guarda evento en BD      │
│  - Procesa async            │
└─────────────────────────────┘
```

**Uso típico**: dashboard de reporting, sincronización con ERP externo, notificaciones.

---

## Decisión rápida

| Situación | Patrón recomendado |
|---|---|
| App chica-mediana emitiendo < 100/día | **Monolito simple** |
| SaaS con múltiples empresas clientes | **Multi-tenant** |
| Volumen alto o picos | **Queue-based** |
| Varias apps internas emiten | **Microservicio dedicado** |
| Next.js / Vercel | **Serverless** |
| Solo consumir eventos | **Webhook-first** |
| Enterprise con equipos separados | **Híbrido + microservicio** |

---

## Decisiones técnicas comunes

### ¿Dónde guardar api_key + api_secret?

- **NUNCA** en código / git.
- **Dev**: `.env` (gitignored).
- **Producción**:
  - Vercel: env vars del dashboard
  - AWS: Secrets Manager / Parameter Store
  - Kubernetes: Secrets + rotación
  - Laravel Forge / Docker: `.env` + mount como secret
  - Multi-tenant: BD cifrada (`encrypted` columns, Laravel `encrypted` cast)

### ¿Retries ante fallo?

- Nuestra API ya hace retry contra SUNAT — no repitas al emitir
- Si fallan llamadas a API PRO por red: exponential backoff, max 3-5 intentos
- Idempotencia: si reintentas `POST /facturas` con mismos datos, NO duplica (la API usa lock + revisa serie+correlativo)

### ¿Cuándo leer estado final?

- Si hay webhook: wait for event
- Si no: poll cada 3-5s por hasta 60s, luego abandonar y revisar con cron después
- Mostrar en UI: estado `enviado` con spinner hasta `aceptado`/`rechazado`

### ¿Cómo manejar descargas (PDF, XML, CDR)?

- Devolver al usuario como download (Content-Disposition: attachment)
- Cachear en disco/S3 (el PDF no cambia — cachear al final)
- NO descargar si el documento aún no está aceptado

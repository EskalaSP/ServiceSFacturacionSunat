# TypeScript / JavaScript — Integración

Stack: **Node.js 18+**, Next.js, Remix, NestJS, Express, Fastify, vanilla JS.

---

## 1. Cliente HTTP base (reutilizable)

Crea `src/lib/sunat/client.ts`:

```typescript
// src/lib/sunat/client.ts
export interface SunatConfig {
  baseUrl: string;
  apiKey: string;
  apiSecret: string;
}

export interface SunatResponse<T = unknown> {
  estado: 'exito' | 'error';
  mensaje: string;
  datos?: T;
  meta?: Record<string, unknown>;
  errores?: Record<string, string[]>;
  codigo_error?: string;
}

export class SunatApiError extends Error {
  constructor(
    public status: number,
    public mensaje: string,
    public codigoError?: string,
  ) {
    super(mensaje);
    this.name = 'SunatApiError';
  }
}

export class SunatValidationError extends SunatApiError {
  constructor(
    mensaje: string,
    public errores: Record<string, string[]>,
  ) {
    super(422, mensaje);
    this.name = 'SunatValidationError';
  }
}

export class SunatLimitError extends SunatApiError {
  constructor(
    mensaje: string,
    public mejoraPlan?: { slug: string; price: number },
  ) {
    super(429, mensaje, 'limite_alcanzado');
    this.name = 'SunatLimitError';
  }
}

export class SunatClient {
  constructor(private config: SunatConfig) {}

  async request<T>(
    method: 'GET' | 'POST' | 'PUT' | 'DELETE',
    path: string,
    body?: unknown,
  ): Promise<T> {
    const url = `${this.config.baseUrl}${path}`;

    const headers: Record<string, string> = {
      'Accept': 'application/json',
      'X-Api-Key': this.config.apiKey,
      'X-Api-Secret': this.config.apiSecret,
    };

    if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    // Descargas binarias (XML, PDF, CDR) → no parsear como JSON
    const ct = response.headers.get('content-type') ?? '';
    if (!ct.includes('application/json')) {
      if (!response.ok) {
        throw new SunatApiError(response.status, `Error ${response.status}`);
      }
      return (await response.blob()) as T;
    }

    const data: SunatResponse<T> = await response.json();

    if (response.ok && data.estado === 'exito') {
      return data.datos as T;
    }

    // Mapeo de errores
    if (response.status === 422) {
      throw new SunatValidationError(data.mensaje, data.errores ?? {});
    }
    if (response.status === 429) {
      throw new SunatLimitError(data.mensaje, (data as any).mejora_plan);
    }
    throw new SunatApiError(response.status, data.mensaje, data.codigo_error);
  }

  // Shortcuts
  get<T>(path: string) { return this.request<T>('GET', path); }
  post<T>(path: string, body: unknown) { return this.request<T>('POST', path, body); }
  put<T>(path: string, body: unknown) { return this.request<T>('PUT', path, body); }
  delete<T>(path: string) { return this.request<T>('DELETE', path); }

  // Namespaces
  get facturas() { return new FacturasResource(this); }
  get boletas() { return new BoletasResource(this); }
  get clientes() { return new ClientesResource(this); }
  get empresa() { return new EmpresaResource(this); }
}
```

---

## 2. Resources por recurso

`src/lib/sunat/resources/facturas.ts`:

```typescript
import { SunatClient } from '../client';
import type { Factura, CrearFacturaInput, FacturaListParams, Paginado } from '../types';

export class FacturasResource {
  constructor(private client: SunatClient) {}

  crear(data: CrearFacturaInput): Promise<Factura> {
    return this.client.post<Factura>('/facturas', data);
  }

  ver(id: number): Promise<Factura> {
    return this.client.get<Factura>(`/facturas/${id}`);
  }

  listar(params: FacturaListParams = {}): Promise<Paginado<Factura>> {
    const qs = new URLSearchParams(params as any).toString();
    return this.client.get<Paginado<Factura>>(`/facturas${qs ? `?${qs}` : ''}`);
  }

  actualizar(id: number, data: Partial<CrearFacturaInput>): Promise<Factura> {
    return this.client.put<Factura>(`/facturas/${id}`, data);
  }

  enviar(id: number): Promise<Factura> {
    return this.client.post<Factura>(`/facturas/${id}/enviar`, {});
  }

  pdf(id: number, formato: 'a4' | 'a5' | 'ticket-80' | 'ticket-58' = 'a4'): Promise<Blob> {
    return this.client.request<Blob>('GET', `/facturas/${id}/pdf?format=${formato}`);
  }

  xml(id: number): Promise<Blob> {
    return this.client.request<Blob>('GET', `/facturas/${id}/xml`);
  }

  cdr(id: number): Promise<Blob> {
    return this.client.request<Blob>('GET', `/facturas/${id}/cdr`);
  }
}
```

---

## 3. Types compartidos

`src/lib/sunat/types.ts`:

```typescript
export interface Paginado<T> {
  datos: T[];
  paginacion: {
    pagina_actual: number;
    ultima_pagina: number;
    por_pagina: number;
    total: number;
  };
}

export interface Cliente {
  tipo_doc: '0' | '1' | '4' | '6' | '7' | 'A';
  num_doc: string;
  razon_social: string;
  direccion?: string;
  email?: string;
  telefono?: string;
}

export interface Item {
  codigo: string;
  descripcion: string;
  unidad: string;       // NIU, ZZ, KGM, etc.
  cantidad: number;
  precio_unitario: number;
  tip_afe_igv: '10' | '20' | '30' | '40';   // Cat. 07
  descuento?: number;
}

export interface CrearFacturaInput {
  serie: string;
  fecha_emision: string;
  fecha_vencimiento?: string;
  tipo_operacion?: string;
  tipo_moneda?: 'PEN' | 'USD';
  forma_pago?: 'Contado' | 'Credito';
  cliente: Cliente;
  items: Item[];
  enviar_automatico?: boolean;
  observacion?: string;
  leyenda?: string;
}

export interface SunatEstado {
  estado: 'pendiente' | 'enviado' | 'aceptado' | 'rechazado' | 'anulado';
  codigo: string | null;
  descripcion: string | null;
  notas: string[] | null;
  hash_cpe: string | null;
}

export interface Factura {
  id: number;
  tipo_documento: '01';
  serie: string;
  correlativo: number;
  numero_completo: string;
  fecha_emision: string;
  tipo_operacion: string;
  tipo_moneda: string;
  cliente: Cliente;
  totales: {
    gravadas: number;
    igv: number;
    total_impuestos: number;
    valor_venta: number;
    sub_total: number;
    total: number;
  };
  items: Item[];
  sunat: SunatEstado;
  archivos: {
    xml: string | null;
    cdr: string | null;
    pdf: string | null;
  };
  creado_en: string;
  enviado_en: string | null;
}

export interface FacturaListParams {
  serie?: string;
  fecha_desde?: string;
  fecha_hasta?: string;
  sunat_status?: 'pendiente' | 'enviado' | 'aceptado' | 'rechazado';
  por_pagina?: number;
  pagina?: number;
}
```

---

## 4. Uso en Next.js App Router

`src/app/api/facturas/route.ts`:

```typescript
import { SunatClient, SunatValidationError } from '@/lib/sunat/client';
import { NextResponse } from 'next/server';

const sunat = new SunatClient({
  baseUrl: process.env.SUNAT_BASE_URL!,
  apiKey: process.env.SUNAT_API_KEY!,
  apiSecret: process.env.SUNAT_API_SECRET!,
});

export async function POST(req: Request) {
  try {
    const body = await req.json();
    const factura = await sunat.facturas.crear(body);
    return NextResponse.json({ ok: true, factura });
  } catch (err) {
    if (err instanceof SunatValidationError) {
      return NextResponse.json(
        { ok: false, errores: err.errores },
        { status: 422 }
      );
    }
    console.error('Error al emitir factura:', err);
    return NextResponse.json(
      { ok: false, mensaje: 'Error al emitir factura' },
      { status: 500 }
    );
  }
}
```

---

## 5. Server Action (Next.js RSC)

```typescript
'use server';

import { SunatClient } from '@/lib/sunat/client';

export async function emitirFactura(formData: FormData) {
  const sunat = new SunatClient({ /* env */ });

  const factura = await sunat.facturas.crear({
    serie: 'F001',
    fecha_emision: new Date().toISOString().split('T')[0],
    cliente: {
      tipo_doc: '6',
      num_doc: formData.get('ruc') as string,
      razon_social: formData.get('razon_social') as string,
    },
    items: [/* parse from formData */],
  });

  revalidatePath('/facturas');
  return factura;
}
```

---

## 6. Express / Fastify

```typescript
import express from 'express';
import { SunatClient, SunatValidationError } from './lib/sunat/client';

const app = express();
const sunat = new SunatClient({
  baseUrl: process.env.SUNAT_BASE_URL!,
  apiKey: process.env.SUNAT_API_KEY!,
  apiSecret: process.env.SUNAT_API_SECRET!,
});

app.post('/facturas', express.json(), async (req, res) => {
  try {
    const factura = await sunat.facturas.crear(req.body);
    res.status(201).json({ ok: true, factura });
  } catch (err) {
    if (err instanceof SunatValidationError) {
      res.status(422).json({ errores: err.errores });
    } else {
      res.status(500).json({ mensaje: 'Error interno' });
    }
  }
});
```

---

## 7. Webhook handler (Next.js)

`src/app/api/sunat/webhook/route.ts`:

```typescript
import { NextResponse } from 'next/server';

export async function POST(req: Request) {
  const payload = await req.json();

  // payload.event: "document.sent" | "document.rejected"
  // payload.model: "Invoice" | "Boleta" | ...
  // payload.data: { numero, sunat_status, sunat_code, hash_cpe }

  switch (payload.event) {
    case 'document.sent':
      if (payload.data.sunat_status === 'aceptado') {
        // Actualizar BD local, notificar cliente por email, etc.
        await db.invoice.update({
          where: { externalId: payload.id },
          data: {
            estado: 'aceptado',
            hashCpe: payload.data.hash_cpe,
          },
        });
        await enviarEmailFactura(payload.id);
      }
      break;
    case 'document.rejected':
      // Alertar al equipo
      await slack.notify(`Factura ${payload.data.numero} rechazada: ${payload.data.sunat_description}`);
      break;
  }

  return NextResponse.json({ ok: true });
}
```

---

## 8. Testing (Vitest)

`tests/sunat.test.ts`:

```typescript
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { SunatClient, SunatValidationError } from '@/lib/sunat/client';

describe('SunatClient', () => {
  let client: SunatClient;

  beforeEach(() => {
    client = new SunatClient({
      baseUrl: 'http://mock',
      apiKey: 'test',
      apiSecret: 'test',
    });
  });

  it('crea factura exitosamente', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: true,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({
        estado: 'exito',
        mensaje: 'Creado',
        datos: { id: 1, numero_completo: 'F001-000001' },
      }),
    });

    const factura = await client.facturas.crear({
      serie: 'F001',
      fecha_emision: '2026-04-19',
      cliente: { tipo_doc: '6', num_doc: '20000000001', razon_social: 'TEST' },
      items: [{ codigo: 'P', descripcion: 'X', unidad: 'NIU', cantidad: 1, precio_unitario: 100, tip_afe_igv: '10' }],
    });

    expect(factura.id).toBe(1);
  });

  it('lanza SunatValidationError en 422', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: false,
      status: 422,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({
        estado: 'error',
        mensaje: 'Error de validación',
        errores: { serie: ['El campo serie es obligatorio.'] },
      }),
    });

    await expect(
      client.facturas.crear({ /* vacío */ } as any)
    ).rejects.toThrow(SunatValidationError);
  });
});
```

---

## 9. Estructura de carpetas recomendada

```
src/
├── lib/
│   └── sunat/
│       ├── client.ts            # SunatClient + errores
│       ├── types.ts             # Interfaces/types
│       ├── resources/
│       │   ├── facturas.ts
│       │   ├── boletas.ts
│       │   ├── notas-credito.ts
│       │   ├── notas-debito.ts
│       │   ├── guias-remision.ts
│       │   ├── resumenes.ts
│       │   ├── anulaciones.ts
│       │   ├── panel.ts
│       │   └── empresa.ts
│       └── index.ts             # export * from ...
├── services/
│   └── facturacion.ts           # lógica de dominio del usuario
└── app/                         # Next.js routes o Express controllers
    ├── api/sunat/webhook/route.ts
    ├── api/facturas/route.ts
    └── ...
```

---

## 10. `.env` (ejemplo)

```
SUNAT_BASE_URL=https://api.kodevo.es/sunat-api/api/v1
SUNAT_API_KEY=xxx
SUNAT_API_SECRET=yyy

# opcional
SUNAT_WEBHOOK_SECRET=zzz
```

---

## 11. Dependencias recomendadas

```json
{
  "dependencies": {
    "zod": "^3.23.0"
  },
  "devDependencies": {
    "vitest": "^1.6.0",
    "@types/node": "^20.12.0"
  }
}
```

No usar `axios` — el `fetch` nativo de Node 18+ es suficiente y evita bloat.

---

## 12. Convenciones

- Nombres de funciones en español (`crear`, `listar`, `emitir`) — coincide con la API
- Mensajes de consola/UI en español peruano ("Factura creada exitosamente")
- Dates como strings `YYYY-MM-DD` (no Date objects) — la API espera strings
- Montos como `number` (NO Decimal.js salvo cálculos internos complejos)

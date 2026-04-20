# Testing — Estrategia para integraciones con API SUNAT PRO

Tres capas de testing: **unit** (mocks) → **integration** (beta SUNAT) → **contract** (schema validation).

---

## 1. Unit tests — mocks

**Regla**: NUNCA llames a la API real en unit tests. Usa mocks.

### TypeScript (Vitest)

```typescript
import { vi, describe, it, expect } from 'vitest';
import { SunatClient, SunatValidationError } from '../src/lib/sunat/client';

describe('SunatClient.facturas.crear', () => {
  it('retorna factura en éxito', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: true, status: 201,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({
        estado: 'exito',
        mensaje: 'Creado',
        datos: { id: 1, numero_completo: 'F001-000001' },
      }),
    });

    const client = new SunatClient({ baseUrl: 'http://mock', apiKey: 'k', apiSecret: 's' });
    const factura = await client.facturas.crear({/*...*/});
    expect(factura.id).toBe(1);
  });

  it('lanza SunatValidationError en 422', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: false, status: 422,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({
        estado: 'error',
        mensaje: 'Error de validación',
        errores: { serie: ['El campo serie es obligatorio.'] },
      }),
    });

    const client = new SunatClient({ baseUrl: 'http://mock', apiKey: 'k', apiSecret: 's' });
    await expect(client.facturas.crear({} as any)).rejects.toThrow(SunatValidationError);
  });
});
```

### PHP (PHPUnit)

```php
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class SunatClientTest extends TestCase
{
    public function test_crea_factura(): void
    {
        $mock = new MockHandler([
            new Response(201, ['Content-Type' => 'application/json'],
                json_encode(['estado' => 'exito', 'datos' => ['id' => 1]])
            ),
        ]);
        $client = $this->createClientWith($mock);

        $factura = $client->facturas()->crear([/*...*/]);

        $this->assertSame(1, $factura['id']);
    }
}
```

### Python (pytest + respx)

```python
import pytest
import httpx
from sunat.client import SunatClient, SunatValidationError

def test_crea_factura(respx_mock):
    respx_mock.post('http://mock/facturas').mock(
        return_value=httpx.Response(201, json={
            'estado': 'exito',
            'datos': {'id': 1, 'numero_completo': 'F001-000001'},
        })
    )
    client = SunatClient(base_url='http://mock', api_key='k', api_secret='s')
    factura = client.facturas.crear({})
    assert factura['id'] == 1

def test_raises_validation_error(respx_mock):
    respx_mock.post('http://mock/facturas').mock(
        return_value=httpx.Response(422, json={
            'estado': 'error',
            'mensaje': 'Error de validación',
            'errores': {'serie': ['obligatorio']},
        })
    )
    client = SunatClient(...)
    with pytest.raises(SunatValidationError) as exc:
        client.facturas.crear({})
    assert 'serie' in exc.value.errores
```

### Go

```go
func TestCrearFactura(t *testing.T) {
    server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        w.Header().Set("Content-Type", "application/json")
        w.WriteHeader(201)
        w.Write([]byte(`{"estado":"exito","datos":{"id":1}}`))
    }))
    defer server.Close()

    client := sunat.New(sunat.Config{BaseURL: server.URL, APIKey: "k", APISecret: "s"})
    f, err := client.Facturas.Crear(sunat.CrearFacturaInput{/*...*/})
    if err != nil { t.Fatal(err) }
    if f.ID != 1 { t.Errorf("expected 1") }
}
```

---

## 2. Integration tests — beta SUNAT real

Ejecuta contra la API PRO apuntada a **beta de SUNAT** (no producción real). Marca como `@integration` para no correr en CI default.

### Pre-requisitos

```
# .env.test (NO commitear — solo local o secrets de CI)
SUNAT_BASE_URL=https://api.kodevo.es/sunat-api/api/v1
SUNAT_API_KEY=key_de_tenant_de_pruebas
SUNAT_API_SECRET=secret_de_tenant_de_pruebas
```

Crea un tenant de pruebas con `entorno=beta`:
```bash
curl -X POST $BASE_URL/registro \
  -F "ruc=20100000001" \
  -F "razon_social=EMPRESA TEST" \
  -F "direccion=AV TEST" \
  -F "ubigeo=150101" \
  -F "sol_user=MODDATOS" \
  -F "sol_pass=MODDATOS" \
  -F "certificado=@cert-beta.pfx" \
  -F "entorno=beta"
```

### Flujo completo — emisión e2e

```typescript
// tests/integration/emision.test.ts
import { describe, it, expect } from 'vitest';
import { SunatClient } from '../src/lib/sunat/client';

describe.skipIf(!process.env.SUNAT_API_KEY)('Integración real (beta SUNAT)', () => {
  const client = new SunatClient({
    baseUrl: process.env.SUNAT_BASE_URL!,
    apiKey: process.env.SUNAT_API_KEY!,
    apiSecret: process.env.SUNAT_API_SECRET!,
  });

  it('emite factura → polling hasta aceptado', async () => {
    const factura = await client.facturas.crear({
      serie: 'F001',
      fecha_emision: new Date().toISOString().split('T')[0],
      tipo_operacion: '0101',
      cliente: {
        tipo_doc: '6',
        num_doc: '20000000001',
        razon_social: 'CLIENTE TEST',
      },
      items: [{
        codigo: 'P001', descripcion: 'Test', unidad: 'NIU',
        cantidad: 1, precio_unitario: 118, tip_afe_igv: '10',
      }],
    });

    expect(factura.id).toBeGreaterThan(0);
    expect(factura.sunat.estado).toMatch(/pendiente|enviado/);

    // Poll hasta que SUNAT responda (max 30s)
    let final = factura;
    for (let i = 0; i < 10 && !['aceptado', 'rechazado'].includes(final.sunat.estado); i++) {
      await new Promise(r => setTimeout(r, 3000));
      final = await client.facturas.ver(factura.id);
    }

    expect(final.sunat.estado).toBe('aceptado');
    expect(final.sunat.hash_cpe).toBeTruthy();
  }, 60_000);  // timeout 60s
});
```

### Script: test matrix

Ejecuta una batería que cubra los casos principales:

```typescript
const casos = [
  { nombre: 'factura-basica', input: { /*...*/ } },
  { nombre: 'factura-credito-con-cuotas', input: { /*...*/ } },
  { nombre: 'boleta-NRUS', input: { /*...*/ }, tipo: 'boleta' },
  { nombre: 'nota-credito-devolucion', input: { /*...*/ }, tipo: 'nc' },
  // ... etc
];

for (const caso of casos) {
  it(`✓ ${caso.nombre}`, async () => {
    const doc = caso.tipo === 'boleta'
      ? await client.boletas.crear(caso.input)
      : await client.facturas.crear(caso.input);
    expect(doc.id).toBeDefined();
  });
}
```

---

## 3. Contract tests — validar schema

Genera fixtures reales de la API y assert que tu parser los maneja.

### Ejemplo (TypeScript + Zod)

```typescript
import { z } from 'zod';
import realFixture from './fixtures/factura-aceptada.json';

const FacturaSchema = z.object({
  id: z.number(),
  serie: z.string(),
  numero_completo: z.string(),
  sunat: z.object({
    estado: z.enum(['pendiente', 'enviado', 'aceptado', 'rechazado']),
    codigo: z.string().nullable(),
    hash_cpe: z.string().nullable(),
  }),
  totales: z.object({
    total: z.number(),
    igv: z.number(),
  }),
});

it('parsea factura real', () => {
  expect(() => FacturaSchema.parse(realFixture)).not.toThrow();
});
```

Guarda fixtures reales después de una emisión:
```bash
curl $BASE_URL/facturas/1 -H "X-Api-Key: ..." -H "X-Api-Secret: ..." | jq .datos > tests/fixtures/factura-aceptada.json
```

---

## 4. Mock del webhook handler

```typescript
import request from 'supertest';
import { app } from '../src/app';

describe('POST /sunat-webhook', () => {
  it('acepta evento document.sent y actualiza BD', async () => {
    await request(app)
      .post('/sunat-webhook')
      .send({
        event: 'document.sent',
        tenant_id: 1,
        model: 'Invoice',
        id: 123,
        data: {
          numero: 'F001-000123',
          sunat_status: 'aceptado',
          sunat_code: '0',
          hash_cpe: 'abc',
        },
      })
      .expect(200);

    // Verificar actualización BD
    const factura = await db.invoice.findUnique({ where: { externalId: 123 } });
    expect(factura?.estado).toBe('aceptado');
  });

  it('es idempotente — no duplica en webhook repetido', async () => {
    // ... primera llamada
    // ... segunda llamada idéntica
    // verificar que BD tiene solo una actualización
  });
});
```

---

## 5. CI/CD — separación de jobs

```yaml
# .github/workflows/test.yml
jobs:
  unit:
    runs-on: ubuntu-latest
    steps:
      - run: npm run test:unit    # rápido, sin network

  integration:
    if: github.ref == 'refs/heads/main'  # solo en main
    runs-on: ubuntu-latest
    env:
      SUNAT_BASE_URL: ${{ secrets.SUNAT_BASE_URL }}
      SUNAT_API_KEY: ${{ secrets.SUNAT_API_KEY }}
      SUNAT_API_SECRET: ${{ secrets.SUNAT_API_SECRET }}
    steps:
      - run: npm run test:integration
```

---

## 6. Fixtures de SUNAT

Ten a mano RUCs y DNIs que funcionen en beta:

```json
// tests/fixtures/clientes-validos.json
{
  "empresas": [
    { "tipo_doc": "6", "num_doc": "20000000001", "razon_social": "CLIENTE DEMO SAC" },
    { "tipo_doc": "6", "num_doc": "20512345678", "razon_social": "OTRA EMPRESA SAC" },
    { "tipo_doc": "6", "num_doc": "20123456789", "razon_social": "EMPRESA PRUEBA" }
  ],
  "personas": [
    { "tipo_doc": "1", "num_doc": "12345678", "razon_social": "JUAN PEREZ" },
    { "tipo_doc": "1", "num_doc": "87654321", "razon_social": "MARIA LOPEZ" }
  ]
}
```

---

## 7. Cheat sheet

| Test type | Cuándo | Qué verifica |
|---|---|---|
| Unit | Cada push | Tu código parsea respuestas correctamente |
| Contract | Semanal | El schema de la API no cambió |
| Integration | Pre-release | E2E real con beta SUNAT funciona |
| Smoke | Post-deploy | Producción responde |

**Nunca**:
- ❌ Unit tests que hacen red real
- ❌ Integration tests en CI sin secrets
- ❌ Ignorar 100% del error handling (si catch está vacío, falla el test)

**Siempre**:
- ✅ Mocks del error 422 con estructura real
- ✅ Integration test de al menos 1 happy path por comprobante
- ✅ Test del webhook handler con payload real
- ✅ Test de idempotencia (mismos datos dos veces → NO duplica)

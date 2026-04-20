# PHP — Integración

Stack: **PHP 8.2+**, Laravel, Symfony, vanilla PHP, WordPress.

---

## 1. Cliente HTTP base (Laravel / Symfony / vanilla compatible)

`src/Sunat/SunatClient.php`:

```php
<?php

declare(strict_types=1);

namespace App\Sunat;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Sunat\Exceptions\{SunatApiException, SunatValidationException, SunatLimitException};

class SunatClient
{
    private Client $http;

    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $apiSecret,
    ) {
        $this->http = new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'http_errors' => false,
            'timeout' => 30,
        ]);
    }

    /**
     * @return array|string  Array decodificado para JSON, string para descargas binarias
     */
    public function request(string $method, string $path, ?array $body = null): array|string
    {
        $options = [
            'headers' => [
                'Accept' => 'application/json',
                'X-Api-Key' => $this->apiKey,
                'X-Api-Secret' => $this->apiSecret,
            ],
        ];

        if ($body !== null) {
            $options['headers']['Content-Type'] = 'application/json';
            $options['json'] = $body;
        }

        $response = $this->http->request($method, ltrim($path, '/'), $options);
        $status = $response->getStatusCode();
        $contentType = $response->getHeaderLine('Content-Type');
        $rawBody = (string) $response->getBody();

        // Descargas binarias (XML/PDF/CDR)
        if (! str_contains($contentType, 'application/json')) {
            if ($status >= 400) {
                throw new SunatApiException("Error HTTP $status", $status);
            }
            return $rawBody;
        }

        $data = json_decode($rawBody, true) ?? [];

        if ($status < 400 && ($data['estado'] ?? null) === 'exito') {
            return $data['datos'] ?? [];
        }

        // Mapeo de errores
        $mensaje = $data['mensaje'] ?? "Error HTTP $status";

        if ($status === 422) {
            throw new SunatValidationException($mensaje, $data['errores'] ?? []);
        }
        if ($status === 429) {
            throw new SunatLimitException($mensaje, $data['mejora_plan'] ?? null);
        }
        throw new SunatApiException($mensaje, $status, $data['codigo_error'] ?? null);
    }

    // Shortcuts
    public function get(string $path): array|string
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $body): array
    {
        return (array) $this->request('POST', $path, $body);
    }

    public function put(string $path, array $body): array
    {
        return (array) $this->request('PUT', $path, $body);
    }

    public function delete(string $path): array
    {
        return (array) $this->request('DELETE', $path);
    }

    // Namespaces
    public function facturas(): Resources\FacturasResource
    {
        return new Resources\FacturasResource($this);
    }

    public function boletas(): Resources\BoletasResource
    {
        return new Resources\BoletasResource($this);
    }

    public function clientes(): Resources\ClientesResource
    {
        return new Resources\ClientesResource($this);
    }

    public function empresa(): Resources\EmpresaResource
    {
        return new Resources\EmpresaResource($this);
    }
}
```

---

## 2. Exceptions

`src/Sunat/Exceptions/SunatApiException.php`:

```php
<?php

namespace App\Sunat\Exceptions;

class SunatApiException extends \Exception
{
    public function __construct(
        string $message,
        public readonly int $status = 500,
        public readonly ?string $codigoError = null,
    ) {
        parent::__construct($message, $status);
    }
}

class SunatValidationException extends SunatApiException
{
    public function __construct(
        string $message,
        public readonly array $errores,
    ) {
        parent::__construct($message, 422);
    }
}

class SunatLimitException extends SunatApiException
{
    public function __construct(
        string $message,
        public readonly ?array $mejoraPlan,
    ) {
        parent::__construct($message, 429, 'limite_alcanzado');
    }
}
```

---

## 3. Resource (ejemplo Facturas)

`src/Sunat/Resources/FacturasResource.php`:

```php
<?php

namespace App\Sunat\Resources;

use App\Sunat\SunatClient;

class FacturasResource
{
    public function __construct(private SunatClient $client) {}

    public function crear(array $data): array
    {
        return $this->client->post('facturas', $data);
    }

    public function ver(int $id): array
    {
        return (array) $this->client->get("facturas/$id");
    }

    public function listar(array $params = []): array
    {
        $qs = http_build_query($params);
        return (array) $this->client->get('facturas' . ($qs ? "?$qs" : ''));
    }

    public function actualizar(int $id, array $data): array
    {
        return $this->client->put("facturas/$id", $data);
    }

    public function enviar(int $id): array
    {
        return $this->client->post("facturas/$id/enviar", []);
    }

    public function pdf(int $id, string $formato = 'a4'): string
    {
        return (string) $this->client->request('GET', "facturas/$id/pdf?format=$formato");
    }

    public function xml(int $id): string
    {
        return (string) $this->client->request('GET', "facturas/$id/xml");
    }

    public function cdr(int $id): string
    {
        return (string) $this->client->request('GET', "facturas/$id/cdr");
    }
}
```

---

## 4. Uso en Laravel

`config/sunat.php`:

```php
<?php

return [
    'base_url' => env('SUNAT_BASE_URL', 'https://api.kodevo.es/sunat-api/api/v1'),
    'api_key' => env('SUNAT_API_KEY'),
    'api_secret' => env('SUNAT_API_SECRET'),
];
```

`app/Providers/AppServiceProvider.php`:

```php
use App\Sunat\SunatClient;

public function register(): void
{
    $this->app->singleton(SunatClient::class, fn () =>
        new SunatClient(
            config('sunat.base_url'),
            config('sunat.api_key'),
            config('sunat.api_secret'),
        )
    );
}
```

`app/Http/Controllers/FacturacionController.php`:

```php
use App\Sunat\SunatClient;
use App\Sunat\Exceptions\SunatValidationException;

public function emitirFactura(Request $request, SunatClient $sunat)
{
    try {
        $factura = $sunat->facturas()->crear([
            'serie' => 'F001',
            'fecha_emision' => now()->toDateString(),
            'tipo_moneda' => 'PEN',
            'forma_pago' => 'Contado',
            'cliente' => $request->input('cliente'),
            'items' => $request->input('items'),
        ]);

        return response()->json(['ok' => true, 'factura' => $factura], 201);

    } catch (SunatValidationException $e) {
        return response()->json([
            'ok' => false,
            'errores' => $e->errores,
        ], 422);

    } catch (SunatApiException $e) {
        Log::error('SUNAT error', [
            'status' => $e->status,
            'mensaje' => $e->getMessage(),
        ]);
        return response()->json(['ok' => false, 'mensaje' => 'Error al emitir'], 500);
    }
}
```

---

## 5. Uso en Symfony

`config/services.yaml`:

```yaml
services:
    App\Sunat\SunatClient:
        arguments:
            $baseUrl: '%env(SUNAT_BASE_URL)%'
            $apiKey: '%env(SUNAT_API_KEY)%'
            $apiSecret: '%env(SUNAT_API_SECRET)%'
```

Inyectar en controller:

```php
#[AsController]
class FacturaController
{
    public function __construct(private SunatClient $sunat) {}

    #[Route('/api/facturas', methods: ['POST'])]
    public function emitir(Request $request): JsonResponse
    {
        $factura = $this->sunat->facturas()->crear(json_decode($request->getContent(), true));
        return new JsonResponse(['factura' => $factura], 201);
    }
}
```

---

## 6. Webhook handler (Laravel)

`routes/api.php`:

```php
Route::post('/sunat/webhook', [SunatWebhookController::class, 'handle']);
```

`app/Http/Controllers/SunatWebhookController.php`:

```php
public function handle(Request $request)
{
    $event = $request->input('event');
    $modelo = $request->input('model');
    $data = $request->input('data');

    match ($event) {
        'document.sent' => $this->onSent($modelo, $data),
        'document.rejected' => $this->onRejected($modelo, $data),
        default => null,
    };

    return response()->json(['ok' => true]);
}

private function onSent(string $modelo, array $data): void
{
    if ($data['sunat_status'] === 'aceptado') {
        // Actualizar BD local
        MiFactura::where('numero', $data['numero'])->update([
            'estado' => 'aceptado',
            'hash_cpe' => $data['hash_cpe'],
        ]);

        // Enviar email
        Mail::to($cliente)->send(new FacturaEmitida($data));
    }
}
```

---

## 7. Testing (PHPUnit)

```php
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

class SunatClientTest extends TestCase
{
    public function test_crea_factura(): void
    {
        $mock = new MockHandler([
            new Response(201, ['Content-Type' => 'application/json'], json_encode([
                'estado' => 'exito',
                'mensaje' => 'Creado',
                'datos' => ['id' => 1, 'numero_completo' => 'F001-000001'],
            ])),
        ]);

        $client = new SunatClient('http://mock', 'key', 'secret');
        // inject mock handler here...

        $factura = $client->facturas()->crear([
            'serie' => 'F001',
            'fecha_emision' => '2026-04-19',
            'cliente' => ['tipo_doc' => '6', 'num_doc' => '20000000001', 'razon_social' => 'TEST'],
            'items' => [['codigo' => 'P', 'descripcion' => 'X', 'unidad' => 'NIU',
                'cantidad' => 1, 'precio_unitario' => 100, 'tip_afe_igv' => '10']],
        ]);

        $this->assertEquals(1, $factura['id']);
    }

    public function test_lanza_validation_exception_en_422(): void
    {
        $mock = new MockHandler([
            new Response(422, ['Content-Type' => 'application/json'], json_encode([
                'estado' => 'error',
                'mensaje' => 'Error de validación',
                'errores' => ['serie' => ['El campo serie es obligatorio.']],
            ])),
        ]);

        $this->expectException(SunatValidationException::class);
        // ...
    }
}
```

---

## 8. Estructura de carpetas Laravel

```
app/
├── Sunat/
│   ├── SunatClient.php
│   ├── Resources/
│   │   ├── FacturasResource.php
│   │   ├── BoletasResource.php
│   │   ├── ClientesResource.php
│   │   └── ...
│   └── Exceptions/
│       ├── SunatApiException.php
│       ├── SunatValidationException.php
│       └── SunatLimitException.php
├── Services/
│   └── FacturacionService.php     # lógica dominio
└── Http/
    └── Controllers/
        ├── FacturacionController.php
        └── SunatWebhookController.php
```

---

## 9. `.env`

```
SUNAT_BASE_URL=https://api.kodevo.es/sunat-api/api/v1
SUNAT_API_KEY=xxx
SUNAT_API_SECRET=yyy
SUNAT_WEBHOOK_SECRET=zzz
```

---

## 10. Dependencias

```bash
composer require guzzlehttp/guzzle
```

PHP 8.2+ requerido (readonly properties, enums, etc.).

---

## 11. Integración como package composer (opcional)

Si vas a reusar en múltiples proyectos del usuario, publícalo como package:

```
mi-empresa/sunat-client/
├── composer.json
├── src/
│   └── SunatClient.php
└── tests/
```

`composer.json`:
```json
{
  "name": "mi-empresa/sunat-client",
  "autoload": { "psr-4": { "MiEmpresa\\Sunat\\": "src/" } },
  "require": {
    "php": "^8.2",
    "guzzlehttp/guzzle": "^7.0"
  }
}
```

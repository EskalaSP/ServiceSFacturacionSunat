<?php

namespace App\Sire\Services\Http;

use App\Models\Tenant;
use App\Sire\Exceptions\SireAuthException;
use App\Sire\Exceptions\SireErrorCatalog;
use App\Sire\Exceptions\SireException;
use App\Sire\Exceptions\SireValidationException;
use App\Sire\Services\Auth\SireAuthService;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

/**
 * Cliente HTTP específico para los servicios SIRE.
 * - Inyecta el Bearer token automáticamente (delegando a SireAuthService).
 * - Si SUNAT responde 401, invalida el token cacheado y reintenta UNA vez.
 * - Mapea respuestas 422 al catálogo de errores.
 * - Los endpoints binarios (descarga de ZIP 5.32) se manejan con `getRaw()`.
 */
class SireHttpClient
{
    public function __construct(
        private readonly SireAuthService $auth,
        private readonly HttpClient $http = new HttpClient(),
    ) {}

    /**
     * GET que devuelve JSON decodificado.
     */
    public function get(Tenant $tenant, string $path, array $query = []): array
    {
        return $this->request($tenant, 'GET', $path, ['query' => $query]);
    }

    /**
     * POST que devuelve JSON decodificado.
     */
    public function post(Tenant $tenant, string $path, array $options = []): array
    {
        return $this->request($tenant, 'POST', $path, $options);
    }

    /**
     * GET binario — para descargar ZIPs (5.32) sin intentar decodificar como JSON.
     */
    public function getRaw(Tenant $tenant, string $path, array $query = []): ResponseInterface
    {
        return $this->rawRequest($tenant, 'GET', $path, ['query' => $query]);
    }

    private function request(Tenant $tenant, string $method, string $path, array $options): array
    {
        $response = $this->rawRequest($tenant, $method, $path, $options);
        $body = (string) $response->getBody();

        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new SireException(
                'Respuesta SUNAT no es JSON válido: ' . json_last_error_msg(),
                httpStatus: 502,
                context: ['body_preview' => substr($body, 0, 500)],
            );
        }

        return $decoded ?? [];
    }

    private function rawRequest(
        Tenant $tenant,
        string $method,
        string $path,
        array $options,
        bool $retryOn401 = true,
    ): ResponseInterface {
        $url = $this->buildUrl($path);
        $token = $this->auth->getToken($tenant);

        $options = array_merge_recursive([
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'API-PRO-SIRE/1.0',
            ],
            'timeout'         => 30,
            'connect_timeout' => 10,
            'http_errors'     => true,
        ], $options);

        try {
            return $this->http->request($method, $url, $options);
        } catch (ClientException $e) {
            $status = $e->getCode();
            $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';

            // 401 → token expirado o rechazado. Invalida cache y reintenta una vez.
            if ($status === 401 && $retryOn401) {
                $this->auth->invalidate($tenant);
                return $this->rawRequest($tenant, $method, $path, $options, retryOn401: false);
            }

            // 422 → error de validación SUNAT con código 1001-2278
            if ($status === 422) {
                $this->throwValidationException($body, $tenant, $path);
            }

            Log::channel('stack')->warning('[SIRE] ClientException', [
                'tenant_id' => $tenant->id,
                'method'    => $method,
                'url'       => $url,
                'status'    => $status,
                'body'      => substr($body, 0, 500),
            ]);

            if ($status === 401) {
                throw SireAuthException::credencialesInvalidas();
            }

            throw new SireException(
                "SUNAT SIRE respondió {$status}: {$body}",
                httpStatus: $status ?: 400,
                previous: $e,
            );
        } catch (ServerException $e) {
            Log::channel('stack')->error('[SIRE] ServerException', [
                'tenant_id' => $tenant->id,
                'url'       => $url,
                'message'   => $e->getMessage(),
            ]);

            throw new SireException(
                'SUNAT SIRE tuvo un error interno. Reintenta en unos minutos.',
                httpStatus: 502,
                previous: $e,
            );
        } catch (GuzzleException $e) {
            throw new SireException(
                "Error de red consultando SIRE: {$e->getMessage()}",
                httpStatus: 502,
                previous: $e,
            );
        }
    }

    private function buildUrl(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return rtrim(config('sire.hosts.api'), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Parsea respuesta 422 de SUNAT y lanza SireValidationException con mensaje legible.
     */
    private function throwValidationException(string $body, Tenant $tenant, string $path): never
    {
        $decoded = json_decode($body, true) ?? [];
        $errors  = SireErrorCatalog::parseSunatErrorResponse($decoded);

        if (empty($errors)) {
            throw new SireValidationException(
                code: $decoded['cod'] ?? '422',
                sunatMessage: $decoded['msg'] ?? 'Error de validación SUNAT sin detalles',
                context: ['tenant_id' => $tenant->id, 'path' => $path],
            );
        }

        $first = $errors[0];
        throw new SireValidationException(
            code: $first['cod'],
            sunatMessage: $first['msg'],
            context: [
                'tenant_id' => $tenant->id,
                'path'      => $path,
                'all_errors' => $errors,
            ],
        );
    }
}

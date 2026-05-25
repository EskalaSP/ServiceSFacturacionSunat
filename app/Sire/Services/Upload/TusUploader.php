<?php

namespace App\Sire\Services\Upload;

use App\Models\Tenant;
use App\Sire\Exceptions\SireErrorCatalog;
use App\Sire\Exceptions\SireException;
use App\Sire\Exceptions\SireValidationException;
use App\Sire\Services\Auth\SireAuthService;
use App\Sire\Support\Base64MetadataEncoder;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Cliente TUS.io mínimo para subir archivos a los servicios SIRE de carga
 * (5.3, 5.5, 5.6, 5.7, 5.8, 5.9, 5.10, etc.).
 *
 * Protocolo TUS 1.0.0 (ver https://tus.io/protocols/resumable-upload.html):
 *   1. POST   {endpoint}  con Upload-Length + Upload-Metadata → 201 + Location
 *   2. PATCH  {location}  con Upload-Offset: 0 + bytes        → 204
 *
 * SUNAT, además del flujo estándar, devuelve un `numTicket` ya sea en el
 * cuerpo del POST o del PATCH. Esta clase lo extrae de ambos lugares.
 *
 * NOTA: La versión v22 del manual advierte que las librerías genéricas TUS
 * tienen problemas mapeando errores 422 de SUNAT. Aquí mapeamos explícitamente.
 */
class TusUploader
{
    public function __construct(
        private readonly SireAuthService $auth,
        private readonly HttpClient $http = new HttpClient(),
    ) {}

    /**
     * Sube un archivo y devuelve la respuesta decodificada con `numTicket`.
     *
     * @param Tenant $tenant
     * @param string $url            URL completa del endpoint (/rvierce/.../upload)
     * @param string $filePath       Ruta absoluta al ZIP local
     * @param array  $metadata       Metadata para Upload-Metadata (clave => valor sin-base64)
     *
     * @return array{numTicket?: string, ...}
     */
    public function upload(Tenant $tenant, string $url, string $filePath, array $metadata): array
    {
        if (! is_readable($filePath)) {
            throw new \RuntimeException("Archivo no legible: {$filePath}");
        }

        $fileSize = filesize($filePath);
        $token    = $this->auth->getToken($tenant);

        $metadataHeader = Base64MetadataEncoder::encode($metadata);

        // Paso 1: crear recurso TUS
        $createResponse = $this->safeRequest(
            $tenant,
            fn () => $this->http->request('POST', $url, [
                'headers' => [
                    'Tus-Resumable'    => '1.0.0',
                    'Upload-Length'    => (string) $fileSize,
                    'Upload-Metadata'  => $metadataHeader,
                    'Authorization'    => "Bearer {$token}",
                    'Content-Length'   => '0',
                    'User-Agent'       => 'API-PRO-SIRE-TUS/1.0',
                ],
                'timeout'         => 60,
                'connect_timeout' => 15,
                'http_errors'     => true,
            ]),
            context: ['phase' => 'create', 'url' => $url],
        );

        // Algunos endpoints devuelven el numTicket directamente en el POST
        $ticketFromCreate = $this->extractTicket((string) $createResponse->getBody());
        if ($ticketFromCreate !== null) {
            return ['numTicket' => $ticketFromCreate];
        }

        // El encabezado Location apunta al recurso recién creado
        $location = $createResponse->getHeaderLine('Location');
        if (empty($location)) {
            throw new SireException(
                'TUS: SUNAT no devolvió Location tras la creación del recurso.',
                httpStatus: 502,
            );
        }

        // Si Location es relativo, lo absolutizamos
        if (! str_starts_with($location, 'http')) {
            $location = $this->absolutizeLocation($url, $location);
        }

        // Paso 2: subir los bytes
        $patchResponse = $this->safeRequest(
            $tenant,
            fn () => $this->http->request('PATCH', $location, [
                'headers' => [
                    'Tus-Resumable'   => '1.0.0',
                    'Upload-Offset'   => '0',
                    'Content-Type'    => 'application/offset+octet-stream',
                    'Content-Length'  => (string) $fileSize,
                    'Authorization'   => "Bearer {$token}",
                    'User-Agent'      => 'API-PRO-SIRE-TUS/1.0',
                ],
                'body'            => fopen($filePath, 'rb'),
                'timeout'         => 300, // archivos grandes
                'connect_timeout' => 15,
                'http_errors'     => true,
            ]),
            context: ['phase' => 'patch', 'url' => $location],
        );

        $ticket = $this->extractTicket((string) $patchResponse->getBody());

        if ($ticket === null) {
            throw new SireException(
                'TUS: SUNAT completó el upload pero no devolvió numTicket.',
                httpStatus: 502,
                context: [
                    'patch_status' => $patchResponse->getStatusCode(),
                    'patch_body_preview' => substr((string) $patchResponse->getBody(), 0, 500),
                ],
            );
        }

        Log::channel('stack')->info('[SIRE][TUS] upload OK', [
            'tenant_id' => $tenant->id,
            'url'       => $url,
            'size'      => $fileSize,
            'ticket'    => $ticket,
        ]);

        return ['numTicket' => $ticket];
    }

    /**
     * Ejecuta una petición TUS y traduce errores.
     */
    private function safeRequest(Tenant $tenant, \Closure $fn, array $context): \Psr\Http\Message\ResponseInterface
    {
        try {
            return $fn();
        } catch (ClientException $e) {
            $status = $e->getCode();
            $body   = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';

            Log::channel('stack')->warning('[SIRE][TUS] ClientException', array_merge(
                ['tenant_id' => $tenant->id, 'status' => $status, 'body_preview' => substr($body, 0, 500)],
                $context,
            ));

            if ($status === 422) {
                $decoded = json_decode($body, true) ?? [];
                $errors  = SireErrorCatalog::parseSunatErrorResponse($decoded);
                $first   = $errors[0] ?? ['cod' => '422', 'msg' => $decoded['msg'] ?? 'Error'];

                throw new SireValidationException(
                    code: $first['cod'],
                    sunatMessage: $first['msg'],
                    context: array_merge($context, ['all_errors' => $errors]),
                );
            }

            throw new SireException(
                "TUS: SUNAT respondió {$status}: {$body}",
                httpStatus: $status ?: 400,
                context: $context,
                previous: $e,
            );
        } catch (GuzzleException $e) {
            throw new SireException(
                "TUS: Error de red: {$e->getMessage()}",
                httpStatus: 502,
                context: $context,
                previous: $e,
            );
        }
    }

    /**
     * Busca `numTicket` en el cuerpo JSON de una respuesta SUNAT.
     */
    private function extractTicket(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded['numTicket']
            ?? $decoded['num_ticket']
            ?? $decoded['data']['numTicket']
            ?? null;
    }

    private function absolutizeLocation(string $baseUrl, string $location): string
    {
        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $origin = "{$scheme}://{$host}{$port}";

        return str_starts_with($location, '/')
            ? $origin . $location
            : $origin . '/' . $location;
    }
}

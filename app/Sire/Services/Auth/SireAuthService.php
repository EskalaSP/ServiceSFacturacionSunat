<?php

namespace App\Sire\Services\Auth;

use App\Models\Tenant;
use App\Sire\Exceptions\SireAuthException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Obtiene y cachea el token Bearer para consumir la API SIRE de SUNAT.
 *
 * Flujo OAuth (manual v22, sección 5.1):
 *   POST https://api-seguridad.sunat.gob.pe/v1/clientessol/{client_id}/oauth2/token/
 *   Content-Type: application/x-www-form-urlencoded
 *
 *   grant_type=password
 *   scope=https://api-sire.sunat.gob.pe
 *   client_id={client_id}
 *   client_secret={client_secret}
 *   username={RUC} {SOL_USER}
 *   password={SOL_PASS}
 *
 * Respuesta: { access_token, token_type, expires_in }
 *
 * NOTA: es DIFERENTE al flujo de Consulta CPE:
 *   - CPE usa `/clientesextranet/...` y grant_type=client_credentials
 *   - SIRE usa `/clientessol/...` y grant_type=password (necesita SOL_USER/SOL_PASS)
 */
class SireAuthService
{
    public function __construct(
        private readonly HttpClient $http = new HttpClient(),
    ) {}

    /**
     * Devuelve un token Bearer válido para el tenant.
     * Usa cache aislado por tenant — imposible cruzar tokens entre empresas.
     */
    public function getToken(Tenant $tenant): string
    {
        $cacheKey = config('sire.token.cache_prefix') . $tenant->id;

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $response = $this->requestToken($tenant);

        $ttl = max(
            60,
            ((int) $response['expires_in']) - config('sire.token.safety_margin_seconds'),
        );

        Cache::put($cacheKey, $response['access_token'], $ttl);

        return $response['access_token'];
    }

    /**
     * Fuerza invalidación del token cacheado (útil cuando SUNAT devuelve 401
     * o cuando el tenant rota sus credenciales).
     */
    public function invalidate(Tenant $tenant): void
    {
        Cache::forget(config('sire.token.cache_prefix') . $tenant->id);
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int}
     */
    private function requestToken(Tenant $tenant): array
    {
        $this->validateTenantCredentials($tenant);

        [$clientId, $clientSecret] = [
            $tenant->sireCredentials()['client_id'],
            $tenant->sireCredentials()['client_secret'],
        ];

        $url = sprintf(
            '%s/clientessol/%s/oauth2/token/',
            rtrim(config('sire.hosts.auth'), '/'),
            $clientId,
        );

        try {
            $response = $this->http->post($url, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept'       => 'application/json',
                ],
                'form_params' => [
                    'grant_type'    => config('sire.token.grant_type'),
                    'scope'         => config('sire.scope'),
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'username'      => sprintf('%s %s', $tenant->ruc, $tenant->sol_user),
                    'password'      => $tenant->sol_pass,
                ],
                'timeout' => 15,
            ]);

            $data = json_decode((string) $response->getBody(), true);

            if (! isset($data['access_token']) || ! isset($data['expires_in'])) {
                throw new SireAuthException('Respuesta OAuth inesperada de SUNAT.', httpStatus: 502);
            }

            return $data;
        } catch (ClientException $e) {
            $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
            Log::channel('stack')->warning('[SIRE] Auth rechazado', [
                'tenant_id' => $tenant->id,
                'ruc'       => $tenant->ruc,
                'status'    => $e->getCode(),
                'body'      => $body,
            ]);

            if (str_contains($body, 'unauthorized_client') || str_contains($body, 'invalid_client')) {
                throw SireAuthException::credencialesInvalidas();
            }

            throw new SireAuthException(
                "SUNAT rechazó la autenticación SIRE: {$body}",
                httpStatus: $e->getCode() ?: 401,
                previous: $e,
            );
        } catch (GuzzleException $e) {
            throw new SireAuthException(
                "Error de red al autenticar contra SUNAT SIRE: {$e->getMessage()}",
                httpStatus: 502,
                previous: $e,
            );
        }
    }

    private function validateTenantCredentials(Tenant $tenant): void
    {
        $creds = $tenant->sireCredentials();

        $missing = [];
        if (empty($creds['client_id']))     $missing[] = 'client_id';
        if (empty($creds['client_secret'])) $missing[] = 'client_secret';
        if (empty($tenant->sol_user))       $missing[] = 'sol_user';
        if (empty($tenant->sol_pass))       $missing[] = 'sol_pass';
        if (empty($tenant->ruc))            $missing[] = 'ruc';

        if (! empty($missing)) {
            throw new SireAuthException(
                'Faltan credenciales SIRE en el tenant: ' . implode(', ', $missing),
                httpStatus: 422,
            );
        }
    }
}

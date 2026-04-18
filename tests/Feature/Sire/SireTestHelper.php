<?php

namespace Tests\Feature\Sire;

use App\Models\Tenant;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Helper compartido para tests de SIRE.
 *
 * Provee:
 *  - Creación de tenant de prueba con credenciales SIRE
 *  - Swap del Guzzle Client por un MockHandler para interceptar SUNAT
 *  - Métodos utilitarios para simular respuestas SUNAT
 */
trait SireTestHelper
{
    protected MockHandler $sunatMock;
    protected Tenant $tenant;

    protected function setUpSireTenant(bool $sireEnabled = true): Tenant
    {
        $user = User::factory()->create();

        $this->tenant = Tenant::create([
            'user_id'       => $user->id,
            'ruc'           => '20100000001',
            'razon_social'  => 'EMPRESA TEST SAC',
            'direccion'     => 'AV. TEST 123',
            'ubigeo'        => '150101',
            'sol_user'      => 'MODDATOS',
            'sol_pass'      => 'MODDATOS',
            'client_id'     => 'test-client-id-sire',
            'client_secret' => 'test-client-secret-sire',
            'environment'   => 'beta',
            'plan'          => 'business',
            'api_key'       => 'test_api_key_' . uniqid(),
            'api_secret'    => 'test_api_secret_' . uniqid(),
            'is_active'     => true,
            'sire_enabled'  => $sireEnabled,
        ]);

        return $this->tenant;
    }

    protected function setUpSunatMock(): void
    {
        // Limpia cache del token SIRE entre tests — evita que un token cacheado
        // de un test previo haga saltar la llamada mockeada a auth
        \Illuminate\Support\Facades\Cache::flush();

        $this->sunatMock = new MockHandler();
        $handlerStack   = HandlerStack::create($this->sunatMock);
        $mockedClient   = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockedClient);

        // Limpia storage temporal de ZIPs entre tests (Windows: ZipArchive::rename falla si hay residuos)
        $tempRoot = storage_path('app/sire-temp');
        if (is_dir($tempRoot)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tempRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($it as $file) {
                $file->isDir() ? @rmdir($file->getRealPath()) : @unlink($file->getRealPath());
            }
        }
    }

    protected function headers(): array
    {
        return [
            'X-Api-Key'    => $this->tenant->api_key,
            'X-Api-Secret' => $this->tenant->api_secret,
            'Accept'       => 'application/json',
        ];
    }

    /**
     * Prepara la respuesta del endpoint OAuth token (5.1) con éxito.
     */
    protected function mockAuthSuccess(string $accessToken = 'fake_token', int $expiresIn = 3600): void
    {
        $this->sunatMock->append(new Response(200, [], json_encode([
            'access_token' => $accessToken,
            'token_type'   => 'Bearer',
            'expires_in'   => $expiresIn,
        ])));
    }

    /**
     * Prepara un fallo OAuth (credenciales inválidas).
     */
    protected function mockAuthFailure(): void
    {
        $this->sunatMock->append(new Response(400, [], json_encode([
            'error'             => 'unauthorized_client',
            'error_description' => 'cliente no autorizado',
        ])));
    }

    protected function mockResponse(int $status, array|string $body, array $headers = []): void
    {
        if (is_array($body)) {
            $body = json_encode($body);
            $headers = array_merge(['Content-Type' => 'application/json'], $headers);
        }

        $this->sunatMock->append(new Response($status, $headers, $body));
    }

    protected function mockTicketResponse(string $numTicket = '2026040012345678'): void
    {
        $this->mockResponse(200, ['numTicket' => $numTicket]);
    }
}

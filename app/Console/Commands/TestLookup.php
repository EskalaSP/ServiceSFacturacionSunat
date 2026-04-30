<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestLookup extends Command
{
    protected $signature = 'lookup:test {numero} {--tipo=6 : 6=RUC, 1=DNI}';
    protected $description = 'Prueba el lookup de RUC/DNI contra apis.net.pe';

    public function handle(): int
    {
        $numero = $this->argument('numero');
        $tipo   = $this->option('tipo');

        $token   = config('facturacion.lookup.token');
        $baseUrl = config('facturacion.lookup.base_url');

        $this->line("Token configurado : " . ($token ? substr($token, 0, 10) . '...' : '(vacío)'));
        $this->line("Base URL          : {$baseUrl}");

        if (empty($token)) {
            $this->error('APIS_NET_PE_TOKEN está vacío. Verifica las variables de Railway.');
            return 1;
        }

        $endpoint = $tipo === '6'
            ? "{$baseUrl}/v2/sunat/ruc"
            : "{$baseUrl}/v2/reniec/dni";

        $this->line("Endpoint          : GET {$endpoint}?numero={$numero}");
        $this->line('');

        try {
            $response = Http::timeout(10)
                ->withToken($token)
                ->acceptJson()
                ->get($endpoint, ['numero' => $numero]);

            $this->line("HTTP Status: {$response->status()}");
            $this->line("Body:");
            $this->line(json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            $this->error("Excepción: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}

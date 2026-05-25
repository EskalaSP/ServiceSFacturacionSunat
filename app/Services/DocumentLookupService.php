<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class DocumentLookupService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('facturacion.lookup.base_url'), '/');
        $this->token   = config('facturacion.lookup.token');
    }

    public function lookup(string $tipo, string $numero): ?array
    {
        $cacheKey = "doc_lookup:{$tipo}:{$numero}";

        // Solo cachear resultados exitosos; nunca cachear null para que los reintentos funcionen
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $result = $tipo === '6'
            ? $this->lookupRuc($numero)
            : $this->lookupDni($numero);

        if ($result !== null) {
            Cache::put($cacheKey, $result, now()->addHours(24));
        }

        return $result;
    }

    private function lookupRuc(string $ruc): ?array
    {
        try {
            // apis.net.pe v1: GET /v1/ruc?numero={ruc}  Autorización: Bearer {token}
            $response = Http::timeout(8)
                ->withToken($this->token)
                ->acceptJson()
                ->get("{$this->baseUrl}/v1/ruc", ['numero' => $ruc]);

            if ($response->successful()) {
                $data = $response->json();
                $data = $data['data'] ?? $data;

                $razonSocial = $data['razonSocial']
                    ?? $data['nombre_o_razon_social']
                    ?? $data['nombre']   // campo en respuesta v1
                    ?? null;

                if (!empty($razonSocial)) {
                    return [
                        'tipo_doc'     => '6',
                        'num_doc'      => $ruc,
                        'razon_social' => $razonSocial,
                        'direccion'    => $data['direccion']  ?? $data['direccion_completa'] ?? '',
                        'estado'       => $data['estado']     ?? '',
                        'condicion'    => $data['condicion']  ?? '',
                        'source'       => 'sunat',
                    ];
                }

                \Log::error("RUC {$ruc}: respuesta OK pero sin razonSocial", ['body' => $data]);
            } else {
                \Log::error("RUC {$ruc}: HTTP {$response->status()} desde apis.net.pe", ['body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            \Log::error("RUC lookup excepción para {$ruc}: " . $e->getMessage());
        }

        return null;
    }

    private function lookupDni(string $dni): ?array
    {
        try {
            // apis.net.pe v1: GET /v1/dni?numero={dni}  Autorización: Bearer {token}
            $response = Http::timeout(8)
                ->withToken($this->token)
                ->acceptJson()
                ->get("{$this->baseUrl}/v1/dni", ['numero' => $dni]);

            if ($response->successful()) {
                $data = $response->json();
                $data = $data['data'] ?? $data;

                $nombres        = $data['nombres']         ?? null;
                $apPaterno      = $data['apellidoPaterno'] ?? $data['apellido_paterno'] ?? '';
                $apMaterno      = $data['apellidoMaterno'] ?? $data['apellido_materno'] ?? '';
                $nombreCompleto = $data['nombreCompleto']  ?? $data['nombre_completo']  ?? null;

                if (!empty($nombres) || !empty($nombreCompleto)) {
                    return [
                        'tipo_doc'         => '1',
                        'num_doc'          => $dni,
                        'razon_social'     => $nombreCompleto ?? trim("{$apPaterno} {$apMaterno} {$nombres}"),
                        'nombres'          => $nombres ?? '',
                        'apellido_paterno' => $apPaterno,
                        'apellido_materno' => $apMaterno,
                        'direccion'        => $data['direccion'] ?? $data['direccion_completa'] ?? '',
                        'source'           => 'reniec',
                    ];
                }

                \Log::error("DNI {$dni}: respuesta OK pero sin nombres", ['body' => $data]);
            } else {
                \Log::error("DNI {$dni}: HTTP {$response->status()} desde apis.net.pe", ['body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            \Log::error("DNI lookup excepción para {$dni}: " . $e->getMessage());
        }

        return null;
    }
}

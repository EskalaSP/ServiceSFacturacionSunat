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

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($tipo, $numero) {
            return $tipo === '6'
                ? $this->lookupRuc($numero)
                : $this->lookupDni($numero);
        });
    }

    private function lookupRuc(string $ruc): ?array
    {
        try {
            // apis.net.pe: GET /v1/ruc?numero={ruc}  Authorization: Bearer {token}
            $response = Http::timeout(8)
                ->withToken($this->token)
                ->acceptJson()
                ->get("{$this->baseUrl}/v1/ruc", ['numero' => $ruc]);

            if ($response->successful()) {
                $body = $response->json();
                // Acepta respuesta envuelta en "data" o directa
                $data = $body['data'] ?? $body;

                $razonSocial = $data['razonSocial']
                    ?? $data['nombre_o_razon_social']
                    ?? null;

                if (!empty($razonSocial)) {
                    return [
                        'tipo_doc'     => '6',
                        'num_doc'      => $ruc,
                        'razon_social' => $razonSocial,
                        'direccion'    => $data['direccion']        ?? $data['direccion_completa'] ?? '',
                        'estado'       => $data['estado']           ?? $data['estado_contribuyente'] ?? '',
                        'condicion'    => $data['condicion']        ?? $data['condicion_contribuyente'] ?? '',
                        'ubigeo'       => $data['ubigeo']           ?? $data['ubigeo_sunat'] ?? '',
                        'source'       => 'sunat',
                    ];
                }
            }
        } catch (\Throwable) {
            // silently fail — el controlador devuelve 404
        }

        return null;
    }

    private function lookupDni(string $dni): ?array
    {
        try {
            // api.json.pe: GET /api/v2/reniec?dni={dni}  Authorization: Bearer {token}
            $response = Http::timeout(8)
                ->withToken($this->token)
                ->acceptJson()
                ->get("{$this->baseUrl}/v2/reniec", ['dni' => $dni]);

            if ($response->successful()) {
                $body = $response->json();
                $data = $body['data'] ?? $body;

                $nombres    = $data['nombres']        ?? null;
                $apPaterno  = $data['apellidoPaterno'] ?? $data['apellido_paterno'] ?? '';
                $apMaterno  = $data['apellidoMaterno'] ?? $data['apellido_materno'] ?? '';
                $nombreCompleto = $data['nombreCompleto'] ?? $data['nombre_completo'] ?? null;

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
            }
        } catch (\Throwable) {
            // silently fail — el controlador devuelve 404
        }

        return null;
    }
}

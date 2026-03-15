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
        $this->baseUrl = config('facturacion.lookup.base_url');
        $this->token = config('facturacion.lookup.token');
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
            $response = Http::timeout(8)
                ->withToken($this->token)
                ->post("{$this->baseUrl}/ruc", ['ruc' => $ruc]);

            if ($response->successful()) {
                $data = $response->json('data');
                if (!empty($data['nombre_o_razon_social'])) {
                    return [
                        'tipo_doc' => '6',
                        'num_doc' => $ruc,
                        'razon_social' => $data['nombre_o_razon_social'],
                        'direccion' => $data['direccion_completa'] ?? $data['direccion'] ?? '',
                        'estado' => $data['estado'] ?? '',
                        'condicion' => $data['condicion'] ?? '',
                        'ubigeo' => $data['ubigeo_sunat'] ?? '',
                        'source' => 'sunat',
                    ];
                }
            }
        } catch (\Throwable) {
            // silently fail
        }

        return null;
    }

    private function lookupDni(string $dni): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withToken($this->token)
                ->post("{$this->baseUrl}/dni", ['dni' => $dni]);

            if ($response->successful()) {
                $data = $response->json('data');
                if (!empty($data['nombres'])) {
                    return [
                        'tipo_doc' => '1',
                        'num_doc' => $dni,
                        'razon_social' => $data['nombre_completo'] ?? trim("{$data['apellido_paterno']} {$data['apellido_materno']} {$data['nombres']}"),
                        'nombres' => $data['nombres'] ?? '',
                        'apellido_paterno' => $data['apellido_paterno'] ?? '',
                        'apellido_materno' => $data['apellido_materno'] ?? '',
                        'direccion' => $data['direccion_completa'] ?? '',
                        'source' => 'reniec',
                    ];
                }
            }
        } catch (\Throwable) {
            // silently fail
        }

        return null;
    }
}

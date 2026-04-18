<?php

namespace App\Sire\Services\Periodos;

use App\Models\Tenant;
use App\Sire\Enums\CodLibro;
use App\Sire\Services\Http\SireHttpClient;

/**
 * Servicio 5.33 — Consultar años y meses del RCE.
 *
 * GET /v1/contribuyente/migeigv/libros/rvierce/padron/web/omisos/{codLibro}/periodos
 *
 * Respuesta (salida según manual v22):
 *   [
 *     {
 *       numEjercicio: "2024",
 *       desEstado: "...",
 *       lisPeriodos: [
 *         { perTributario: "202401", codEstado: "...", desEstado: "..." },
 *         ...
 *       ]
 *     },
 *     ...
 *   ]
 */
class PeriodoService
{
    public function __construct(
        private readonly SireHttpClient $http,
    ) {}

    /**
     * Lista los periodos disponibles para el tenant en el libro indicado.
     */
    public function listar(Tenant $tenant, CodLibro $libro = CodLibro::RCE): array
    {
        $path = sprintf(
            'contribuyente/migeigv/libros/rvierce/padron/web/omisos/%s/periodos',
            $libro->value,
        );

        $response = $this->http->get($tenant, $path);

        // SUNAT devuelve directamente un array o un objeto con un array adentro.
        // Normalizamos para el controller.
        return $this->normalize($response);
    }

    /**
     * Normaliza la respuesta de SUNAT.
     * Algunas variantes devuelven directamente una lista, otras la envuelven.
     */
    private function normalize(array $raw): array
    {
        // Si viene como lista de ejercicios
        if (array_is_list($raw)) {
            return array_map(fn ($ejercicio) => $this->mapEjercicio($ejercicio), $raw);
        }

        // Si viene como un solo ejercicio
        if (isset($raw['numEjercicio'])) {
            return [$this->mapEjercicio($raw)];
        }

        // Si viene envuelto en "data" o similar
        if (isset($raw['data']) && is_array($raw['data'])) {
            return $this->normalize($raw['data']);
        }

        return [];
    }

    private function mapEjercicio(array $ejercicio): array
    {
        return [
            'anio'        => $ejercicio['numEjercicio'] ?? null,
            'des_estado'  => $ejercicio['desEstado'] ?? null,
            'periodos'    => array_map(
                fn ($p) => [
                    'per_tributario' => $p['perTributario'] ?? null,
                    'cod_estado'     => $p['codEstado'] ?? null,
                    'des_estado'     => $p['desEstado'] ?? null,
                ],
                $ejercicio['lisPeriodos'] ?? [],
            ),
        ];
    }
}

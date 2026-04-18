<?php

namespace App\Sire\Services\Constancia;

use App\Models\Tenant;
use App\Sire\Services\Http\SireHttpClient;
use Psr\Http\Message\ResponseInterface;

/**
 * Servicio 5.49 — Descargar constancia de recepción.
 *
 * GET /v1/contribuyente/migeigv/libros/rvierce/gestionlibro/web/registroslibros
 *     /constancia/constanciarecepcion?nomConstanciaRecepcion={nombre}
 *
 * Respuesta: PDF binario (Arreglo de Bytes según manual v22 sección 5.49 — actualizado en v22).
 *
 * El parámetro `nomConstanciaRecepcion` es el nombre del archivo generado por
 * SUNAT al finalizar el registro (normalmente se obtiene tras registrar preliminar).
 */
class ConstanciaService
{
    public function __construct(
        private readonly SireHttpClient $http,
    ) {}

    public function descargar(Tenant $tenant, string $nomConstancia): ResponseInterface
    {
        $path = 'contribuyente/migeigv/libros/rvierce/gestionlibro/web/registroslibros/constancia/constanciarecepcion';

        return $this->http->getRaw($tenant, $path, [
            'nomConstanciaRecepcion' => $nomConstancia,
        ]);
    }
}

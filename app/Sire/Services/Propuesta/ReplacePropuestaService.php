<?php

namespace App\Sire\Services\Propuesta;

use App\Sire\Enums\CodProceso;
use App\Sire\Services\Upload\BaseUploadService;

/**
 * Servicio 5.3 — Importar reemplazo de la propuesta.
 *
 * POST https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/receptorpropuesta/web/propuesta/upload
 *
 * CodProceso: 61 (Reemplazo de la Propuesta)
 *
 * Permite al generador reemplazar la propuesta SUNAT con su propio archivo .txt
 * empaquetado en .zip. El sistema devuelve numTicket para seguimiento.
 */
class ReplacePropuestaService extends BaseUploadService
{
    protected function endpointUrl(): string
    {
        return rtrim(config('sire.hosts.api'), '/')
            . '/contribuyente/migeigv/libros/rvierce/receptorpropuesta/web/propuesta/upload';
    }

    protected function codProceso(): CodProceso
    {
        return CodProceso::REEMPLAZAR_PROPUESTA;
    }
}

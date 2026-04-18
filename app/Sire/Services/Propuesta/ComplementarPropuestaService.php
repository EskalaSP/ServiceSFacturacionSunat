<?php

namespace App\Sire\Services\Propuesta;

use App\Sire\Enums\CodProceso;
use App\Sire\Services\Upload\BaseUploadService;

/**
 * Servicio 5.6 — Importar datos complementarios de los CP de la propuesta.
 *
 * POST https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/receptorpropuesta/web/propuesta/upload
 *
 * CodProceso: 54 (Carga Complementar)
 *
 * Permite complementar datos (fechas, detracciones, observaciones) de los
 * comprobantes ya propuestos por SUNAT, sin reemplazar la propuesta completa.
 */
class ComplementarPropuestaService extends BaseUploadService
{
    protected function endpointUrl(): string
    {
        return rtrim(config('sire.hosts.api'), '/')
            . '/contribuyente/migeigv/libros/rvierce/receptorpropuesta/web/propuesta/upload';
    }

    protected function codProceso(): CodProceso
    {
        return CodProceso::COMPLEMENTAR_PROPUESTA;
    }
}

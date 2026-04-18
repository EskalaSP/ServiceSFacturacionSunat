<?php

namespace App\Sire\Services\Preliminar;

use App\Sire\Enums\CodProceso;
use App\Sire\Services\Upload\BaseUploadService;

/**
 * Servicio 5.5 — Cargar registro de compra no domiciliados.
 *
 * POST https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/receptorpreliminar/web/preliminar/upload
 *
 * CodProceso: 56 (Carga No Domiciliados)
 *
 * Se usa cuando el tenant elige incluir comprobantes de proveedores no domiciliados
 * en su RCE. Antes se debe haber aceptado/reemplazado la propuesta.
 */
class NoDomiciliadosService extends BaseUploadService
{
    protected function endpointUrl(): string
    {
        return rtrim(config('sire.hosts.api'), '/')
            . '/contribuyente/migeigv/libros/rvierce/receptorpreliminar/web/preliminar/upload';
    }

    protected function codProceso(): CodProceso
    {
        return CodProceso::CARGA_NO_DOMICILIADOS;
    }
}

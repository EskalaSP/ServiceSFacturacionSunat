<?php

namespace App\Services\License;

/**
 * Sello de entrega de ESTA copia del sistema.
 *
 * El fingerprint identifica la copia entregada a un comprador concreto. Va
 * embebido en el código (no en .env, para que no se pueda cambiar fácil) y se
 * escribe al empaquetar la venta con:
 *
 *     php artisan license:stamp {fingerprint}
 *
 * El fingerprint lo genera el panel del proveedor al emitir la licencia. Si la
 * copia se filtra, este sello permite rastrear de quién salió. Este archivo
 * debe ofuscarse junto con el resto del candado (ver LICENSING.md).
 */
class BuildInfo
{
    // Marcador reemplazado al sellar la copia. No editar a mano.
    private const FINGERPRINT = '__LICENSE_FINGERPRINT__';

    /**
     * Fingerprint de esta copia, o null si todavía no se ha sellado.
     */
    public static function fingerprint(): ?string
    {
        return self::FINGERPRINT === '__LICENSE_FINGERPRINT__' ? null : self::FINGERPRINT;
    }
}

<?php

namespace App\Sire\Support;

/**
 * Genera nombres de archivo válidos según la convención posicional SUNAT.
 * El error 1044 del manual indica que SUNAT valida el nombre por posición,
 * así que este builder centraliza esa lógica para evitar rechazos.
 *
 * Ejemplo real del manual (línea 1073):
 *   LE20100176450202302001404000311102.zip
 *        ^^^^^^^^^^^^^^  ^^^^^^  ^^  ^^  ^^^^^^
 *        RUC(11)        PERIODO LIB PROC SECUENCIA
 *
 * Estructura observada:
 *   LE + numRuc(11) + perTributario(6) + codLibro(6) + codProceso(3) + secuencia(8) + .zip
 */
class NombreArchivoBuilder
{
    /**
     * Construye el nombre del archivo ZIP para upload a SIRE.
     *
     * @param string $ruc             RUC de 11 dígitos
     * @param string $perTributario   yyyymm
     * @param string $codLibro        6 dígitos (080000 RCE)
     * @param string $codProceso      Indicador de carga masiva (Anexo I)
     * @param int    $secuencia       Número correlativo del envío (1, 2, 3...)
     * @param string $extension       'zip' (default) o 'txt'
     */
    public static function build(
        string $ruc,
        string $perTributario,
        string $codLibro,
        string $codProceso,
        int $secuencia = 1,
        string $extension = 'zip',
    ): string {
        return sprintf(
            'LE%s%s%s%s%s.%s',
            str_pad($ruc, 11, '0', STR_PAD_LEFT),
            $perTributario,
            str_pad($codLibro, 6, '0', STR_PAD_LEFT),
            str_pad($codProceso, 3, '0', STR_PAD_LEFT),
            str_pad((string) $secuencia, 8, '0', STR_PAD_LEFT),
            $extension,
        );
    }
}

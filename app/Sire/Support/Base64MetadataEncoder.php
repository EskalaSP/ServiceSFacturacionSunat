<?php

namespace App\Sire\Support;

/**
 * Encoder para metadata TUS.io exigida por SUNAT en uploads (5.3, 5.5, 5.6, 5.8, 5.9).
 *
 * El header `Upload-Metadata` debe tener forma:
 *   key1 base64(value1),key2 base64(value2),...
 *
 * Ejemplo real del manual v22:
 *   filename TEUyMDEw...zaXA=,numRuc MjAxMDAxNzY0NTA=,perTributario MjAyMzAy,...
 */
class Base64MetadataEncoder
{
    /**
     * Arma el valor del header `Upload-Metadata`.
     *
     * @param array<string, string> $metadata  Asociativo clave => valor-sin-encodear
     */
    public static function encode(array $metadata): string
    {
        $parts = [];
        foreach ($metadata as $key => $value) {
            $parts[] = $key . ' ' . base64_encode((string) $value);
        }

        return implode(',', $parts);
    }

    /**
     * Arma la metadata completa requerida por los servicios de upload de SIRE.
     *
     * @param array{
     *   filename: string,
     *   filetype: string,
     *   numRuc: string,
     *   perTributario: string,
     *   codOrigenEnvio: string,
     *   codProceso: string,
     *   codTipoCorrelativo: string,
     *   nomArchivoImportacion: string,
     *   codLibro: string,
     * } $input
     */
    public static function forSireUpload(array $input): string
    {
        return self::encode([
            'filename'              => $input['filename'],
            'filetype'              => $input['filetype'],
            'numRuc'                => $input['numRuc'],
            'perTributario'         => $input['perTributario'],
            'codOrigenEnvio'        => $input['codOrigenEnvio'],
            'codProceso'            => $input['codProceso'],
            'codTipoCorrelativo'    => $input['codTipoCorrelativo'],
            'nomArchivoImportacion' => $input['nomArchivoImportacion'],
            'codLibro'              => $input['codLibro'],
        ]);
    }
}

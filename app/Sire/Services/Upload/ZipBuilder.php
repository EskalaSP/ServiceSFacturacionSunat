<?php

namespace App\Sire\Services\Upload;

use App\Sire\Support\NombreArchivoBuilder;
use ZipArchive;

/**
 * Arma archivos .zip listos para subirse a SIRE.
 *
 * SUNAT valida el nombre por posición (error 1044). El TXT DENTRO del ZIP
 * también debe respetar la convención. Esta clase centraliza ambos aspectos.
 *
 * Uso típico:
 *   $result = $builder->build(
 *       ruc: '20100000001',
 *       perTributario: '202604',
 *       codLibro: '080000',
 *       codProceso: '61',
 *       txtContent: "202604|...\n202604|...\n",
 *       destDir: storage_path('sire-temp'),
 *   );
 *   // $result->zipPath, $result->zipName, $result->txtName
 */
class ZipBuilder
{
    /**
     * @return object{zipPath: string, zipName: string, txtName: string, size: int, sha256: string}
     */
    public function build(
        string $ruc,
        string $perTributario,
        string $codLibro,
        string $codProceso,
        string $txtContent,
        string $destDir,
        int $secuencia = 1,
    ): object {
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $txtName = NombreArchivoBuilder::build(
            ruc: $ruc,
            perTributario: $perTributario,
            codLibro: $codLibro,
            codProceso: $codProceso,
            secuencia: $secuencia,
            extension: 'txt',
        );

        $zipName = NombreArchivoBuilder::build(
            ruc: $ruc,
            perTributario: $perTributario,
            codLibro: $codLibro,
            codProceso: $codProceso,
            secuencia: $secuencia,
            extension: 'zip',
        );

        $zipPath = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("No se pudo crear el ZIP: {$zipPath}");
        }

        $zip->addFromString($txtName, $txtContent);
        $zip->close();

        return (object) [
            'zipPath' => $zipPath,
            'zipName' => $zipName,
            'txtName' => $txtName,
            'size'    => filesize($zipPath),
            'sha256'  => hash_file('sha256', $zipPath),
        ];
    }

    /**
     * Construye un ZIP a partir de un archivo TXT ya existente.
     */
    public function buildFromTxtFile(
        string $ruc,
        string $perTributario,
        string $codLibro,
        string $codProceso,
        string $txtPath,
        string $destDir,
        int $secuencia = 1,
    ): object {
        if (! is_readable($txtPath)) {
            throw new \RuntimeException("TXT no legible: {$txtPath}");
        }

        return $this->build(
            ruc: $ruc,
            perTributario: $perTributario,
            codLibro: $codLibro,
            codProceso: $codProceso,
            txtContent: file_get_contents($txtPath),
            destDir: $destDir,
            secuencia: $secuencia,
        );
    }
}

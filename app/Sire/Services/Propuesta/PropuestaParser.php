<?php

namespace App\Sire\Services\Propuesta;

use Illuminate\Support\Carbon;
use ZipArchive;

/**
 * Parser del archivo TXT de Propuesta RCE de SUNAT.
 *
 * Formato observado (Res. 112-2021/SUNAT y modificatorias) — campos separados por `|`.
 * El layout real puede tener variaciones; mantenemos `raw_line` para debugging.
 *
 * Mapeo por posición (basado en el formato estándar SIRE RCE):
 *   0  perTributario
 *   1  carSunat
 *   2  indicador (fila)
 *   3  fecha emisión
 *   4  fecha vencimiento
 *   5  codTipoCDP
 *   6  numSerieCDP
 *   7  numCDP
 *   8  tipoDocProveedor
 *   9  numDocProveedor
 *   10 razonSocialProveedor
 *   11 mtoBiGravada
 *   12 mtoIGV
 *   13 mtoBiNoGravada (base sin crédito fiscal)
 *   14 mtoNoGravadas
 *   15 mtoISC
 *   16 mtoICBPER
 *   17 mtoOtros
 *   18 mtoTotal
 *   19 codMoneda
 *   20 tipoCambio
 *   ...
 */
class PropuestaParser
{
    /**
     * Lee el ZIP, extrae el TXT y devuelve array de comprobantes parseados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseZipFile(string $zipPath): array
    {
        $zip = new ZipArchive();
        $opened = $zip->open($zipPath);

        if ($opened !== true) {
            throw new \RuntimeException("No se pudo abrir el ZIP: código {$opened}");
        }

        $lines = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! str_ends_with(strtolower($name), '.txt')) {
                continue;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false) {
                continue;
            }

            // SIRE a veces usa BOM UTF-8
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

            foreach (preg_split("/\r\n|\n|\r/", $content) as $line) {
                $line = trim($line);
                if ($line === '' || $this->isHeader($line)) {
                    continue;
                }
                $lines[] = $line;
            }
        }

        $zip->close();

        return array_map(fn (string $line) => $this->parseLine($line), $lines);
    }

    /**
     * Parsea una línea del TXT en un array asociativo.
     */
    public function parseLine(string $line): array
    {
        $f = explode('|', $line);

        return [
            'raw_line'               => $line,
            'per_tributario'         => $f[0] ?? null,
            'car_sunat'              => $f[1] ?? null,
            'fec_emision'            => $this->parseDate($f[3] ?? null),
            'fec_vencimiento'        => $this->parseDate($f[4] ?? null),
            'cod_tipo_cdp'           => isset($f[5]) ? str_pad($f[5], 2, '0', STR_PAD_LEFT) : null,
            'num_serie_cdp'          => $f[6] ?? null,
            'num_cdp'                => $f[7] ?? null,
            'tipo_doc_proveedor'     => $f[8] ?? null,
            'num_doc_proveedor'      => $f[9] ?? null,
            'razon_social_proveedor' => $f[10] ?? null,
            'mto_bi_gravada'         => $this->parseDecimal($f[11] ?? null),
            'mto_igv'                => $this->parseDecimal($f[12] ?? null),
            'mto_bi_no_gravada'      => $this->parseDecimal($f[13] ?? null),
            'mto_total'              => $this->parseDecimal($f[18] ?? null),
            'cod_moneda'             => $f[19] ?? 'PEN',
            'tipo_cambio'            => $this->parseDecimal($f[20] ?? null),
        ];
    }

    /**
     * Las cabeceras de SIRE típicamente empiezan con "PERIODO" o no son numéricas en posición 0.
     */
    private function isHeader(string $line): bool
    {
        $first = explode('|', $line)[0] ?? '';
        return ! preg_match('/^\d{6}$/', trim($first));
    }

    private function parseDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // SUNAT usa varios formatos: dd/mm/yyyy, yyyy-mm-dd, yyyymmdd
        try {
            if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $value)) {
                return Carbon::createFromFormat('d/m/Y', $value)->toDateString();
            }
            if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $value)) {
                return $value;
            }
            if (preg_match('~^\d{8}$~', $value)) {
                return Carbon::createFromFormat('Ymd', $value)->toDateString();
            }
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDecimal(?string $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        // SUNAT usa punto como decimal; limpiamos cualquier coma de miles
        $clean = str_replace([','], ['.'], trim($value));
        return is_numeric($clean) ? (float) $clean : 0.0;
    }
}

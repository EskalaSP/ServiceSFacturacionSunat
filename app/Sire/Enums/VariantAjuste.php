<?php

namespace App\Sire\Enums;

/**
 * Variantes de Ajustes Posteriores del RCE.
 *
 * Determina:
 *   - Path del endpoint SUNAT (periodo actual / no domiciliados / periodos anteriores / ...)
 *   - CodProceso asociado (Anexo I)
 *   - IndTipoAjustePosterior (Anexo II)
 */
enum VariantAjuste: string
{
    case PERIODO_ACTUAL              = 'actual';
    case NO_DOMICILIADOS             = 'no_domiciliados';
    case PERIODOS_ANTERIORES         = 'periodos_anteriores';
    case PERIODOS_ANTERIORES_ND      = 'periodos_anteriores_nd';

    public function label(): string
    {
        return match ($this) {
            self::PERIODO_ACTUAL         => 'Ajuste Posterior del periodo actual',
            self::NO_DOMICILIADOS        => 'Ajuste Posterior con No Domiciliados',
            self::PERIODOS_ANTERIORES    => 'Ajuste de periodos anteriores',
            self::PERIODOS_ANTERIORES_ND => 'Ajuste de periodos anteriores con No Domiciliados',
        };
    }

    /**
     * CodProceso del Anexo I a usar en el upload TUS.
     */
    public function codProceso(): CodProceso
    {
        return match ($this) {
            self::PERIODO_ACTUAL         => CodProceso::IMPORTAR_CP_AJUSTES_POST,         // 59
            self::NO_DOMICILIADOS        => CodProceso::IMPORTAR_CP_NO_DOM_AJUSTES,       // 60
            self::PERIODOS_ANTERIORES    => CodProceso::IMPORTAR_CP_AJUSTES_POST,         // 94 también aplica
            self::PERIODOS_ANTERIORES_ND => CodProceso::IMPORTAR_CP_NO_DOM_AJUSTES,       // 95 también aplica
        };
    }

    /**
     * Valor para `indTipoAjustePosterior` (Anexo II).
     */
    public function indTipoAjustePosterior(): string
    {
        return match ($this) {
            self::PERIODO_ACTUAL         => '1',
            self::NO_DOMICILIADOS        => '2',
            self::PERIODOS_ANTERIORES    => '3',
            self::PERIODOS_ANTERIORES_ND => '5',
        };
    }

    /**
     * Segmento del path en endpoints de carga (5.18/5.21/5.24/5.27).
     */
    public function uploadSegment(): string
    {
        return match ($this) {
            self::PERIODO_ACTUAL         => 'receptorajustesposteriores/web/ajustesposteriores/upload',
            self::NO_DOMICILIADOS        => 'receptorajustesposterioresnd/web/ajustesposterioresnd/upload',
            self::PERIODOS_ANTERIORES    => 'receptorajustesposteriorespa/web/ajustesposteriorespa/upload',
            self::PERIODOS_ANTERIORES_ND => 'receptorajustesposteriorespand/web/ajustesposteriorespand/upload',
        };
    }

    /**
     * Segmento del path en endpoints de envío/eliminación (5.19/5.20/5.22/5.23/...).
     */
    public function operationSegment(): string
    {
        return match ($this) {
            self::PERIODO_ACTUAL         => 'rce/ajustesposteriores/web/comprobantesajuspost',
            self::NO_DOMICILIADOS        => 'rce/ajustesposteriores/web/comprobantesajuspostnd',
            self::PERIODOS_ANTERIORES    => 'rce/ajustesposteriores/web/comprobantesajuspostpa',
            self::PERIODOS_ANTERIORES_ND => 'rce/ajustesposteriores/web/comprobantesajuspostpand',
        };
    }

    /**
     * Segmento del path en endpoints de descarga (5.45/5.46/5.47/5.48).
     */
    public function exportSegment(): string
    {
        return match ($this) {
            self::PERIODO_ACTUAL         => 'exportaajustesposterioresrc',
            self::NO_DOMICILIADOS        => 'exportarajustesposterioresrcnd',
            self::PERIODOS_ANTERIORES    => 'exportaajustesposterioresrcpa',
            self::PERIODOS_ANTERIORES_ND => 'exportarajustesposterioresrcpand',
        };
    }
}

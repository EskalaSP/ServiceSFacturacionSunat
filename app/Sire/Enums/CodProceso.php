<?php

namespace App\Sire\Enums;

/**
 * Indicadores de carga masiva (Anexo I del manual).
 * Solo incluimos los que usamos desde la API.
 */
enum CodProceso: string
{
    case IMPORTAR_CP_PROPUESTA          = '1';
    case ACEPTAR_PROPUESTA              = '2';
    case IMPORTA_CP_PRELIMINAR          = '4';
    case GENERAR_EXPORT_PROPUESTA       = '10';
    case GENERAR_EXPORT_PRELIMINAR      = '12';
    case COMPLEMENTAR_PROPUESTA         = '54';
    case INCLUIR_EXCLUIR_CP             = '55';
    case CARGA_NO_DOMICILIADOS          = '56';
    case CARGA_COMPARACION_RCE          = '57';
    case IMPORTAR_CP_AJUSTES_POST       = '59';
    case IMPORTAR_CP_NO_DOM_AJUSTES     = '60';
    case REEMPLAZAR_PROPUESTA           = '61';
    case CONSTANCIA_RECEPCION_RCE       = '46';
    case DESCARGA_CONSOLIDADA_RCE       = '69';
    case DESCARGA_RCE                   = '70';

    public function label(): string
    {
        return match ($this) {
            self::IMPORTAR_CP_PROPUESTA      => 'Importar CP - Propuesta',
            self::ACEPTAR_PROPUESTA          => 'Aceptar propuesta',
            self::IMPORTA_CP_PRELIMINAR      => 'Importa CP - Preliminar',
            self::GENERAR_EXPORT_PROPUESTA   => 'Generar archivo exportar propuesta',
            self::GENERAR_EXPORT_PRELIMINAR  => 'Generar archivo exportar preliminar',
            self::COMPLEMENTAR_PROPUESTA     => 'Carga Complementar',
            self::INCLUIR_EXCLUIR_CP         => 'Carga Incluir Excluir',
            self::CARGA_NO_DOMICILIADOS      => 'Carga No Domiciliados',
            self::CARGA_COMPARACION_RCE      => 'Carga Comparación RCE',
            self::IMPORTAR_CP_AJUSTES_POST   => 'Importar CP en Ajustes Posteriores RCE',
            self::IMPORTAR_CP_NO_DOM_AJUSTES => 'Importar CP no domiciliados en Ajustes Posteriores',
            self::REEMPLAZAR_PROPUESTA       => 'Reemplazo de la Propuesta',
            self::CONSTANCIA_RECEPCION_RCE   => 'Descargar Constancia de Recepción RCE',
            self::DESCARGA_CONSOLIDADA_RCE   => 'Descarga Consolidada de registros del RCE',
            self::DESCARGA_RCE               => 'Descarga RCE',
        };
    }
}

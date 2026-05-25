<?php

namespace App\Sire\Exceptions;

/**
 * Catálogo de errores 422 devueltos por SUNAT en los servicios SIRE.
 * Extraído del manual v22 (secciones 5.2 - 5.58).
 */
class SireErrorCatalog
{
    /**
     * Mapa de códigos a mensajes legibles en español.
     */
    private const MESSAGES = [
        // RUC y perTributario
        '1001' => 'El campo "numRuc" no fue enviado o está vacío.',
        '1002' => 'El número de RUC debe tener 11 dígitos numéricos.',
        '1003' => 'El RUC ingresado no existe o no es válido.',
        '1005' => 'El campo "perTributario" no fue enviado o está vacío.',
        '1006' => 'El formato de perTributario no cumple con "yyyymm".',
        '1007' => 'El perTributario no debe ser mayor a la fecha actual.',
        '1008' => 'El registro electrónico ya se encuentra en el módulo de preliminar.',
        '1009' => 'El registro electrónico ya ha sido generado.',
        '1014' => 'Solo se permite dato numérico de 6 dígitos para el perTributario.',
        '1064' => 'El periodo no debe ser mayor al periodo de la fecha actual.',
        '1093' => 'El formato de periodo no cumple con "yyyymm".',

        // Tipo comprobante
        '1011' => 'El campo "codTipoCDP" no fue enviado o está vacío.',
        '1012' => 'El código de tipo de CDP no existe o no es válido.',
        '1104' => 'El código de tipo de comprobante de pago no es válido.',

        // Archivos y carga
        '1022' => 'Nombre del archivo no enviado o está vacío.',
        '1024' => 'El archivo fue previamente enviado.',
        '1044' => 'Error en el formato del nombre del archivo plano. Corregir según la convención SUNAT.',
        '1346' => 'El tamaño del archivo .zip debe ser menor o igual a 6 GB.',
        '1348' => 'La extensión del archivo debe ser .zip.',
        '1350' => 'El tamaño del archivo debe ser mayor a 0 KB.',
        '1351' => 'Error al realizar el envío del archivo. Vuelve a intentar.',

        // codProceso / codOrigenEnvio
        '1025' => 'El campo "codProceso" no fue enviado o está vacío.',
        '1026' => 'Código de proceso no permitido o no válido.',
        '1027' => 'Solo se permite dato numérico para el codProceso.',
        '1028' => 'El campo "codOrigenEnvio" no fue enviado o está vacío.',
        '1029' => 'Código de tipo de Origen de Envío no permitido o no válido.',
        '1030' => 'Solo se permite dato numérico de 1 dígito para el codOrigenEnvio.',
        '1048' => 'Solo se permite dato numérico para el codTipoCorrelativo.',
        '1049' => 'El campo "codTipoCorrelativo" no fue enviado o está vacío.',
        '1050' => 'Código de tipo de Correlativo no permitido o no válido.',
        '1138' => 'El campo "codProceso" es nulo o vacío.',
        '1139' => 'Código de Proceso no permitido o no válido.',
        '1140' => 'El campo "codLibro" no fue enviado o está vacío.',
        '1161' => 'Código de Libro no permitido o no válido.',

        // Tipo archivo / resumen
        '1056' => 'Solo se permite dato numérico de 1 dígito para el codTipoResumen.',
        '1057' => 'El campo "codTipoResumen" es nulo o vacío.',
        '1059' => 'Código tipo de Archivo no permitido o no válido.',
        '1060' => 'Solo se permite dato numérico de 1 dígito para el codTipoArchivo.',
        '1061' => 'El campo "codTipoArchivo" es nulo o vacío.',

        // Búsquedas
        '1067' => 'El campo "perIni" no fue enviado o está vacío.',
        '1068' => 'El formato de perIni no cumple con "yyyymm".',
        '1069' => 'El perIni de búsqueda no debe ser mayor a la fecha actual.',
        '1070' => 'No se ha encontrado información de comprobantes de pago en el periodo seleccionado.',
        '1071' => 'El campo "perFin" no fue enviado o está vacío.',
        '1072' => 'El formato de perFin no cumple con "yyyymm".',
        '1073' => 'El perFin de búsqueda no debe ser mayor a la fecha actual.',
        '1076' => 'El campo "page" no fue enviado o está vacío.',
        '1077' => 'El campo "page" debe ser numérico mayor a cero.',
        '1078' => 'El campo "per_page" debe ser numérico mayor a cero.',
        '1079' => 'El campo "perPage" no fue enviado o está vacío.',
        '1052' => 'Formato no permitido o no válido para el número de Ticket.',

        // Montos y fechas
        '1099' => 'El campo "fecEmisionIni" no fue enviado o está vacío.',
        '1101' => 'El campo "fecEmisionFin" no fue enviado o está vacío.',
        '1112' => 'El Monto Total Desde debe ser mayor o igual al Monto Total Hasta.',
        '1113' => 'Si se realiza búsqueda por monto total, debe ingresar mtoTotalDesde y mtoTotalHasta.',
        '1114' => 'Fecha Documento Desde debe estar dentro del periodo seleccionado.',
        '1116' => 'Fecha de documento Hasta debe ser mayor o igual a Fecha Desde.',
        '1118' => 'Fecha Documento Desde debe estar dentro del periodo seleccionado.',
        '1119' => 'El código de tipo de inconsistencia no es válido.',
        '1134' => 'El campo "nomArchivoReporte" no fue enviado o está vacío.',
        '1518' => 'No existen documentos para exportar.',

        // Específicos de descarga archivo (5.32 y 5.34)
        '2267' => 'El formato del campo "mtoDesde" no es válido.',
        '2268' => 'El formato del campo "mtoHasta" no es válido.',
        '2270' => 'El campo "fecEmisionIni" debe cumplir con el formato "yyyy-mm-dd".',
        '2271' => 'El campo "fecEmisionFin" debe cumplir con el formato "yyyy-mm-dd".',
        '2272' => 'El campo "mtoDesde" no fue enviado o está vacío.',
        '2273' => 'El campo "mtoHasta" no fue enviado o está vacío.',
        '2274' => 'El campo "numSerieCDP" no fue enviado o está vacío.',
        '2275' => 'El campo "numCDP" no fue enviado o está vacío.',
        '2276' => 'El campo "codInconsistencia" no fue enviado o está vacío.',
        '2277' => 'El campo "numDocAdquiriente" no fue enviado o está vacío.',
        '2278' => 'El campo "codTipoArchivoReporte" no fue enviado o está vacío.',
    ];

    public static function friendlyMessage(string $code, ?string $fallback = null): string
    {
        return self::MESSAGES[$code] ?? ($fallback ?: "Error SIRE con código {$code}.");
    }

    /**
     * Parsea la respuesta 422 de SUNAT que tiene forma:
     *   { "cod": "422", "msg": "...", "errors": [{"cod": "1001", "msg": "..."}, ...] }
     * Retorna una lista de mensajes legibles.
     *
     * @return array<int, array{cod: string, msg: string, friendly: string}>
     */
    public static function parseSunatErrorResponse(array $response): array
    {
        $errors = $response['errors'] ?? [];
        $result = [];

        foreach ($errors as $err) {
            $code = (string) ($err['cod'] ?? '');
            $msg  = (string) ($err['msg'] ?? '');
            $result[] = [
                'cod' => $code,
                'msg' => $msg,
                'friendly' => self::friendlyMessage($code, $msg),
            ];
        }

        return $result;
    }
}

<?php

/*
|--------------------------------------------------------------------------
| Configuración del módulo SIRE (Sistema Integrado de Registros Electrónicos)
|--------------------------------------------------------------------------
|
| Valores extraídos del PDF oficial SUNAT:
| "Manual de servicios Web Api - SIRE_Compras v22.pdf"
|
*/

return [

    // Hosts oficiales SUNAT — NO hay entorno beta para SIRE, solo producción.
    'hosts' => [
        'auth' => env('SIRE_AUTH_HOST', 'https://api-seguridad.sunat.gob.pe/v1'),
        'api'  => env('SIRE_API_HOST',  'https://api-sire.sunat.gob.pe/v1'),
    ],

    // Scope OAuth fijo según sección 5.1 del manual
    'scope' => 'https://api-sire.sunat.gob.pe',

    // Códigos de libro (ver Anexo I del manual)
    'cod_libro' => [
        'rce'  => '080000', // Registro de Compras Electrónico
        'rvie' => '140000', // Registro de Ventas e Ingresos Electrónico
    ],

    // Código de origen de envío — 2 = Servicio web (fijo para nuestra API, según manual)
    'cod_origen_envio' => '2',

    // Tipo de correlativo — 01 = envíos masivos (ver Anexo II)
    'cod_tipo_correlativo' => '01',

    // Token OAuth (ver sección 5.1)
    'token' => [
        'safety_margin_seconds' => 60,       // renovar antes de expirar
        'cache_prefix' => 'sire_token:',
        'grant_type' => 'password',          // según manual 5.1
    ],

    // Polling de tickets (ver sección 5.31)
    'polling' => [
        'interval_seconds'    => 10,   // frecuencia entre consultas de estado
        'max_attempts'        => 180,  // 180 * 10s = 30 min máximo
        'backoff_multiplier'  => 1.5,
        'max_backoff_seconds' => 60,
    ],

    // Rate limiting hacia SUNAT — evita saturar el servicio SIRE
    'rate_limit' => [
        'per_tenant_per_minute' => 30,
    ],

    // Almacenamiento de archivos descargados/subidos
    'storage' => [
        'disk' => env('SIRE_STORAGE_DISK', 'local'),
        'base_path' => 'sire',
    ],

    // Colas dedicadas — NO usar las colas de facturación
    'queues' => [
        'poll'    => 'sire-poll',
        'heavy'   => 'sire-heavy',
        'process' => 'sire-process',
    ],

    // Estados de proceso devueltos por SUNAT en 5.31
    // (codEstadoProceso - valores observados según manual)
    'estados_proceso' => [
        '01' => 'PENDIENTE',
        '02' => 'EN_PROCESO',
        '03' => 'PROCESANDO',
        '05' => 'TERMINADO',
        '06' => 'TERMINADO_ERRORES',
        '07' => 'ERROR',
    ],

    // Códigos de proceso (codProceso) relevantes (Anexo I)
    // Solo incluimos los que usaremos desde la API
    'cod_proceso' => [
        'aceptar_propuesta'         => '2',
        'importar_cp_preliminar'    => '4',
        'cargar_no_domiciliados'    => '56',
        'complementar_propuesta'    => '54',
        'incluir_excluir_cp'        => '55',
        'reemplazar_propuesta'      => '61',
        'generar_export_propuesta'  => '10',
        'generar_export_preliminar' => '12',
        'descargar_rce'             => '70',
        'descargar_consolidado_rce' => '69',
        'constancia_recepcion_rce'  => '46',
    ],

    // Tipos de archivo para descarga (Anexo III)
    'cod_tipo_archivo' => [
        'txt'   => '0',
        'csv'   => '1',
        'excel' => '2',
    ],

    // Tipos de resumen (servicio 5.35)
    'cod_tipo_resumen' => [
        'propuesta'             => '1',
        'preliminar'            => '2',
        'no_incluidos_excluidos' => '3',
        'registro'              => '4',
        'preliminar_registrado' => '5',
        'ajustes_posteriores'   => '6',
        'no_domiciliados'       => '7',
    ],

    // Tipos de ajuste posterior (Anexo II)
    'cod_tipo_ajuste' => [
        'ajuste_posterior'                      => '1',
        'ajuste_posterior_no_domiciliados'      => '2',
        'ajuste_periodos_anteriores_general'    => '3',
        'ajuste_periodos_anteriores_simplificado' => '4',
        'ajuste_periodos_anteriores_no_domiciliados' => '5',
    ],

    // Límites de archivos en uploads TUS
    'upload_limits' => [
        'max_size_bytes' => 6 * 1024 * 1024 * 1024, // 6 GB según error 1346
        'min_size_bytes' => 1,                        // > 0 KB según error 1350
        'allowed_extensions' => ['zip'],
    ],

    // Webhook — reusa tenants.webhook_url para notificar eventos SIRE
    'webhooks' => [
        'events' => [
            'ticket.completed',
            'ticket.failed',
            'propuesta.processed',
            'preliminar.registered',
            'reconciliation.completed',
        ],
    ],
];

<?php

return [

    /*
     * Rutas a las que se aplica CORS.
     * 'api/*' cubre todos los endpoints de la API REST.
     */
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
     * Orígenes permitidos.
     * En .env pon: CORS_ALLOWED_ORIGINS=https://app.zaresk.com
     * Separa varios dominios con coma: https://app.zaresk.com,https://zaresk.com
     */
    'allowed_origins' => array_filter(
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*')))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-Usage-Documents-Used',
        'X-Usage-Documents-Limit',
        'X-Usage-Percent',
        'X-Usage-Warning',
    ],

    'max_age' => 86400, // 24 horas — el browser cachea el preflight

    'supports_credentials' => false,

];

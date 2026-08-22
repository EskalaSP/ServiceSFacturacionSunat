<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Licencia de uso de este sistema
    |--------------------------------------------------------------------------
    |
    | Este sistema valida su licencia contra el servidor central del proveedor
    | antes de emitir comprobantes a SUNAT en PRODUCCIÓN. El entorno 'beta'
    | (pruebas) nunca se bloquea: puedes probar aunque no tengas licencia.
    |
    | Las credenciales (LICENSE_KEY / LICENSE_SECRET) las entrega el proveedor
    | al comprar. Van atadas al dominio de esta instalación.
    */

    // Interruptor general. Ponlo en false solo en desarrollo local.
    'enabled' => (bool) env('LICENSE_ENABLED', true),

    // Servidor central de licencias del proveedor.
    'server_url' => rtrim((string) env('LICENSE_SERVER_URL', 'https://agenda.kodevo.es'), '/'),

    // Credenciales de la licencia (entregadas por el proveedor).
    'key' => env('LICENSE_KEY'),
    'secret' => env('LICENSE_SECRET'),

    // Dominio de esta instalación. Debe coincidir con el dominio autorizado.
    'domain' => env('LICENSE_DOMAIN', env('APP_URL', 'http://localhost')),

    // Versión de la app que se reporta al servidor (telemetría).
    'app_version' => env('LICENSE_APP_VERSION', '1.0.0'),

    // Segundos que se cachea una validación exitosa (evita llamar al servidor
    // en cada emisión). Por defecto 1 hora.
    'cache_ttl' => (int) env('LICENSE_CACHE_TTL', 3600),

    // Días que el sistema sigue operando si el servidor de licencias está caído,
    // contados desde la última validación exitosa. Evita que una caída del
    // proveedor detenga la facturación del cliente.
    'grace_days' => (int) env('LICENSE_GRACE_DAYS', 7),

    // Timeout de la llamada HTTP al servidor de licencias (segundos).
    'timeout' => (int) env('LICENSE_TIMEOUT', 8),

];

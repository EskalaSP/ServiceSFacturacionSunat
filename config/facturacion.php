<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Almacenamiento de archivos
    |--------------------------------------------------------------------------
    |
    | Estructura:
    |   storage/app/private/certificates/{ruc}/cert.pem        ← certificados (privado)
    |   storage/app/public/{ruc}/{cod_local}/{fecha}/xml/      ← XMLs firmados
    |   storage/app/public/{ruc}/{cod_local}/{fecha}/cdr/      ← CDRs de SUNAT
    |   storage/app/public/{ruc}/{cod_local}/{fecha}/pdf/      ← PDFs generados
    */
    'storage' => [
        'certificates_disk' => 'local',        // storage/app/private/
        'documents_disk' => 'public',          // storage/app/public/
    ],

    /*
    |--------------------------------------------------------------------------
    | Throughput / equidad multi-tenant
    |--------------------------------------------------------------------------
    |
    | Máx. de comprobantes por minuto que un mismo RUC puede enviar a SUNAT
    | desde la cola. Protege a los demás tenants de un cliente que sube miles
    | de golpe (el exceso se re-libera y reintenta, no se pierde). Ajusta según
    | lo que SUNAT tolere por RUC. Usado por el rate limiter 'sunat-tenant'.
    */
    'throughput' => [
        'per_tenant_per_minute' => (int) env('SUNAT_PER_TENANT_PER_MINUTE', 120),
    ],

    'sunat' => [
        'beta' => [
            'fe' => 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService',
            'retention' => 'https://e-beta.sunat.gob.pe/ol-ti-itemision-otroscpe-gem-beta/billService',
            'guias_auth' => env('SUNAT_BETA_GRE_AUTH', 'https://gre-test.nubefact.com/v1'),
            'guias_cpe' => env('SUNAT_BETA_GRE_CPE', 'https://gre-test.nubefact.com/v1'),
            'gre_client_id' => env('SUNAT_BETA_GRE_CLIENT_ID', 'test-85e5b0ae-255c-4891-a595-0b98c65c9854'),
            'gre_client_secret' => env('SUNAT_BETA_GRE_CLIENT_SECRET', 'test-Hty/M6QshYvPgItX2P0+Kw=='),
            'consulta_cdr' => 'https://e-beta.sunat.gob.pe/ol-it-wsconscpegem-beta/billConsultService',
        ],
        'production' => [
            'fe' => 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',
            'retention' => 'https://e-factura.sunat.gob.pe/ol-ti-itemision-otroscpe-gem/billService',
            // El token OAuth2 se pide en api-seguridad; el comprobante se envía a api-cpe.
            // Son hosts DISTINTOS en SUNAT producción (en beta el proxy nubefact unifica ambos).
            'guias_auth' => env('SUNAT_GRE_AUTH', 'https://api-seguridad.sunat.gob.pe/v1'),
            'guias_cpe' => env('SUNAT_GRE_CPE', 'https://api-cpe.sunat.gob.pe/v1'),
            'gre_client_id' => env('SUNAT_GRE_CLIENT_ID', ''),
            'gre_client_secret' => env('SUNAT_GRE_CLIENT_SECRET', ''),
            'consulta_cdr' => 'https://e-factura.sunat.gob.pe/ol-it-wsconscpegem/billConsultService',
        ],
    ],

    'certificate' => [
        'path'         => env('CERTIFICATE_PATH', ''),
        'password'     => env('CERTIFICATE_PASSWORD', ''),
        'pem_b64'      => env('CERTIFICATE_PEM_B64', ''),
        'pse_ruc'      => env('SUNAT_RUC_PSE', ''),
        'pse_razon_social' => env('SUNAT_RAZON_SOCIAL_PSE', ''),
    ],

    'lookup' => [
        'token' => env('APIS_NET_PE_TOKEN', env('LOOKUP_API_TOKEN', '')),
        'base_url' => 'https://api.apis.net.pe',
    ],

    // Plan limits are now managed via PlanService + plans DB table.
    // See PlanSeeder for current plan definitions.

];

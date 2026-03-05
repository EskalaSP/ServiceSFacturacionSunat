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
            'guias_auth' => 'https://api-cpe.sunat.gob.pe/v1',
            'guias_cpe' => 'https://api-cpe.sunat.gob.pe/v1',
            'consulta_cdr' => 'https://e-factura.sunat.gob.pe/ol-it-wsconscpegem/billConsultService',
        ],
    ],

    'plans' => [
        'free' => ['max_documents' => 20],
        'pro' => ['max_documents' => 500],
        'business' => ['max_documents' => 99999],
    ],

];

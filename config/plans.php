<?php

/*
|--------------------------------------------------------------------------
| Catálogo de límites y features de planes
|--------------------------------------------------------------------------
|
| Fuente única de verdad para las claves conocidas que se pueden configurar
| en un plan. El panel /admin/planes lee de aquí para presentar labels
| amigables y agrupaciones. El admin puede agregar claves personalizadas
| que no estén listadas aquí — quedarán guardadas en el JSON del plan
| igual, sólo sin label predefinida.
|
| Convención:
|   - Los `limits` son numéricos. `-1` = ilimitado.
|   - Los `features` son flags booleanos (existe la key en `plan.features[]`).
|
*/

return [

    'limits' => [

        'SUNAT' => [
            'documents_month' => [
                'label' => 'Documentos SUNAT por mes',
                'help'  => 'Facturas, boletas, notas, guías, retenciones, percepciones, anulaciones.',
            ],
            'internal_documents_month' => [
                'label' => 'Documentos internos por mes',
                'help'  => 'Cotizaciones y notas de venta (no van a SUNAT).',
            ],
        ],

        'Uso general' => [
            'ai_messages_month' => [
                'label' => 'Mensajes de IA por mes',
                'help'  => 'Consultas al asistente de IA.',
            ],
            'team_members' => [
                'label' => 'Miembros del equipo',
            ],
            'sucursales' => [
                'label' => 'Sucursales',
            ],
            'productos' => [
                'label' => 'Productos en catálogo',
            ],
        ],

        'Marketplace y B2B' => [
            'catalog_listings' => [
                'label' => 'Listings en el marketplace',
            ],
            'rfqs_month' => [
                'label' => 'Cotizaciones recibidas por mes',
            ],
            'b2b_requests_month' => [
                'label' => 'Solicitudes B2B por mes',
            ],
            'reviews_month' => [
                'label' => 'Reseñas por mes',
            ],
            'feed_posts_month' => [
                'label' => 'Publicaciones en el feed por mes',
            ],
        ],

    ],

    'features' => [

        'Emisión SUNAT' => [
            'facturacion'      => 'Emisión de facturas',
            'boletas'          => 'Emisión de boletas + resumen diario',
            'notas'            => 'Notas de crédito y débito',
            'guias_remision'   => 'Guías de remisión (GRR/GRT)',
            'retenciones'      => 'Comprobantes de retención',
            'percepciones'     => 'Comprobantes de percepción',
            'anulaciones'      => 'Comunicaciones de baja',
            'sire'             => 'SIRE - Registro de Compras',
        ],

        'Documentos internos' => [
            'cotizaciones'    => 'Cotizaciones',
            'notas_venta'     => 'Notas de venta',
            'pagos'           => 'Registro de pagos',
        ],

        'Operaciones' => [
            'inventario_basico'   => 'Inventario básico',
            'inventario_avanzado' => 'Inventario avanzado (lotes, series, kardex)',
            'compras'             => 'Compras y proveedores',
            'produccion'          => 'Producción (BOM)',
            'contratos'           => 'Contratos',
            'citas'               => 'Gestión de citas',
            'crm'                 => 'CRM y pipeline de ventas',
            'rrhh'                => 'Recursos humanos',
            'finanzas'            => 'Finanzas avanzadas',
        ],

        'Reportes y datos' => [
            'reportes_basicos'   => 'Reportes básicos',
            'reportes_avanzados' => 'Reportes avanzados',
            'audit_logs'         => 'Auditoría de acciones',
            'export_zip'         => 'Exportación masiva ZIP',
        ],

        'Marketplace' => [
            'marketplace_browse'             => 'Navegar el marketplace',
            'marketplace_advanced'           => 'Marketplace avanzado',
            'marketplace_unlimited'          => 'Marketplace ilimitado',
            'marketplace_promoted_listings'  => 'Listings promocionados',
        ],

        'B2B' => [
            'b2b_basic'      => 'Solicitudes B2B básicas',
            'b2b_invoicing'  => 'Facturación B2B',
            'b2b_templates'  => 'Plantillas B2B',
            'b2b_unlimited'  => 'B2B ilimitado',
        ],

        'Feed / social' => [
            'feed_view'     => 'Ver el feed',
            'feed_posts'    => 'Publicar en el feed',
            'feed_promoted' => 'Publicaciones promocionadas',
        ],

        'Integraciones' => [
            'webhooks'          => 'Webhooks',
            'whatsapp_business' => 'WhatsApp Business',
            'ai_assistant'      => 'Asistente IA',
        ],

        'Score y admin' => [
            'score_analytics'      => 'Analíticas de score',
            'score_export'         => 'Exportar score',
            'custom_roles'         => 'Roles personalizados',
            'soporte_prioritario'  => 'Soporte prioritario',
            'panel'                => 'Panel de control',
        ],

    ],

];

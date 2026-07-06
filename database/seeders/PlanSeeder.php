<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'name' => 'Gratis',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'sort_order' => 0,
                'limits' => [
                    'documents_month' => 30,
                    'internal_documents_month' => 20,
                    'ai_messages_month' => 10,
                    'team_members' => 1,
                    'sucursales' => 1,
                    'productos' => 100,
                    'catalog_listings' => 5,
                    'rfqs_month' => 1,
                    'b2b_requests_month' => 3,
                    'reviews_month' => 1,
                    'feed_posts_month' => 2,
                ],
                'features' => [
                    'facturacion',
                    'boletas',
                    'notas',
                    'guias_remision',
                    'cotizaciones',
                    'notas_venta',
                    'pagos',
                    'inventario_basico',
                    'reportes_basicos',
                    'feed_view',
                    'marketplace_browse',
                    'b2b_basic',
                ],
            ],
            [
                'slug' => 'pro',
                'name' => 'Profesional',
                'price_monthly' => 49.00,
                'price_yearly' => 490.00,
                'sort_order' => 1,
                'limits' => [
                    'documents_month' => 500,
                    'internal_documents_month' => 100,
                    'ai_messages_month' => 100,
                    'team_members' => 5,
                    'sucursales' => 3,
                    'productos' => -1,
                    'catalog_listings' => 20,
                    'rfqs_month' => 10,
                    'b2b_requests_month' => 15,
                    'reviews_month' => 10,
                    'feed_posts_month' => 20,
                ],
                'features' => [
                    // Free features
                    'facturacion',
                    'boletas',
                    'notas',
                    'guias_remision',
                    'cotizaciones',
                    'notas_venta',
                    'pagos',
                    'inventario_basico',
                    'reportes_basicos',
                    'feed_view',
                    'marketplace_browse',
                    'b2b_basic',
                    // Pro additions
                    'compras',
                    'inventario_avanzado',
                    'reportes_avanzados',
                    'crm',
                    'citas',
                    'feed_posts',
                    'marketplace_advanced',
                    'score_analytics',
                ],
            ],
            [
                'slug' => 'business',
                'name' => 'Empresarial',
                'price_monthly' => 99.00,
                'price_yearly' => 990.00,
                'sort_order' => 2,
                'limits' => [
                    'documents_month' => -1,
                    'internal_documents_month' => -1,
                    'ai_messages_month' => -1,
                    'team_members' => 15,
                    'sucursales' => 10,
                    'productos' => -1,
                    'catalog_listings' => -1,
                    'rfqs_month' => -1,
                    'b2b_requests_month' => -1,
                    'reviews_month' => -1,
                    'feed_posts_month' => -1,
                ],
                'features' => [
                    // Pro features
                    'facturacion',
                    'boletas',
                    'notas',
                    'guias_remision',
                    'cotizaciones',
                    'notas_venta',
                    'pagos',
                    'inventario_basico',
                    'reportes_basicos',
                    'feed_view',
                    'marketplace_browse',
                    'b2b_basic',
                    'compras',
                    'inventario_avanzado',
                    'reportes_avanzados',
                    'crm',
                    'citas',
                    'feed_posts',
                    'marketplace_advanced',
                    'score_analytics',
                    // Business additions
                    'finanzas',
                    'rrhh',
                    'contratos',
                    'produccion',
                    'whatsapp_business',
                    'custom_roles',
                    'audit_logs',
                    'soporte_prioritario',
                    'b2b_invoicing',
                    'b2b_unlimited',
                    'b2b_templates',
                    'feed_promoted',
                    'marketplace_unlimited',
                    'marketplace_promoted_listings',
                    'score_export',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        // Deactivate old plans that no longer apply
        Plan::whereIn('slug', ['starter', 'growth'])->update(['is_active' => false]);
    }
}

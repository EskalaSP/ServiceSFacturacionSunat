<?php

namespace App\Http\Middleware;

use App\Services\Plan\PlanService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimit
{
    private const FEATURE_LABELS = [
        'crm' => 'CRM y pipeline de ventas',
        'citas' => 'Gestión de citas',
        'contratos' => 'Contratos',
        'produccion' => 'Producción (BOM)',
        'compras' => 'Compras y proveedores',
        'finanzas' => 'Finanzas avanzadas',
        'rrhh' => 'Recursos humanos',
        'inventario_avanzado' => 'Inventario avanzado',
        'reportes_avanzados' => 'Reportes avanzados',
        'whatsapp_business' => 'WhatsApp Business',
        'custom_roles' => 'Roles personalizados',
        'audit_logs' => 'Auditoría de acciones',
        'soporte_prioritario' => 'Soporte prioritario',
        'feed_posts' => 'Publicaciones en el feed',
        'feed_promoted' => 'Publicaciones promocionadas',
        'marketplace_advanced' => 'Marketplace avanzado',
        'marketplace_unlimited' => 'Marketplace ilimitado',
        'marketplace_promoted_listings' => 'Listings promocionados',
        'b2b_invoicing' => 'Facturación B2B',
        'b2b_unlimited' => 'B2B ilimitado',
        'b2b_templates' => 'Plantillas B2B',
        'score_analytics' => 'Analíticas de score',
        'score_export' => 'Exportar score',
    ];

    private const USAGE_LABELS = [
        'ai_messages_month' => 'mensajes de IA',
        'team_members' => 'miembros de equipo',
        'sucursales' => 'sucursales',
        'productos' => 'productos',
        'catalog_listings' => 'listings en catálogo',
        'rfqs_month' => 'cotizaciones recibidas',
        'b2b_requests_month' => 'solicitudes B2B',
        'reviews_month' => 'reseñas',
        'feed_posts_month' => 'publicaciones en el feed',
    ];

    public function __construct(
        private PlanService $planService,
    ) {}

    /**
     * Maneja las verificaciones de límite del plan.
     *
     * Uso en rutas:
     *   ->middleware('plan:feature:crm')           — restringe una funcionalidad
     *   ->middleware('plan:usage:ai_messages_month') — verifica límite de uso
     */
    public function handle(Request $request, Closure $next, string $type, string $key): Response
    {
        $tenant = $request->get('tenant');

        if (! $tenant) {
            return $next($request);
        }

        return match ($type) {
            'feature' => $this->checkFeature($request, $next, $tenant, $key),
            'usage' => $this->checkUsage($request, $next, $tenant, $key),
            default => $next($request),
        };
    }

    private function checkFeature(Request $request, Closure $next, $tenant, string $feature): Response
    {
        if (! $this->planService->canUseFeature($tenant, $feature)) {
            $label = self::FEATURE_LABELS[$feature] ?? $feature;
            $upgrade = $this->planService->getNextPlanFor($tenant, $feature, 'feature');

            $price = $upgrade['price'] ?? 39;

            return response()->json([
                'estado' => 'error',
                'mensaje' => "Desbloquea {$label} por S/{$price} al mes.",
                'codigo_error' => 'caracteristica_no_disponible',
                'caracteristica' => $feature,
                'mejora_plan' => $upgrade,
            ], 403);
        }

        return $next($request);
    }

    private function checkUsage(Request $request, Closure $next, $tenant, string $limitKey): Response
    {
        $check = $this->planService->checkUsageLimit($tenant, $limitKey);

        if (! $check['allowed']) {
            $label = self::USAGE_LABELS[$limitKey] ?? $limitKey;
            $upgrade = $this->planService->getNextPlanFor($tenant, $limitKey, 'usage');

            $price = $upgrade['price'] ?? 39;

            return response()->json([
                'estado' => 'error',
                'mensaje' => "Has alcanzado el límite de {$label}. Más por S/{$price}/mes.",
                'codigo_error' => 'limite_alcanzado',
                'recurso' => $limitKey,
                'actual' => $check['current'],
                'limite' => $check['limit'],
                'mejora_plan' => $upgrade,
            ], 429);
        }

        return $next($request);
    }
}

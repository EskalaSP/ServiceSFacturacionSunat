<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\Plan\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(
        private PlanService $planService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/planes/index', [
            'planes' => Plan::orderBy('sort_order')->get()->map(fn (Plan $p) => [
                'id'              => $p->id,
                'slug'            => $p->slug,
                'name'            => $p->name,
                'price_monthly'   => (float) $p->price_monthly,
                'price_yearly'    => $p->price_yearly ? (float) $p->price_yearly : null,
                'documents_month' => (int) $p->getLimit('documents_month', 0),
                'is_unlimited'    => $p->isUnlimited(),
                'duration_days'   => $p->duration_days,
                'features_count'  => count($p->features ?? []),
                'sort_order'      => (int) $p->sort_order,
                'is_active'       => (bool) $p->is_active,
                'subscriptions_count' => $p->subscriptions()->count(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/planes/form', [
            'plan' => [
                'id'            => null,
                'slug'          => '',
                'name'          => '',
                'price_monthly' => 0,
                'price_yearly'  => null,
                'sort_order'    => (Plan::max('sort_order') ?? 0) + 1,
                'limits'        => (object) [],
                'features'      => [],
                'is_unlimited'  => false,
                'duration_days' => null,
                'is_active'     => true,
            ],
            'catalogo' => $this->catalogo(),
            'modo'     => 'crear',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $plan = Plan::create($data);

        $this->limpiarCachePlanes();

        return redirect()->route('admin.planes.index')
            ->with('success', "Plan «{$plan->name}» creado.");
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('admin/planes/form', [
            'plan' => [
                'id'            => $plan->id,
                'slug'          => $plan->slug,
                'name'          => $plan->name,
                'price_monthly' => (float) $plan->price_monthly,
                'price_yearly'  => $plan->price_yearly ? (float) $plan->price_yearly : null,
                'sort_order'    => (int) $plan->sort_order,
                'limits'        => (object) ($plan->limits ?? []),
                'features'      => $plan->features ?? [],
                'is_unlimited'  => (bool) $plan->is_unlimited,
                'duration_days' => $plan->duration_days,
                'is_active'     => (bool) $plan->is_active,
                'subscriptions_count' => $plan->subscriptions()->count(),
            ],
            'catalogo' => $this->catalogo(),
            'modo'     => 'editar',
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $this->validar($request, $plan->id);
        $plan->update($data);

        $this->limpiarCachePlanes();

        return redirect()->route('admin.planes.index')
            ->with('success', "Plan «{$plan->name}» actualizado.");
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return back()->with('error', 'No se puede eliminar: hay suscripciones asociadas. Desactívalo en su lugar.');
        }

        $nombre = $plan->name;
        $plan->delete();

        $this->limpiarCachePlanes();

        return redirect()->route('admin.planes.index')
            ->with('success', "Plan «{$nombre}» eliminado.");
    }

    public function toggle(Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        $this->limpiarCachePlanes();

        return back()->with('success', "Plan «{$plan->name}» " . ($plan->is_active ? 'activado' : 'desactivado') . '.');
    }

    /**
     * Devuelve el catálogo de limits/features conocidos con labels y agrupaciones.
     * Se lee desde config/plans.php — para agregar una key nueva sin tocar código
     * el admin puede usar "personalizado" en el form.
     */
    private function catalogo(): array
    {
        return [
            'limits'   => config('plans.limits', []),
            'features' => config('plans.features', []),
        ];
    }

    private function validar(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'slug' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/',
                'unique:plans,slug' . ($ignoreId ? ",{$ignoreId}" : ''),
            ],
            'name'          => 'required|string|max:100',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly'  => 'nullable|numeric|min:0',
            'sort_order'    => 'nullable|integer|min:0',

            // Limits: cualquier key alfanumérica_snake, valor entero >= -1
            'limits'        => 'nullable|array',
            'limits.*'      => 'nullable|integer|min:-1',

            // Features: cualquier string snake_case
            'features'      => 'nullable|array',
            'features.*'    => 'string|max:50|regex:/^[a-z0-9_]+$/',

            // Emisión ilimitada explícita y vigencia (vencimiento) del plan.
            'is_unlimited'  => 'nullable|boolean',
            'duration_days' => 'nullable|integer|min:1|max:3650',

            'is_active'     => 'nullable|boolean',
        ], [
            'slug.regex'      => 'El slug solo puede contener minúsculas, números, guiones y guiones bajos.',
            'features.*.regex' => 'Cada feature debe ser snake_case (minúsculas, números y guión bajo).',
            'limits.*.min'    => 'El límite debe ser -1 (ilimitado) o un número positivo.',
        ]);

        $data['is_active']    = $request->boolean('is_active');
        $data['is_unlimited'] = $request->boolean('is_unlimited');
        $data['sort_order']   = $data['sort_order'] ?? 0;
        $data['duration_days'] = $data['duration_days'] ?? null;

        // Validar keys de limits (evita basura o inyección de claves con caracteres raros)
        $data['limits'] = collect($data['limits'] ?? [])
            ->filter(fn ($v, $k) => $v !== null && $v !== '' && preg_match('/^[a-z0-9_]+$/', (string) $k))
            ->map(fn ($v) => (int) $v)
            ->all();

        // Coherencia: un plan ilimitado fuerza el sentinel -1 en los cupos de
        // comprobantes, para que cualquier consumidor del JSON (dashboards,
        // max_documents_month del tenant) lea "ilimitado" de forma uniforme.
        if ($data['is_unlimited']) {
            $data['limits']['documents_month'] = -1;
            $data['limits']['internal_documents_month'] = -1;
        }

        $data['features'] = array_values(array_unique($data['features'] ?? []));

        return $data;
    }

    private function limpiarCachePlanes(): void
    {
        // Los tenants cachean su activePlan por 5 min. Al tocar planes,
        // limpiamos solo esa key en cada tenant — no tocamos el resto del cache.
        \App\Models\Tenant::query()->pluck('id')->each(function ($id) {
            Cache::forget("tenant:{$id}:active_plan");
        });
    }
}

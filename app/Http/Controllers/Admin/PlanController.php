<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    private const LIMIT_KEYS = ['documents_month', 'sucursales', 'team', 'productos', 'ai_messages'];

    private const FEATURE_KEYS = ['sunat', 'boletas', 'notas', 'guias', 'retenciones', 'percepciones', 'sire', 'webhooks', 'panel', 'reportes', 'export_zip', 'ai_assistant'];

    public function index(): Response
    {
        return Inertia::render('admin/planes/index', [
            'planes' => Plan::orderBy('sort_order')->get()->map(fn (Plan $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'price_monthly' => (float) $p->price_monthly,
                'price_yearly' => $p->price_yearly ? (float) $p->price_yearly : null,
                'documents_month' => (int) $p->getLimit('documents_month', 0),
                'features_count' => count($p->features ?? []),
                'sort_order' => (int) $p->sort_order,
                'is_active' => (bool) $p->is_active,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/planes/form', [
            'plan' => [
                'id' => null,
                'slug' => '',
                'name' => '',
                'price_monthly' => 0,
                'price_yearly' => null,
                'sort_order' => (Plan::max('sort_order') ?? 0) + 1,
                'limits' => (object) [],
                'features' => [],
                'is_active' => true,
            ],
            'limitKeys' => self::LIMIT_KEYS,
            'featureKeys' => self::FEATURE_KEYS,
            'modo' => 'crear',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        Plan::create($data);

        return redirect()->route('admin.planes.index')
            ->with('success', 'Plan creado.');
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('admin/planes/form', [
            'plan' => [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'name' => $plan->name,
                'price_monthly' => (float) $plan->price_monthly,
                'price_yearly' => $plan->price_yearly ? (float) $plan->price_yearly : null,
                'sort_order' => (int) $plan->sort_order,
                'limits' => (object) ($plan->limits ?? []),
                'features' => $plan->features ?? [],
                'is_active' => (bool) $plan->is_active,
            ],
            'limitKeys' => self::LIMIT_KEYS,
            'featureKeys' => self::FEATURE_KEYS,
            'modo' => 'editar',
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $this->validar($request, $plan->id);
        $plan->update($data);

        return redirect()->route('admin.planes.index')
            ->with('success', 'Plan actualizado.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return back()->with('error', 'No se puede eliminar: hay suscripciones asociadas. Desactívalo en su lugar.');
        }

        $plan->delete();

        return redirect()->route('admin.planes.index')
            ->with('success', 'Plan eliminado.');
    }

    public function toggle(Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return back()->with('success', "Plan {$plan->name} " . ($plan->is_active ? 'activado' : 'desactivado') . '.');
    }

    private function validar(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'slug' => 'required|string|max:50|regex:/^[a-z0-9_-]+$/|unique:plans,slug' . ($ignoreId ? ",{$ignoreId}" : ''),
            'name' => 'required|string|max:100',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'limits' => 'nullable|array',
            'limits.*' => 'nullable|integer|min:-1',
            'features' => 'nullable|array',
            'features.*' => 'string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['limits'] = collect($data['limits'] ?? [])->filter(fn ($v) => $v !== null && $v !== '')->all();
        $data['features'] = array_values($data['features'] ?? []);

        return $data;
    }
}

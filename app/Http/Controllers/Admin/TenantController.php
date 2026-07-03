<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TenantRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    private const CAMPOS_ARRAY = ['telefonos', 'emails', 'cuentas_bancarias', 'billeteras_digitales'];

    public function index(Request $request): Response
    {
        $query = Tenant::query()
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $b = $request->input('buscar');
                $q->where(fn ($qq) => $qq->where('ruc', 'like', "%{$b}%")
                    ->orWhere('razon_social', 'like', "%{$b}%")
                    ->orWhere('nombre_comercial', 'like', "%{$b}%"));
            })
            ->when($request->filled('plan'), fn ($q) => $q->where('plan', $request->input('plan')))
            ->when($request->filled('estado'), function ($q) use ($request) {
                $q->where('is_active', $request->input('estado') === 'activa');
            })
            ->latest();

        $paginacion = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/empresas/index', [
            'empresas' => $paginacion->through(fn (Tenant $t) => [
                'id' => $t->id,
                'ruc' => $t->ruc,
                'razon_social' => $t->razon_social,
                'nombre_comercial' => $t->nombre_comercial,
                'environment' => $t->environment,
                'tax_regime' => $t->tax_regime,
                'plan' => $t->plan,
                'is_active' => (bool) $t->is_active,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
            'filtros' => [
                'buscar' => $request->input('buscar', ''),
                'plan' => $request->input('plan', ''),
                'estado' => $request->input('estado', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/empresas/form', [
            'tenant' => $this->tenantVacio(),
            'planes' => $this->planesOpciones(),
            'usuarios' => $this->usuariosOpciones(),
            'modo' => 'crear',
        ]);
    }

    public function store(TenantRequest $request): RedirectResponse
    {
        $data = $this->prepararData($request);

        $tenant = Tenant::create($data);
        $this->guardarArchivos($request, $tenant);

        return redirect()
            ->route('admin.empresas.show', $tenant)
            ->with('success', 'Empresa registrada correctamente.')
            ->with('credenciales_nuevas', [
                'api_key' => $tenant->api_key,
                'api_secret' => $tenant->api_secret,
                'ruc' => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
            ]);
    }

    public function show(Tenant $tenant): Response
    {
        $tenant->load(['sucursales', 'series', 'user']);

        return Inertia::render('admin/empresas/show', [
            'tenant' => [
                'id' => $tenant->id,
                'ruc' => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
                'nombre_comercial' => $tenant->nombre_comercial,
                'direccion' => $tenant->direccion,
                'ubigeo' => $tenant->ubigeo,
                'departamento' => $tenant->departamento,
                'provincia' => $tenant->provincia,
                'distrito' => $tenant->distrito,
                'telefonos' => $tenant->telefonos ?? [],
                'emails' => $tenant->emails ?? [],
                'environment' => $tenant->environment,
                'tax_regime' => $tenant->tax_regime,
                'plan' => $tenant->plan,
                'max_documents_month' => (int) ($tenant->max_documents_month ?? 0),
                'is_active' => (bool) $tenant->is_active,
                'webhook_url' => $tenant->webhook_url,
                'has_certificado' => ! empty($tenant->certificate_path),
                'sire_enabled' => (bool) $tenant->sire_enabled,
                'created_at' => $tenant->created_at?->toIso8601String(),
                'sucursales_count' => $tenant->sucursales->count(),
                'series_count' => $tenant->series->count(),
                'user' => $tenant->user ? [
                    'id' => $tenant->user->id,
                    'name' => $tenant->user->name,
                    'email' => $tenant->user->email,
                ] : null,
            ],
            'credencialesNuevas' => session('credenciales_nuevas'),
        ]);
    }

    public function edit(Tenant $tenant): Response
    {
        return Inertia::render('admin/empresas/form', [
            'tenant' => [
                'id' => $tenant->id,
                'ruc' => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
                'nombre_comercial' => $tenant->nombre_comercial,
                'direccion' => $tenant->direccion,
                'ubigeo' => $tenant->ubigeo,
                'departamento' => $tenant->departamento,
                'provincia' => $tenant->provincia,
                'distrito' => $tenant->distrito,
                'telefonos' => $tenant->telefonos ?? [],
                'emails' => $tenant->emails ?? [],
                'sol_user' => $tenant->sol_user,
                'environment' => $tenant->environment,
                'sire_enabled' => (bool) $tenant->sire_enabled,
                'sire_client_id' => $tenant->sire_client_id,
                'tax_regime' => $tenant->tax_regime,
                'igv_rate_override' => $tenant->igv_rate_override,
                'nrus_categoria' => $tenant->nrus_categoria,
                'plan' => $tenant->plan,
                'max_documents_month' => (int) ($tenant->max_documents_month ?? 0),
                'webhook_url' => $tenant->webhook_url,
                'mensaje_agradecimiento' => $tenant->mensaje_agradecimiento,
                'mensaje_promocional' => $tenant->mensaje_promocional,
                'cuentas_bancarias' => $tenant->cuentas_bancarias ?? [],
                'billeteras_digitales' => $tenant->billeteras_digitales ?? [],
                'user_id' => $tenant->user_id,
                'is_active' => (bool) $tenant->is_active,
                'has_certificado' => ! empty($tenant->certificate_path),
                'has_logo' => ! empty($tenant->logo_path),
            ],
            'planes' => $this->planesOpciones(),
            'usuarios' => $this->usuariosOpciones(),
            'modo' => 'editar',
        ]);
    }

    private function tenantVacio(): array
    {
        return [
            'id' => null,
            'ruc' => '',
            'razon_social' => '',
            'nombre_comercial' => '',
            'direccion' => '',
            'ubigeo' => '',
            'departamento' => '',
            'provincia' => '',
            'distrito' => '',
            'telefonos' => [],
            'emails' => [],
            'sol_user' => 'MODDATOS',
            'environment' => 'beta',
            'sire_enabled' => false,
            'sire_client_id' => '',
            'tax_regime' => 'general',
            'igv_rate_override' => null,
            'nrus_categoria' => null,
            'plan' => 'free',
            'max_documents_month' => 20,
            'webhook_url' => '',
            'mensaje_agradecimiento' => '',
            'mensaje_promocional' => '',
            'cuentas_bancarias' => [],
            'billeteras_digitales' => [],
            'user_id' => null,
            'is_active' => true,
            'has_certificado' => false,
            'has_logo' => false,
        ];
    }

    private function planesOpciones(): array
    {
        return Plan::orderBy('sort_order')->get()->map(fn (Plan $p) => [
            'slug' => $p->slug,
            'name' => $p->name,
        ])->all();
    }

    private function usuariosOpciones(): array
    {
        return User::orderBy('name')->get(['id', 'name', 'email'])->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
        ])->all();
    }

    public function update(TenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $data = $this->prepararData($request);

        // En update, no sobrescribir sol_pass si viene vacío
        if (empty($data['sol_pass'] ?? null)) {
            unset($data['sol_pass']);
        }
        if (empty($data['sire_client_secret'] ?? null)) {
            unset($data['sire_client_secret']);
        }

        $tenant->update($data);
        $this->guardarArchivos($request, $tenant);

        return redirect()->route('admin.empresas.show', $tenant)
            ->with('success', 'Empresa actualizada.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        return redirect()->route('admin.empresas.index')
            ->with('success', 'Empresa eliminada (soft delete).');
    }

    public function toggle(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['is_active' => ! $tenant->is_active]);

        return back()->with('success', 'Estado de la empresa actualizado.');
    }

    public function regenerarCredenciales(Tenant $tenant): RedirectResponse
    {
        $tenant->update([
            'api_key' => Str::random(64),
            'api_secret' => hash('sha256', Str::random(64)),
        ]);

        return redirect()->route('admin.empresas.show', $tenant)
            ->with('success', 'Credenciales regeneradas. Guárdalas ahora — no se mostrarán de nuevo.')
            ->with('credenciales_nuevas', [
                'api_key' => $tenant->api_key,
                'api_secret' => $tenant->api_secret,
                'ruc' => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
            ]);
    }

    private function prepararData(TenantRequest $request): array
    {
        $data = $request->validated();

        // Normaliza campos array (evita nulls / entradas vacías)
        foreach (self::CAMPOS_ARRAY as $campo) {
            if (! isset($data[$campo])) {
                continue;
            }
            $data[$campo] = collect($data[$campo])
                ->filter(fn ($v) => is_array($v) ? ! empty(array_filter($v)) : ! empty($v))
                ->values()
                ->all();
            if (empty($data[$campo])) {
                $data[$campo] = null;
            }
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['sire_enabled'] = $request->boolean('sire_enabled');

        // El archivo de certificado se maneja aparte
        unset($data['certificado'], $data['logo']);

        return $data;
    }

    private function guardarArchivos(TenantRequest $request, Tenant $tenant): void
    {
        if ($request->hasFile('certificado')) {
            $path = $request->file('certificado')->store("tenants/{$tenant->id}/certs", 'local');
            $update = ['certificate_path' => Storage::disk('local')->path($path)];
            if ($request->filled('contrasena_certificado')) {
                $update['certificate_password'] = $request->input('contrasena_certificado');
            }
            $tenant->update($update);
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store("tenants/{$tenant->id}/logos", 'public');
            $tenant->update(['logo_path' => $path]);
        }
    }
}

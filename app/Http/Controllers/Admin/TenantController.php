<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TenantRequest;
use App\Mail\TenantCredentialsMail;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plan\PlanService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TenantController extends Controller
{
    private const CAMPOS_ARRAY = ['telefonos', 'emails', 'cuentas_bancarias', 'billeteras_digitales'];

    public function index(Request $request, SettingsService $settings): Response
    {
        $query = Tenant::query()
            ->withCount(['sucursales', 'series'])
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
                'emission_mode' => $t->emission_mode ?? Tenant::EMISSION_PLAN,
                'is_active' => (bool) $t->is_active,
                'created_at' => $t->created_at?->toIso8601String(),
                'sucursales_count' => (int) $t->sucursales_count,
                'series_count' => (int) $t->series_count,
            ]),
            'filtros' => [
                'buscar' => $request->input('buscar', ''),
                'plan' => $request->input('plan', ''),
                'estado' => $request->input('estado', ''),
            ],
            'emisionGlobalIlimitada' => $settings->bool(SettingsService::EMISION_ILIMITADA_GLOBAL),
        ]);
    }

    public function create(SettingsService $settings): Response
    {
        // El default del selector "Modo de emisión" respeta la config global:
        // si las empresas nuevas nacen ilimitadas, el form arranca en 'unlimited'.
        $defaultUnlimited = $settings->bool(SettingsService::NUEVAS_EMPRESAS_ILIMITADAS);

        return Inertia::render('admin/empresas/form', [
            'tenant' => $this->tenantVacio($defaultUnlimited),
            'planes' => $this->planesOpciones(),
            'usuarios' => $this->usuariosOpciones(),
            'modo' => 'crear',
            'emisionGlobalIlimitada' => $settings->bool(SettingsService::EMISION_ILIMITADA_GLOBAL),
        ]);
    }

    public function store(TenantRequest $request, PlanService $planService, SettingsService $settings): RedirectResponse
    {
        $data = $this->prepararData($request);

        // Modo de emisión: si no vino en el form, se decide por la config global.
        $data['emission_mode'] = $data['emission_mode']
            ?? ($settings->bool(SettingsService::NUEVAS_EMPRESAS_ILIMITADAS)
                ? Tenant::EMISSION_UNLIMITED
                : Tenant::EMISSION_PLAN);

        $tenant = Tenant::create($data);
        $this->guardarArchivos($request, $tenant);

        // Vincular el plan elegido en el formulario con una Subscription real.
        // Los middlewares de límites consultan tenant->activeSubscription; sin
        // esto todo el sistema de planes queda desconectado y todo pasa como
        // ilimitado por el fallback de PlanService::buildFreePlan().
        $this->asignarPlan($tenant, $data['plan'] ?? 'free', $planService);

        // Credenciales: se muestran en el modal (con botón descargar .txt)
        // Y además se envían por correo con el .txt adjunto a los destinatarios.
        $envio = $this->enviarCredencialesPorCorreo($tenant, 'creacion');

        return redirect()
            ->route('admin.empresas.show', $tenant)
            ->with('success', $envio['ok']
                ? "Empresa registrada. Credenciales enviadas a: {$envio['destinos']}. También puedes descargarlas aquí."
                : 'Empresa registrada. ' . $envio['motivo'] . ' Descárgalas desde el recuadro.')
            ->with('credenciales_nuevas', [
                'api_key' => $tenant->api_key,
                'api_secret' => $tenant->api_secret,
                'ruc' => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
            ]);
    }

    /**
     * Crea/actualiza la suscripción activa del tenant al plan indicado.
     * Es una asignación administrativa — no hay cobro ni trial.
     */
    private function asignarPlan(Tenant $tenant, ?string $planSlug, PlanService $planService): void
    {
        $plan = Plan::where('slug', $planSlug ?: 'free')->where('is_active', true)->first()
            ?? Plan::where('slug', 'free')->where('is_active', true)->first();

        if (! $plan) {
            return;
        }

        // Cancelar cualquier suscripción activa/trial previa
        $tenant->subscriptions()
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->update(['status' => Subscription::STATUS_CANCELLED, 'cancelled_at' => now()]);

        // Vencimiento: si el plan define días de vigencia se respeta;
        // de lo contrario se mantiene el comportamiento previo (+1 año).
        $periodEnd = $plan->duration_days
            ? now()->addDays($plan->duration_days)
            : now()->addYear();

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::BILLING_MONTHLY,
            'current_period_start' => now(),
            'current_period_end' => $periodEnd,
            'payment_gateway' => 'admin',
        ]);

        $tenant->update([
            'plan' => $plan->slug,
            'max_documents_month' => (int) $plan->getLimit('documents_month', 30),
        ]);

        $planService->clearCache($tenant);
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
                'emission_mode' => $tenant->emission_mode ?? Tenant::EMISSION_PLAN,
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

    public function edit(Tenant $tenant, SettingsService $settings): Response
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
                'emission_mode' => $tenant->emission_mode ?? Tenant::EMISSION_PLAN,
                'plan' => $tenant->plan,
                'max_documents_month' => (int) ($tenant->max_documents_month ?? 0),
                'webhook_url' => $tenant->webhook_url,
                'mensaje_agradecimiento' => $tenant->mensaje_agradecimiento,
                'mensaje_promocional' => $tenant->mensaje_promocional,
                'cuentas_bancarias' => $tenant->cuentas_bancarias ?? [],
                'billeteras_digitales' => $tenant->billeteras_digitales ?? [],
                'user_id' => $tenant->user_id,
                'is_active' => (bool) $tenant->is_active,
                'emission_mode' => $tenant->emission_mode ?? Tenant::EMISSION_PLAN,
                'has_certificado' => ! empty($tenant->certificate_path),
                'has_logo' => ! empty($tenant->logo_path),
            ],
            'planes' => $this->planesOpciones(),
            'usuarios' => $this->usuariosOpciones(),
            'modo' => 'editar',
            'emisionGlobalIlimitada' => $settings->bool(SettingsService::EMISION_ILIMITADA_GLOBAL),
        ]);
    }

    private function tenantVacio(bool $emisionIlimitada = false): array
    {
        return [
            'id' => null,
            'emission_mode' => $emisionIlimitada ? Tenant::EMISSION_UNLIMITED : Tenant::EMISSION_PLAN,
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

    public function update(TenantRequest $request, Tenant $tenant, PlanService $planService): RedirectResponse
    {
        $data = $this->prepararData($request);

        // En update, no sobrescribir sol_pass si viene vacío
        if (empty($data['sol_pass'] ?? null)) {
            unset($data['sol_pass']);
        }
        if (empty($data['sire_client_secret'] ?? null)) {
            unset($data['sire_client_secret']);
        }

        $planAnterior = $tenant->activeSubscription?->plan?->slug;
        $planNuevo = $data['plan'] ?? $planAnterior;

        $tenant->update($data);
        $this->guardarArchivos($request, $tenant);
        $this->olvidarCacheTenant($tenant);

        // Si cambió el plan (o el tenant nunca tuvo suscripción), reasignar.
        if ($planNuevo && $planNuevo !== $planAnterior) {
            $this->asignarPlan($tenant->fresh(), $planNuevo, $planService);
        }

        return redirect()->route('admin.empresas.show', $tenant)
            ->with('success', 'Empresa actualizada.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->olvidarCacheTenant($tenant);
        $tenant->delete();

        return redirect()->route('admin.empresas.index')
            ->with('success', 'Empresa eliminada (soft delete).');
    }

    public function toggle(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['is_active' => ! $tenant->is_active]);

        // ResolveTenant cachea el tenant (con su is_active) por api_key durante
        // 10 min. Sin limpiar el cache, la API seguiría viendo el estado viejo:
        // al reactivar seguiría respondiendo "Empresa desactivada", y al
        // desactivar seguiría aceptando requests. Hay que invalidar aquí.
        $this->olvidarCacheTenant($tenant);

        return back()->with('success', 'Estado de la empresa actualizado.');
    }

    /**
     * Invalida el cache que usa ResolveTenant (clave por api_key) para que
     * los cambios de is_active / credenciales tengan efecto inmediato en la API.
     */
    private function olvidarCacheTenant(Tenant $tenant): void
    {
        \Illuminate\Support\Facades\Cache::forget("tenant:key:{$tenant->api_key}");
    }

    public function regenerarCredenciales(Tenant $tenant): RedirectResponse
    {
        // Invalidar cache de la clave anterior para que el middleware no siga
        // aceptando el api_key viejo hasta que expire el TTL de 10 min.
        $this->olvidarCacheTenant($tenant);

        $tenant->update([
            'api_key' => Str::random(64),
            'api_secret' => hash('sha256', Str::random(64)),
        ]);

        // Se muestran en el modal (descargar .txt) y se envían con el .txt adjunto.
        $envio = $this->enviarCredencialesPorCorreo($tenant->fresh(), 'regeneracion');

        return redirect()->route('admin.empresas.show', $tenant)
            ->with('success', $envio['ok']
                ? "Credenciales regeneradas y enviadas a: {$envio['destinos']}. También puedes descargarlas aquí."
                : 'Credenciales regeneradas. ' . $envio['motivo'] . ' Descárgalas ahora — no se mostrarán de nuevo.')
            ->with('credenciales_nuevas', [
                'api_key' => $tenant->api_key,
                'api_secret' => $tenant->api_secret,
                'ruc' => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
            ]);
    }

    /**
     * Envía el api_key + api_secret (como .txt adjunto) a los destinatarios del
     * tenant: emails del perfil + usuario vinculado. Best-effort — si el SMTP
     * falla, NO rompe la operación (las credenciales igual se muestran/descargan).
     * Devuelve ['ok' => bool, 'destinos' => 'a@b, c@d', 'motivo' => string].
     */
    private function enviarCredencialesPorCorreo(Tenant $tenant, string $motivo): array
    {
        $destinatarios = collect([
            ...($tenant->emails ?? []),
            $tenant->user?->email,
        ])->filter()
          ->map(fn ($email) => strtolower(trim((string) $email)))
          ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
          ->unique()
          ->values()
          ->all();

        if (empty($destinatarios)) {
            return [
                'ok' => false,
                'destinos' => '',
                'motivo' => 'No se envió correo: la empresa no tiene emails ni usuario vinculado.',
            ];
        }

        try {
            Mail::to($destinatarios)->send(new TenantCredentialsMail(
                $tenant,
                $tenant->api_key,
                $tenant->api_secret,
                $motivo,
            ));

            return ['ok' => true, 'destinos' => implode(', ', $destinatarios), 'motivo' => ''];
        } catch (Throwable $e) {
            Log::error('Fallo al enviar credenciales por correo', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'destinos' => '', 'motivo' => 'No se pudo enviar el correo.'];
        }
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

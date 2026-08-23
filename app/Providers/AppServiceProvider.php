<?php

namespace App\Providers;

use App\Events\DocumentCreated;
use App\Events\PaymentFailed;
use App\Events\SubscriptionCreated;
use App\Events\TrialExpiring;
use App\Listeners\IncrementDocumentUsage;
use App\Listeners\SendPaymentFailedEmail;
use App\Listeners\SendTrialEndingEmail;
use App\Listeners\SendWelcomeEmail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Plan\PlanService;
use App\Support\Rbac\Ability;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios de la aplicación.
     */
    public function register(): void
    {
        $this->app->singleton(PdfGeneratorService::class);
        $this->app->singleton(PlanService::class);
    }

    /**
     * Inicializa los servicios de la aplicación.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureEventListeners();
        $this->configureGates();
    }

    /**
     * Gates de autorización del panel según el rol del usuario.
     */
    protected function configureGates(): void
    {
        // Super admin: bypass total (todas las empresas, todas las acciones).
        Gate::before(fn (User $u) => $u->isSuperAdmin() ? true : null);

        // Gates internos del panel admin (roles predefinidos).
        Gate::define('manage-users', fn (User $u) => $u->canManageUsers());
        Gate::define('write', fn (User $u) => $u->canWrite());
        Gate::define('delete', fn (User $u) => $u->canDelete());
        Gate::define('resend', fn (User $u) => $u->canResend());

        // Gates por empresa para comprobantes: reciben (User, Tenant, tipo).
        // Uso: Gate::allows('emitir', [$tenant, 'factura']).
        foreach (Ability::ACCIONES_DOCUMENTO as $accion) {
            Gate::define($accion, fn (User $u, Tenant $t, string $tipo) => $u->puede("{$tipo}.{$accion}", $t));
        }

        // Gates por empresa para módulos transversales: reciben (User, Tenant).
        Gate::define('gestionar-clientes', fn (User $u, Tenant $t) => $u->puede(Ability::CLIENTE_GESTIONAR, $t));
        Gate::define('gestionar-series', fn (User $u, Tenant $t) => $u->puede(Ability::SERIE_GESTIONAR, $t));
        Gate::define('gestionar-sucursales', fn (User $u, Tenant $t) => $u->puede(Ability::SUCURSAL_GESTIONAR, $t));
        Gate::define('ver-reportes', fn (User $u, Tenant $t) => $u->puede(Ability::REPORTE_VER, $t));
        Gate::define('exportar', fn (User $u, Tenant $t) => $u->puede(Ability::EXPORTAR, $t));
        Gate::define('consultar-cpe', fn (User $u, Tenant $t) => $u->puede(Ability::CONSULTA_CPE, $t));
        Gate::define('editar-empresa', fn (User $u, Tenant $t) => $u->puede(Ability::CONFIG_EDITAR, $t));
        Gate::define('ver-apikey', fn (User $u, Tenant $t) => $u->puede(Ability::APIKEY_VER, $t));
        Gate::define('gestionar-equipo', fn (User $u, Tenant $t) => $u->puede(Ability::EQUIPO_GESTIONAR, $t));
        Gate::define('gestionar-sire', fn (User $u, Tenant $t) => $u->puede(Ability::SIRE_GESTIONAR, $t));
    }

    protected function configureEventListeners(): void
    {
        // DocumentCreated → IncrementDocumentUsage: DESCONECTADO.
        // Cada CreateXxxAction ya llama app(PlanService::class)->incrementUsage()
        // dentro de la misma transacción. Reactivar el listener causaría un
        // doble conteo (cada documento sumaría 2 al contador del tenant).
        // El evento se sigue disparando por si otro listener lo necesita.
        Event::listen(SubscriptionCreated::class, SendWelcomeEmail::class);
        Event::listen(PaymentFailed::class, SendPaymentFailedEmail::class);
        Event::listen(TrialExpiring::class, SendTrialEndingEmail::class);
    }

    /**
     * Configura los comportamientos predeterminados para aplicaciones en producción.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // Omitir límite de velocidad para peticiones internas entre servidores
            $internalToken = config('services.internal_token');
            if ($internalToken && $request->header('X-Internal-Token') === $internalToken) {
                return Limit::none();
            }

            $tenant = $request->get('tenant');
            $limit = match ($tenant?->plan ?? 'free') {
                'free' => 30,
                'pro' => 120,
                'business' => 300,
                default => 30,
            };

            return Limit::perMinute($limit)->by($tenant?->id ?? $request->ip());
        });

        // Rate limiter para jobs que llaman SUNAT SIRE (por tenant).
        // Evita saturar a SUNAT cuando muchos jobs del mismo tenant corren en paralelo.
        RateLimiter::for('sunat-sire', function ($job) {
            $tenantId = $job->ticket?->tenant_id
                ?? $job->tenantId
                ?? (method_exists($job, 'tenantId') ? $job->tenantId() : 'global');

            return Limit::perMinute(config('sire.rate_limit.per_tenant_per_minute', 30))
                ->by("sire:{$tenantId}");
        });

        // Equidad multi-tenant para el envío de comprobantes (facturas/boletas/notas).
        // Cada RUC drena a su propio ritmo → un cliente masivo no ahoga a los demás.
        // Si se excede el límite, el job se re-libera y reintenta (no se pierde).
        RateLimiter::for('sunat-tenant', function ($job) {
            $tenantId = method_exists($job, 'tenantId') ? $job->tenantId() : 'global';

            return Limit::perMinute(config('facturacion.throughput.per_tenant_per_minute', 120))
                ->by("sunat:{$tenantId}");
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

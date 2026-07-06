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
use App\Services\Plan\PlanService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\Pdf\PdfGeneratorService;
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

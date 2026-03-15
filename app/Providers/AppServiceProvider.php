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
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PdfGeneratorService::class);
        $this->app->singleton(PlanService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureEventListeners();
    }

    protected function configureEventListeners(): void
    {
        Event::listen(DocumentCreated::class, IncrementDocumentUsage::class);
        Event::listen(SubscriptionCreated::class, SendWelcomeEmail::class);
        Event::listen(PaymentFailed::class, SendPaymentFailedEmail::class);
        Event::listen(TrialExpiring::class, SendTrialEndingEmail::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $tenant = $request->get('tenant');
            $limit = match ($tenant?->plan ?? 'free') {
                'free' => 30,
                'pro' => 120,
                'business' => 300,
                default => 30,
            };

            return Limit::perMinute($limit)->by($tenant?->id ?? $request->ip());
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

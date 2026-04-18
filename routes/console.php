<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Crear particiones para los próximos 3 meses (mensual, día 1 a las 2:00 AM)
Schedule::command('partitions:create --months=3')->monthlyOn(1, '02:00');

// Purgar api_logs con más de 90 días (semanal, domingos a las 3:00 AM)
Schedule::command('logs:purge --days=90')->weeklyOn(0, '03:00');

// === Suscripciones ===
// Procesar pagos recurrentes vencidos (diario a las 6:00 AM)
Schedule::job(new \App\Jobs\ProcessRecurringPayments)->dailyAt('06:00');

// Verificar trials vencidos y notificar trials por vencer (diario a las 7:00 AM)
Schedule::job(new \App\Jobs\CheckTrialExpiration)->dailyAt('07:00');

// Resetear contadores mensuales de uso (1ro de cada mes a las 00:05 AM)
Schedule::job(new \App\Jobs\ResetMonthlyUsage)->monthlyOn(1, '00:05');

// === SIRE ===
// Poolear tickets pendientes cada minuto (idempotente, usa withoutOverlapping)
Schedule::command('sire:poll-pending')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Reconciliar todos los tenants activos (diario 03:00 AM)
Schedule::command('sire:reconcile-all')
    ->dailyAt('03:00')
    ->withoutOverlapping();

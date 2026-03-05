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

<?php

/**
 * cron-jobs.php — Runner único para hosting compartido (Hostinger, cPanel, etc).
 *
 * Reemplaza al daemon `php artisan queue:work` (que requiere acceso SSH y
 * procesos persistentes). Diseñado para ejecutarse cada minuto vía cron.
 *
 * En cada ejecución hace DOS cosas:
 *   1. `schedule:run` — dispara las tareas programadas en routes/console.php
 *      (sire:poll-pending, ProcessRecurringPayments, CheckTrialExpiration,
 *      logs:purge, etc.).
 *   2. `queue:work --stop-when-empty` — procesa todos los jobs pendientes
 *      en la cola (envíos a SUNAT, webhooks, etc.) y termina cuando se vacía
 *      o cuando se alcanza el timeout.
 *
 * Configuración cron (Hostinger / cPanel):
 *
 *   * * * * * /usr/bin/php /home/USER/domains/TUDOMINIO/public_html/cron-jobs.php >> /dev/null 2>&1
 *
 * (Reemplaza /usr/bin/php por la ruta real de PHP en tu hosting — suele ser
 * /usr/local/bin/php8.2 o similar).
 *
 * Logs:
 *   - storage/logs/cron-jobs.log       (actividad / errores no-críticos)
 *   - storage/logs/cron-jobs-error.log (excepciones críticas)
 *
 * Lock anti-overlap: si la corrida anterior aún no terminó, esta sale
 * silenciosamente (evita que dos workers procesen el mismo job).
 */

declare(strict_types=1);

// ============================================================================
// CONFIGURACIÓN
// ============================================================================

const QUEUE_NAME           = 'default';   // Cola que usa la API (config/queue.php)
const MAX_EXECUTION_TIME   = 55;          // segundos (deja margen antes del próximo cron)
const QUEUE_MAX_TIME       = 45;          // segundos máx para queue:work
const QUEUE_SLEEP          = 3;           // segundos entre polls cuando no hay jobs
const QUEUE_TIMEOUT        = 30;          // segundos máx por job individual
const QUEUE_TRIES          = 3;           // reintentos por job (los Jobs definen su propio backoff)
const LOCK_STALE_AFTER     = 70;          // segundos — locks más viejos se consideran obsoletos

$basePath = __DIR__;
$lockFile = $basePath . '/storage/framework/cron-jobs.lock';
$logFile  = $basePath . '/storage/logs/cron-jobs.log';
$errLog   = $basePath . '/storage/logs/cron-jobs-error.log';

// ============================================================================
// LOCK ANTI-OVERLAP
// ============================================================================

if (file_exists($lockFile)) {
    $age = time() - (int) filemtime($lockFile);
    if ($age < LOCK_STALE_AFTER) {
        // Otra ejecución está en curso — salir sin hacer nada
        exit(0);
    }
    @unlink($lockFile);
}

@mkdir(dirname($lockFile), 0775, true);
file_put_contents($lockFile, (string) getmypid());

// Limpiar lock al terminar (incluso en caso de fatal error)
register_shutdown_function(function () use ($lockFile) {
    @unlink($lockFile);
});

// ============================================================================
// EJECUCIÓN
// ============================================================================

try {
    require $basePath . '/vendor/autoload.php';

    /** @var Illuminate\Foundation\Application $app */
    $app = require_once $basePath . '/bootstrap/app.php';

    /** @var Illuminate\Contracts\Console\Kernel $kernel */
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    set_time_limit(MAX_EXECUTION_TIME);
    @ini_set('memory_limit', '256M');

    $startTime = microtime(true);
    $errors = [];

    // ------------------------------------------------------------------------
    // 1) SCHEDULER — dispara las tareas programadas (sire:poll-pending, etc.)
    // ------------------------------------------------------------------------

    $schedInput  = new Symfony\Component\Console\Input\ArrayInput(['command' => 'schedule:run']);
    $schedOutput = new Symfony\Component\Console\Output\BufferedOutput();
    $schedExit   = $kernel->handle($schedInput, $schedOutput);

    if ($schedExit !== 0) {
        $errors[] = "schedule:run salió con código {$schedExit}: " . trim($schedOutput->fetch());
    }

    // ------------------------------------------------------------------------
    // 2) QUEUE WORKER — procesa jobs hasta vaciar la cola o agotar el tiempo
    // ------------------------------------------------------------------------

    $elapsed   = microtime(true) - $startTime;
    $remaining = max(5, MAX_EXECUTION_TIME - (int) $elapsed - 5);
    $maxQueue  = min(QUEUE_MAX_TIME, $remaining);

    $queueInput = new Symfony\Component\Console\Input\ArrayInput([
        'command'           => 'queue:work',
        '--queue'           => QUEUE_NAME,
        '--stop-when-empty' => true,
        '--max-time'        => $maxQueue,
        '--sleep'           => QUEUE_SLEEP,
        '--tries'           => QUEUE_TRIES,
        '--timeout'         => QUEUE_TIMEOUT,
    ]);
    $queueOutput = new Symfony\Component\Console\Output\BufferedOutput();
    $queueExit   = $kernel->handle($queueInput, $queueOutput);

    if ($queueExit !== 0) {
        $errors[] = "queue:work salió con código {$queueExit}: " . trim($queueOutput->fetch());
    }

    // ------------------------------------------------------------------------
    // LOG (solo si hubo errores — no llenar disco con info de runs vacíos)
    // ------------------------------------------------------------------------

    if (!empty($errors)) {
        @mkdir(dirname($logFile), 0775, true);
        $msg = sprintf(
            "[%s] (%.1fs) %s\n",
            date('Y-m-d H:i:s'),
            microtime(true) - $startTime,
            implode(' | ', $errors)
        );
        @file_put_contents($logFile, $msg, FILE_APPEND);
    }

    $kernel->terminate($queueInput, $queueExit);
    exit($queueExit);

} catch (\Throwable $e) {
    @mkdir(dirname($errLog), 0775, true);
    $msg = sprintf(
        "[%s] %s\n  in %s:%d\n%s\n\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    @file_put_contents($errLog, $msg, FILE_APPEND);
    exit(1);
}

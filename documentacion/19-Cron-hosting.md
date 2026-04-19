# Cron jobs en hosting compartido

Esta API usa colas (`queue`) y tareas programadas (`scheduler`). En un VPS normal:

```bash
# Worker persistente
php artisan queue:work --queue=default

# Scheduler (vía cron del sistema)
* * * * * cd /var/www/api-pro && php artisan schedule:run >> /dev/null 2>&1
```

Pero en **hosting compartido** (Hostinger, cPanel, GoDaddy, etc.) NO puedes ejecutar procesos persistentes — solo tareas cron a intervalos. Para esto se incluye `cron-jobs.php` en la raíz del proyecto.

## Configuración (1 sola entrada cron)

En el panel de tu hosting (Hostinger → Avanzado → Cron Jobs), crear:

**Frecuencia:** cada minuto (`* * * * *`)

**Comando:**

```bash
/usr/bin/php /home/USER/domains/TUDOMINIO/public_html/cron-jobs.php >> /dev/null 2>&1
```

> Reemplaza `/usr/bin/php` por la ruta real de PHP en tu hosting (suele ser `/usr/local/bin/php8.2`, `/opt/cpanel/ea-php82/root/usr/bin/php`, etc.).
>
> Reemplaza `/home/USER/domains/TUDOMINIO/public_html` por la ruta absoluta de tu instalación.

## ¿Qué hace cron-jobs.php?

En cada ejecución (1 vez por minuto):

1. **`schedule:run`** — Dispara las tareas programadas en `routes/console.php`:
   - `sire:poll-pending` (cada minuto) — consulta tickets SIRE pendientes
   - `ProcessRecurringPayments` (diario 06:00) — pagos recurrentes vencidos
   - `CheckTrialExpiration` (diario 07:00) — trials por vencer
   - `ResetMonthlyUsage` (1ro de mes 00:05) — resetea contadores
   - `partitions:create --months=3` (1ro de mes 02:00) — particiones de tablas
   - `logs:purge --days=90` (domingos 03:00) — limpieza api_logs
   - `sire:reconcile-all` (diario 03:00) — reconciliación SIRE

2. **`queue:work --stop-when-empty`** — Procesa todos los jobs pendientes en la cola `default`:
   - `SendDocumentToSunat` (facturas, boletas, NC, ND)
   - `SendDispatchGuideToSunat` (guías GRR/GRT)
   - `SendSummaryToSunat` (resúmenes diarios)
   - `SendVoidedToSunat` / `SendReversionToSunat` (anulaciones)
   - `SendRetentionToSunat` / `SendPerceptionToSunat`
   - `CheckTicketStatus`, `CheckSummaryTicketStatus`, etc.
   - `NotifyWebhookJob`
   - Termina cuando la cola se vacía o al pasar 45 segundos

## Protección anti-overlap

`cron-jobs.php` usa un lock file (`storage/framework/cron-jobs.lock`) para impedir que dos ejecuciones procesen los mismos jobs en paralelo:

- Si la corrida anterior aún no terminó, la nueva sale **silenciosamente** (exit 0) en ~100 ms.
- Si el lock tiene más de 70 segundos (proceso colgado), se considera obsoleto y se elimina.

## Configuración interna

Las constantes del archivo permiten ajustar el comportamiento:

```php
const QUEUE_NAME         = 'default';   // cola a procesar
const MAX_EXECUTION_TIME = 55;          // límite global del script
const QUEUE_MAX_TIME     = 45;          // límite del queue:work
const QUEUE_SLEEP        = 3;           // segundos entre polls
const QUEUE_TIMEOUT      = 30;          // límite por job individual
const QUEUE_TRIES        = 3;           // reintentos por job
const LOCK_STALE_AFTER   = 70;          // segundos antes de considerar lock obsoleto
```

## Logs

- **`storage/logs/cron-jobs.log`** — solo errores no-críticos (jobs fallidos, schedule con errores).
- **`storage/logs/cron-jobs-error.log`** — excepciones críticas (autoloader caído, DB no responde).
- Los runs sin actividad NO escriben nada (no llena el disco en hostings con poco espacio).

## Verificación

Para confirmar que el cron está corriendo:

```bash
# Ver últimos runs (si hubo errores)
tail -20 storage/logs/cron-jobs.log

# Ver jobs pendientes en BD
php artisan tinker --execute="echo \DB::table('jobs')->count();"

# Ver jobs fallidos
php artisan queue:failed
```

Si todo está bien, los jobs creados desde tu API deben pasar de `enviado` a `aceptado` (o `rechazado`) en menos de 60 segundos.

## Migración desde un VPS

Si vienes de un VPS con `queue:work` corriendo en supervisor, basta con:

1. Detener supervisor: `sudo supervisorctl stop laravel-worker:*`
2. Quitar la entrada cron: `* * * * * php artisan schedule:run`
3. Agregar la nueva entrada cron: `* * * * * php cron-jobs.php`

No hay que cambiar nada del código de la API — los Jobs siguen siendo los mismos.

## Recomendación: ¿Cuándo usar cron-jobs.php?

| Situación | Recomendación |
|---|---|
| **Hosting compartido** (Hostinger, cPanel, GoDaddy) | ✅ Usar `cron-jobs.php` |
| **VPS sin supervisor configurado** | ✅ Usar `cron-jobs.php` (más simple) |
| **VPS con supervisor + Redis** | ❌ Usar `queue:work` daemon — más eficiente para cargas altas |
| **Servidor con > 100 jobs/minuto** | ❌ Usar daemon — el overhead del cron se nota |

Para volúmenes normales (< 50 jobs/minuto), `cron-jobs.php` es perfectamente válido.

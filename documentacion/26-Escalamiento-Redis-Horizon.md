# Escalamiento: Redis + workers + equidad multi-tenant

Guía para pasar de ~50 comprobantes/min (cola `database`, 2 workers) a **miles/min** con
justicia entre clientes. Aplica sobre el VPS ya desplegado (ver `20-Despliegue-VPS.md`).

> **Qué cambió en el código** (ya commiteado):
> - Colas dedicadas: `sunat` (envíos), `webhooks`, `mail`, `default`. Un flood de
>   facturas ya **no** atrasa correos ni webhooks.
> - Equidad por tenant: rate limiter `sunat-tenant` — cada RUC drena a su propio
>   ritmo (`SUNAT_PER_TENANT_PER_MINUTE`, default 120/min). Un cliente masivo no
>   monopoliza los workers; su exceso se re-libera y reintenta.
> - `predis/predis` agregado (cliente Redis en PHP).

---

## 1. Instalar Redis en el VPS

```bash
sudo apt update && sudo apt install -y redis-server
sudo systemctl enable --now redis-server
redis-cli ping        # → PONG
```

Recomendado en `/etc/redis/redis.conf` (evita que Redis se quede sin RAM):

```conf
maxmemory 512mb
maxmemory-policy noeviction     # NO descartar jobs de la cola
```

```bash
sudo systemctl restart redis-server
```

## 2. Configurar `.env`

```ini
# Cola en Redis (antes: database)
QUEUE_CONNECTION=redis

# Cache en Redis (necesario para que el rate limiter por tenant sea exacto y rápido)
CACHE_STORE=redis

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Equidad multi-tenant: máx comprobantes/min por RUC (ajusta según SUNAT)
SUNAT_PER_TENANT_PER_MINUTE=120
```

```bash
cd ~/plataform-api-sunat
php artisan config:clear && php artisan config:cache
```

> Los jobs que estaban en la tabla `jobs` (driver database) **no** migran solos a
> Redis. Drena la cola vieja ANTES de cambiar: `php artisan queue:work database --stop-when-empty`.

---

## 3. Workers escalados — Opción A: Supervisor (simple, sin paquete extra)

Reemplaza el worker único por **dos pools** que aíslan prioridades.
Edita `/etc/supervisor/conf.d/api-pro-worker.conf`:

```ini
; Pool 1 — ENVÍOS A SUNAT (alto volumen, baja latencia). Escala numprocs según CPU/RAM.
[program:api-pro-sunat]
process_name=%(program_name)s_%(process_num)02d
command=php /home/deploy/plataform-api-sunat/artisan queue:work redis --queue=sunat --sleep=1 --tries=20 --max-time=3600 --max-jobs=500
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=10
redirect_stderr=true
stdout_logfile=/home/deploy/plataform-api-sunat/storage/logs/worker-sunat.log
stopwaitsecs=3600

; Pool 2 — webhooks, correos y tareas varias (no compiten con SUNAT).
[program:api-pro-otros]
process_name=%(program_name)s_%(process_num)02d
command=php /home/deploy/plataform-api-sunat/artisan queue:work redis --queue=webhooks,mail,default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=3
redirect_stderr=true
stdout_logfile=/home/deploy/plataform-api-sunat/storage/logs/worker-otros.log
stopwaitsecs=120
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

**Dimensionar `numprocs`:** cada worker = ~1 conexión concurrente a SUNAT y ~40-80 MB RAM.
- VPS 4 GB → ~10 workers SUNAT.
- VPS 8-16 GB → 20-40 workers.
- El límite real lo pone **SUNAT por RUC**, no tu servidor (ver nota final).

---

## 3. Workers escalados — Opción B: Laravel Horizon (recomendado)

Horizon añade dashboard en vivo, auto-balanceo y métricas. **Se instala en el VPS**
(requiere `ext-pcntl`/`ext-posix`, que existen en Linux pero no en Windows):

```bash
cd ~/plataform-api-sunat
composer require laravel/horizon
php artisan horizon:install
```

Edita `config/horizon.php` → `environments.production`:

```php
'production' => [
    'sunat' => [
        'connection' => 'redis',
        'queue' => ['sunat'],
        'balance' => 'auto',
        'minProcesses' => 4,
        'maxProcesses' => 20,          // escala según CPU/RAM del VPS
        'tries' => 20,
        'timeout' => 120,
    ],
    'otros' => [
        'connection' => 'redis',
        'queue' => ['webhooks', 'mail', 'default'],
        'balance' => 'auto',
        'minProcesses' => 1,
        'maxProcesses' => 5,
        'tries' => 3,
    ],
],
```

Supervisor corre **un solo proceso** `horizon` (él gestiona los workers internamente).
Reemplaza los `[program:api-pro-*]` por:

```ini
[program:api-pro-horizon]
process_name=%(program_name)s
command=php /home/deploy/plataform-api-sunat/artisan horizon
autostart=true
autorestart=true
user=deploy
redirect_stderr=true
stdout_logfile=/home/deploy/plataform-api-sunat/storage/logs/horizon.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
```

Dashboard: protégelo en `app/Providers/HorizonServiceProvider.php` (`gate()`) para que
solo super_admin lo vea, y accede en `https://tudominio/horizon`.

> En cada deploy con Horizon: `php artisan horizon:terminate` (Supervisor lo reinicia
> con el código nuevo). Agrégalo al workflow de actualización.

---

## 4. Verificación

```bash
# La cola está en Redis:
php artisan about --only=drivers | grep Queue      # → redis

# Jobs fluyendo:
redis-cli llen queues:sunat                        # profundidad de la cola sunat
sudo supervisorctl status                          # workers RUNNING
tail -f storage/logs/worker-sunat.log
```

---

## 5. El límite REAL: SUNAT por RUC

Aunque tengas 50 workers, **SUNAT limita por RUC**. No se pueden empujar 1000
facturas/min de un mismo RUC de forma síncrona. Por eso:

- El rate limiter `sunat-tenant` (120/min por RUC por defecto) evita que un cliente
  sature a SUNAT y a la vez reparte los workers entre todos los tenants.
- **Boletas de alto volumen → resumen diario** (`SummaryController`): en vez de enviar
  cada boleta 1x1, se acumulan y se mandan en UN resumen. Reduce las llamadas a SUNAT
  ~99%. Es la solución correcta para miles de boletas/min.

Ajusta `SUNAT_PER_TENANT_PER_MINUTE` según lo que SUNAT tolere en tu caso real.

# Despliegue en VPS (Ubuntu 24, Hostinger)

Guía completa para desplegar API-PRO en un VPS limpio Ubuntu 24 (Hostinger u otro). El resultado: una API en producción con MySQL + Nginx + SSL + queue worker + scheduler.

> Si vas a usar **hosting compartido** (no VPS), lee [`19-Cron-hosting.md`](./19-Cron-hosting.md). Esta guía asume acceso root y procesos persistentes.

---

## 0. Pre-requisitos

- VPS Ubuntu 24.04 con acceso SSH como root
- Un dominio apuntando al IP del VPS (ej: `api.tudominio.com → 31.220.81.45`)
- El proyecto en un repo Git (GitHub/GitLab) **o** listo para subir vía SFTP

---

## 1. Actualizar sistema + instalar dependencias base

```bash
apt update && apt upgrade -y
apt install -y software-properties-common curl git unzip nano ufw
```

## 2. Instalar PHP 8.3 + extensiones

Ubuntu 24 trae PHP 8.3 por defecto:

```bash
apt install -y php8.3 php8.3-fpm php8.3-cli \
  php8.3-mysql php8.3-pgsql \
  php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath \
  php8.3-gd php8.3-intl php8.3-soap php8.3-redis \
  php8.3-opcache

# Verificar
php -v
```

## 3. Instalar Composer

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
composer --version
```

## 4. Instalar MySQL 8

```bash
apt install -y mysql-server
systemctl enable --now mysql
mysql_secure_installation     # contraseña root, sí a todo
```

Crear base de datos y usuario:

```bash
mysql -u root -p
```

```sql
CREATE DATABASE api_pro_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'apipro'@'localhost' IDENTIFIED BY 'PASSWORD_FUERTE_AQUI';
GRANT ALL PRIVILEGES ON api_pro_v2.* TO 'apipro'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 5. Instalar Nginx

```bash
apt install -y nginx
systemctl enable --now nginx
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw enable
```

Verifica que ves la página default en `http://TU_IP/`.

## 6. Clonar el proyecto

Recomendado: usar un usuario no-root para la app.

```bash
adduser deploy
usermod -aG www-data deploy
su - deploy
cd ~
git clone https://github.com/TU_USER/API-PRO.git api-pro
cd api-pro
composer install --no-dev --optimize-autoloader
```

> **Sin Git?** Sube via SFTP o FileZilla a `/home/deploy/api-pro/` y luego corre el `composer install`.

## 7. Configurar `.env`

```bash
cp .env.example .env
nano .env
```

Valores críticos:

```ini
APP_NAME=API-PRO
APP_ENV=production
APP_KEY=                       # se genera abajo
APP_DEBUG=false
APP_URL=https://api.tudominio.com

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=api_pro_v2
DB_USERNAME=apipro
DB_PASSWORD=PASSWORD_FUERTE_AQUI

CACHE_STORE=file               # o redis si instalas
QUEUE_CONNECTION=database
SESSION_DRIVER=database

# SUNAT
SUNAT_ENV=produccion           # o "beta" para pruebas

# GRE REST (guías)
GRE_CLIENT_ID=
GRE_CLIENT_SECRET=
```

Generar APP_KEY:

```bash
php artisan key:generate
```

## 8. Migraciones + storage link

```bash
php artisan migrate --force
php artisan storage:link
```

## 9. Permisos correctos

Como `root`:

```bash
chown -R deploy:www-data /home/deploy/api-pro
find /home/deploy/api-pro -type d -exec chmod 755 {} \;
find /home/deploy/api-pro -type f -exec chmod 644 {} \;
chmod -R 775 /home/deploy/api-pro/storage /home/deploy/api-pro/bootstrap/cache
```

## 10. Configurar Nginx

Crear `/etc/nginx/sites-available/api-pro`:

```nginx
server {
    listen 80;
    server_name api.tudominio.com;
    root /home/deploy/api-pro/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;
    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Activar y recargar:

```bash
ln -s /etc/nginx/sites-available/api-pro /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
```

## 11. SSL con Let's Encrypt

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d api.tudominio.com
# Acepta términos, ingresa email, y elige redirección HTTP→HTTPS
```

Renovación automática (ya queda configurada):

```bash
systemctl status certbot.timer
```

## 12. Supervisor para queue worker (recomendado en VPS)

En VPS, lo correcto es un daemon persistente (no `cron-jobs.php`):

```bash
apt install -y supervisor
nano /etc/supervisor/conf.d/api-pro-worker.conf
```

```ini
[program:api-pro-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/deploy/api-pro/artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=2
redirect_stderr=true
stdout_logfile=/home/deploy/api-pro/storage/logs/worker.log
stopwaitsecs=3600
```

Activar:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start api-pro-worker:*
supervisorctl status
```

## 13. Scheduler (cron del sistema)

```bash
crontab -u deploy -e
```

Agregar:

```cron
* * * * * cd /home/deploy/api-pro && php artisan schedule:run >> /dev/null 2>&1
```

## 14. Optimizaciones de producción

```bash
cd /home/deploy/api-pro
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Para **opcache** (mejora 30-50% rendimiento PHP), edita `/etc/php/8.3/fpm/php.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
```

```bash
systemctl restart php8.3-fpm
```

## 15. Verificación final

```bash
# La API responde
curl https://api.tudominio.com/api/v1/planes

# Worker corriendo
supervisorctl status

# Scheduler corriendo
grep CRON /var/log/syslog | tail -5

# Logs de Laravel
tail -f /home/deploy/api-pro/storage/logs/laravel.log
```

---

## Workflow de actualización (deploys posteriores)

Una vez deployado, para cada update:

```bash
su - deploy
cd ~/api-pro
php artisan down

git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Reiniciar workers para que carguen el nuevo código
exit                                          # volver a root
supervisorctl restart api-pro-worker:*

su - deploy
cd ~/api-pro
php artisan up
```

---

## Backup automático de BD (opcional pero recomendado)

```bash
mkdir -p /home/deploy/backups
nano /home/deploy/backup-db.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/home/deploy/backups"
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u apipro -pPASSWORD_FUERTE_AQUI api_pro_v2 | gzip > $BACKUP_DIR/api_pro_v2_$DATE.sql.gz
# Borrar backups de más de 14 días
find $BACKUP_DIR -name "*.sql.gz" -mtime +14 -delete
```

```bash
chmod +x /home/deploy/backup-db.sh
crontab -u deploy -e
```

```cron
0 3 * * * /home/deploy/backup-db.sh
```

---

## Troubleshooting común

| Síntoma | Causa | Fix |
|---|---|---|
| `502 Bad Gateway` | PHP-FPM caído | `systemctl restart php8.3-fpm` |
| `419 Page Expired` | session driver mal config | Verifica `SESSION_DRIVER=database` y corre migrations |
| `Permission denied` en logs | Permisos storage/ | `chmod -R 775 storage bootstrap/cache && chown -R deploy:www-data storage` |
| Jobs no procesan | Worker caído | `supervisorctl status` → `restart api-pro-worker:*` |
| `SQLSTATE[HY000] [2002]` | MySQL caído | `systemctl status mysql` → `start mysql` |
| Cambios .env no toman efecto | Config cacheado | `php artisan config:clear && php artisan config:cache` |
| Nginx `413 Request Entity Too Large` | Subida grande (cert .pfx) | Subir `client_max_body_size 50M` en nginx, restart |

---

## Firewall recomendado

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw status verbose
```

> Si cambias el puerto SSH (recomendado), abre el nuevo: `ufw allow 22XXX/tcp`.

---

## Notas Hostinger VPS específicas

- El panel hPanel de Hostinger te da consola web SSH — más cómoda para los primeros pasos.
- En Hostinger puedes apuntar el dominio desde el panel de Dominios → DNS, agregando un registro A: `api → IP_DEL_VPS`.
- Hostinger ya viene con SSH y root habilitados por defecto.
- Si es un VPS pequeño (KVM 1 / 2GB RAM), reduce `numprocs=2` a `1` en supervisor para no saturar RAM.

#!/bin/sh
set -e

echo "==> Optimizando autoload..."
composer dump-autoload --optimize --no-dev 2>/dev/null || true

echo "==> Cacheando config y rutas..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Ejecutando migraciones..."
php artisan migrate --force

echo "==> Creando symlink storage..."
php artisan storage:link 2>/dev/null || true

echo "==> Iniciando servidor..."
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"

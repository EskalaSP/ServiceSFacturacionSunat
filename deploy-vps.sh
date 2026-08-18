#!/usr/bin/env bash
#
# deploy-vps.sh — Despliegue en VPS SIN Docker (bare-metal PHP-FPM + Nginx).
#
# Actualiza el código y DEJA TODA LA CACHE LIMPIA, para no arrastrar
# config/datos/errores viejos. Pensado para correr como el usuario `deploy`
# desde la raíz del proyecto:
#
#     ./deploy-vps.sh
#
# Si tu OPcache tiene validate_timestamps=0 (ver documentacion/20), además
# hay que recargar PHP-FPM como root para que tome el código nuevo:
#
#     sudo systemctl reload php8.3-fpm
#
# (o mejor: pon opcache.validate_timestamps=1 una sola vez y te olvidas).

set -euo pipefail
cd "$(dirname "$0")"

echo "==> [1/4] Actualizando código (git pull)"
git pull origin main

echo "==> [2/4] Dependencias (composer, prod)"
composer install --no-dev --optimize-autoloader

echo "==> [3/4] Migraciones"
php artisan migrate --force

echo "==> [4/4] Limpiando TODA la cache (config, rutas, vistas, eventos, datos)"
php artisan optimize:clear

echo ""
echo "======================================================================"
echo " LISTO. Cache limpia — el código nuevo está activo."
echo ""
echo " Si NO ves los cambios reflejados, es OPcache. Como root:"
echo "     systemctl reload php8.3-fpm"
echo " (o pon opcache.validate_timestamps=1 en php.ini para no repetirlo)."
echo "======================================================================"

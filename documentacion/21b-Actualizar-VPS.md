# Actualizar el servidor VPS con los cambios del proyecto

> Guía de uso diario. Para la instalación inicial desde cero lee `20-Despliegue-VPS.md`.

---

## Resumen rápido (copia y pega)

```bash
# 1 — Entra al servidor como usuario deploy
su - deploy
cd ~/api-pro

# 2 — Trae el código nuevo
git pull origin main

# 3 — Instala dependencias PHP (sin devtools)
composer install --no-dev --optimize-autoloader

# 4 — Compila el frontend si hubo cambios en React/Inertia/JS
npm ci && npm run build

# 5 — Corre las migraciones nuevas
php artisan migrate --force

# 6 — Limpia cachés viejas y regenera las útiles
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7 — Vuelve a root para reiniciar los procesos
exit

# 8 — Reinicia el worker de colas (toma el código nuevo)
sudo supervisorctl restart api-pro-worker:*

# 9 — Recarga PHP-FPM para vaciar OPcache
sudo systemctl reload php8.3-fpm
```

---

## Qué hace cada paso y por qué importa

| Paso | Comando | Por qué es necesario |
|------|---------|----------------------|
| 2 | `git pull` | Trae los archivos PHP, TS y de configuración actualizados |
| 3 | `composer install` | Instala o actualiza dependencias PHP; sin esto el código nuevo puede romperse si usa una clase nueva |
| 4 | `npm run build` | Recompila React/Inertia; sin esto el frontend sigue con el JS viejo aunque PHP ya tenga el código nuevo |
| 5 | `migrate --force` | Aplica columnas o tablas nuevas a la BD; sin esto el modelo PHP puede fallar al leer o escribir |
| 6 | `optimize:clear` + cache | Borra la config, rutas y vistas en caché que apuntan a archivos viejos; los regenera con los nuevos |
| 8 | `supervisorctl restart` | El worker de colas carga el código PHP en memoria al arrancar; sin reiniciarlo sigue corriendo el código viejo |
| 9 | `reload php8.3-fpm` | Vacía OPcache para que PHP sirva el código nuevo en cada request; sin esto Nginx puede seguir sirviendo código anterior |

---

## Cuándo omitir pasos

| Si el cambio NO incluye… | Puedes saltarte… |
|--------------------------|-----------------|
| Cambios en JS/React/Inertia | `npm ci && npm run build` |
| Nuevas migraciones | `php artisan migrate` |
| Cambios en `.env` | `php artisan config:cache` |
| Cambios en rutas PHP | `php artisan route:cache` |
| Cambios en vistas Blade | `php artisan view:cache` |

Si tienes dudas, corre todo el bloque completo. Nunca rompe nada.

---

## Alternativa: usar el script incluido

El proyecto ya tiene un script que hace los pasos 2 al 6:

```bash
su - deploy
cd ~/api-pro
./deploy-vps.sh

exit

# Pasos 8 y 9 siguen siendo manuales:
sudo supervisorctl restart api-pro-worker:*
sudo systemctl reload php8.3-fpm
```

---

## La cola no pierde trabajos al reiniciar

La cola usa el driver `database`, así que los jobs están guardados en la tabla `jobs`
de MySQL. Al reiniciar el worker con `supervisorctl restart`:

- Los jobs en ejecución se interrumpen y vuelven a la cola automáticamente.
- Los jobs que estaban esperando siguen ahí y se procesan cuando el worker vuelve.
- No se pierde ningún comprobante ni envío a SUNAT.

---

## Verificar que todo quedó corriendo

```bash
# Worker de colas activo
sudo supervisorctl status

# PHP-FPM activo
sudo systemctl status php8.3-fpm

# Nginx activo
sudo systemctl status nginx

# La app responde
curl -s https://api.tudominio.com/ | head -5

# Logs en tiempo real (Ctrl+C para salir)
tail -f /home/deploy/api-pro/storage/logs/laravel.log
```

---

## Si el frontend no muestra los cambios en el navegador

El navegador puede tener el JS viejo en caché. Forzar recarga:

- Chrome / Edge: **Ctrl + Shift + R**
- Firefox: **Ctrl + Shift + R**
- Safari: **Cmd + Shift + R**

---

## Cuándo sí usar `php artisan down`

Solo en dos casos:

1. La migración es destructiva (borra o renombra columnas que el código viejo todavía usa).
2. El cambio necesita que ningún request nuevo entre mientras se actualiza.

En ese caso:

```bash
su - deploy
cd ~/api-pro
php artisan down --message="Actualizando sistema" --retry=30

# ... (pasos 2 al 6) ...

php artisan up
```

En la mayoría de updates normales **no hace falta**.

---

## Troubleshooting rápido

| Síntoma después del update | Causa probable | Fix |
|---------------------------|---------------|-----|
| El frontend sigue igual | Faltó compilar JS o el navegador tiene caché | `npm run build` + Ctrl+Shift+R |
| `Class not found` en logs | Composer no actualizó | `composer install --no-dev --optimize-autoloader` |
| Error 500 después de migrar | Caché de rutas apunta a controlador viejo | `php artisan optimize:clear && php artisan route:cache` |
| Los jobs no se procesan | Worker no se reinició | `sudo supervisorctl restart api-pro-worker:*` |
| Código PHP sin cambios | OPcache todavía tiene la versión anterior | `sudo systemctl reload php8.3-fpm` |
| `SQLSTATE` al acceder | Faltó correr migrate | `php artisan migrate --force` |

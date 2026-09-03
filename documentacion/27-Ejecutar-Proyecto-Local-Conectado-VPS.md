# Ejecutar el proyecto en local conectado a la BD del VPS

Guía paso a paso para levantar el proyecto en tu PC (Windows + Laragon) usando la base de datos real que corre en el VPS, a través de un túnel SSH.

> ⚠️ **Seguridad**: no pongas contraseñas reales en este archivo ni en ningún archivo que se suba a git. Las credenciales reales van **solo** en tu `.env` local (ya está en `.gitignore`). Aquí se usan placeholders como `TU_IP_VPS`, `TU_USUARIO_SSH`, etc.

---

## PARTE A — Instalación inicial (solo la primera vez)

### 1. Abrir Laragon

Abre la aplicación Laragon en Windows.

### 2. Verificar/activar PHP 8.2+

- Click derecho sobre el ícono de Laragon → **PHP** → selecciona una versión 8.2 o superior (ej. 8.3.x).
- Si no la tienes, click derecho → **PHP** → **Download more...** para descargarla.

### 3. Habilitar extensiones PHP necesarias

Edita el `php.ini` de la versión activa (ruta típica: `C:\laragon\bin\php\php-8.3.x\php.ini`) y confirma que **no** tengan `;` delante:

```ini
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=mysqli
extension=soap
extension=zip
```

Guarda y reinicia la terminal si hiciste cambios.

### 4. Abrir la terminal de Laragon

En el panel principal de Laragon, click en el botón **Terminal**.

### 5. Ir a la carpeta del proyecto

```powershell
cd C:\Users\Usuario\Desktop\Sistemas\Facturacion\ServiceSFacturacionSunat
```

### 6. Verificar versión de PHP

```powershell
php -v
```

Debe mostrar 8.2.x o superior.

### 7. Instalar dependencias PHP

```powershell
composer install
```

### 8. Instalar dependencias de frontend

```powershell
npm install
```

### 9. Crear el archivo `.env`

```powershell
cp .env.example .env
```

### 10. Generar la clave de la aplicación

```powershell
php artisan key:generate
```

### 11. Apagar el MySQL de Laragon

En el panel de Laragon, apaga el toggle de **MySQL** (no lo usarás — te conectarás al MySQL del VPS por túnel, y ambos usan el puerto 3306).

### 12. Configurar el `.env` para apuntar al túnel (ver Parte B, paso 3)

Deja el `.env` listo con los valores de conexión (siguiente parte explica cómo obtenerlos).

Con esto, la instalación queda lista. Para el día a día, usa la **Parte B**.

---

## PARTE B — Conexión diaria (cuando ya está todo instalado)

Cada vez que quieras trabajar en el proyecto conectado a la base real del VPS:

### 1. Abrir Laragon

Abre Laragon (si no lo tienes ya abierto).

### 2. Abrir la terminal de Laragon (Terminal 1 — túnel SSH)

Click en **Terminal** en el panel de Laragon.

Ubícate donde quieras (no importa la carpeta) y abre el túnel:

```powershell
ssh -N -L 3306:127.0.0.1:3306 root@147.93.11.69
```

- Te pedirá la contraseña de ese usuario SSH.
- **No cierres esta ventana** — debe quedar abierta todo el tiempo que quieras usar la base remota.
- Si dice `Permission denied`, verifica el usuario y la contraseña con el que accedes normalmente al VPS.

### 3. (Solo si no recuerdas las credenciales de la BD) Consultarlas desde otra terminal

Abre una **segunda** terminal de Laragon (Terminal 2) y ejecuta:

```powershell
ssh TU_USUARIO_SSH@TU_IP_VPS "grep DB_ /home/deploy/api-pro/.env"
```

Esto imprime `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` reales del VPS. Anótalos (no los compartas ni los subas a git).

### 4. Configurar el `.env` local del proyecto

Abre el archivo `.env` en la raíz del proyecto y ajusta:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<valor real obtenido en el paso 3>
DB_USERNAME=<valor real obtenido en el paso 3>
DB_PASSWORD=<valor real obtenido en el paso 3>

SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

`SESSION_DRIVER=file` y `QUEUE_CONNECTION=sync` evitan que tu sesión/jobs de prueba locales se mezclen con las sesiones y colas reales de producción, aunque ambos apunten a la misma base de datos.

### 5. Verificar la conexión (opcional pero recomendado)

En Terminal 2, ve a la carpeta del proyecto y abre tinker:

```powershell
cd C:\Users\Usuario\Desktop\Sistemas\Facturacion\ServiceSFacturacionSunat
php artisan tinker
```

Dentro de tinker (prompt `>`):

```php
DB::connection()->getPdo();
```

Si no da error, la conexión funciona. Sal con:

```php
exit
```

### 6. Levantar el frontend en modo desarrollo (Terminal 3)

Abre una tercera terminal de Laragon:

```powershell
cd C:\Users\Usuario\Desktop\Sistemas\Facturacion\ServiceSFacturacionSunat
npm run dev
npm run build
```

Déjala corriendo — recompila y refresca el navegador automáticamente cuando modificas código en `resources/js`.

### 7. Levantar el servidor Laravel (Terminal 4)

Abre una cuarta terminal de Laragon:

```powershell
cd C:\Users\Usuario\Desktop\Sistemas\Facturacion\ServiceSFacturacionSunat
php artisan serve
```

### 8. Abrir el proyecto en el navegador

```
http://127.0.0.1:8000
```

---

## Resumen de terminales abiertas en simultáneo

| Terminal | Comando                                            | ¿Se puede cerrar?             |
| -------- | -------------------------------------------------- | ----------------------------- |
| 1        | `ssh -N -L 3306:127.0.0.1:3306 usuario@ip` (túnel) | No, mientras trabajes         |
| 2        | usada puntualmente (tinker, consultas)             | Sí, cuando termines de usarla |
| 3        | `npm run dev`                                      | No, mientras trabajes         |
| 4        | `php artisan serve`                                | No, mientras trabajes         |

## Al terminar de trabajar

1. `Ctrl+C` en la terminal de `php artisan serve`.
2. `Ctrl+C` en la terminal de `npm run dev`.
3. `Ctrl+C` en la terminal del túnel SSH (esto corta el acceso a la base del VPS).

---

## ⚠️ Advertencias importantes

- Estás trabajando contra la **base de datos real de producción** (facturación SUNAT). Evita `php artisan migrate`, `php artisan db:seed` o `php artisan queue:work` contra esta conexión salvo que sepas exactamente lo que haces y tengas un backup reciente.
- Si necesitas hacer pruebas de escritura masivas, mejor saca un `mysqldump` y trabaja sobre una copia local (ver [23-Credenciales.md](./23-Credenciales.md) y [20-Despliegue-VPS.md](./20-Despliegue-VPS.md) para más contexto de la infraestructura del VPS).
- Nunca pegues contraseñas reales del VPS o de la base de datos en archivos dentro de `documentacion/` u otras carpetas versionadas con git — solo van en tu `.env` local.

otros -

cd C:\Users\Usuario\Desktop\Sistemas\Facturacion\ServiceSFacturacionSunat
php artisan sunat:sync-ambiente

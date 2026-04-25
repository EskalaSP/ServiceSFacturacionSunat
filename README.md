# SUNAT API — Facturación Electrónica Perú

API REST para emisión de comprobantes electrónicos ante SUNAT: facturas, boletas, notas de crédito/débito, guías de remisión y comprobantes de retención.

- **Stack:** Laravel 12 · PHP 8.2 · MySQL · Queue (database)
- **Librería SUNAT:** [Greenter](https://greenter.dev)
- **Producción:** `https://apisunatv2.kodevo.es`

---

## Requisitos

| Herramienta | Versión mínima |
|-------------|---------------|
| PHP | 8.2 |
| Composer | 2.x |
| Node.js | 18.x |
| MySQL | 8.0 (o MariaDB 10.6) |

---

## Instalación local

### 1. Clonar el repositorio

```bash
git clone https://github.com/yorchavez9/plataform-api-sunat.git
cd plataform-api-sunat
```

### 2. Instalar dependencias

```bash
composer install
npm install
```

### 3. Configurar el entorno

```bash
cp .env.example .env
php artisan key:generate
```

Abre `.env` y configura la base de datos:

```env
APP_NAME="SUNAT API"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sunat_api
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

### 4. Crear la base de datos y ejecutar migraciones

```bash
# Crear la base de datos en MySQL primero, luego:
php artisan migrate --seed
```

### 5. Compilar assets

```bash
npm run build
```

### 6. Levantar el servidor

```bash
# Opción A — un solo comando (servidor + queue + vite en paralelo)
composer dev

# Opción B — manual
php artisan serve
php artisan queue:listen --tries=1
```

La API queda disponible en `http://localhost:8000`.

---

## [Documentación](documentacion/README.md)

---

## Licencia

Propietario — todos los derechos reservados.

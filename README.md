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

## Uso rápido

### Registrar un tenant (empresa)

```http
POST /api/v1/auth/register
Content-Type: application/json

{
    "name": "Tu Nombre",
    "email": "tu@email.com",
    "password": "secret123",
    "password_confirmation": "secret123",
    "ruc": "20123456789",
    "razon_social": "MI EMPRESA SAC",
    "sol_user": "MODDATOS",
    "sol_pass": "MODDATOS",
    "entorno": "beta"
}
```

La respuesta incluye `api_key` y `api_secret` — úsalos en todas las peticiones siguientes.

### Autenticarse

```http
POST /api/v1/auth/login
Content-Type: application/json

{
    "email": "tu@email.com",
    "password": "secret123"
}
```

### Emitir una factura

```http
POST /api/v1/facturas
Authorization: Bearer {token}
Content-Type: application/json

{
    "serie": "F001",
    "correlativo": "1",
    "fecha_emision": "2026-04-25",
    "tipo_moneda": "PEN",
    "client_tipo_doc": "6",
    "client_num_doc": "20000000001",
    "client_razon_social": "CLIENTE SAC",
    "items": [
        {
            "descripcion": "Producto de prueba",
            "cantidad": 1,
            "valor_unitario": 100.00,
            "tipo_igv": "10"
        }
    ]
}
```

---

## Cambiar entre beta y producción

```http
PATCH /api/v1/empresa
Authorization: Bearer {token}
Content-Type: application/json

{
    "entorno": "production"
}
```

> En **beta** se usan los endpoints de prueba de SUNAT (`e-beta.sunat.gob.pe`) y no hay límite de documentos.
> En **production** se requiere certificado digital y credenciales SOL reales.

---

## Cron jobs (hosting compartido)

Para hosting sin SSH (Hostinger, cPanel), configura un cron cada minuto:

```
* * * * * /usr/bin/php /home/USER/domains/TUDOMINIO/public_html/cron-jobs.php
```

Esto ejecuta el scheduler de Laravel y procesa la cola de jobs automáticamente.

---

## Endpoints principales

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/auth/register` | Registrar empresa |
| POST | `/api/v1/auth/login` | Iniciar sesión |
| GET | `/api/v1/empresa` | Datos del tenant |
| PATCH | `/api/v1/empresa` | Actualizar datos / entorno |
| POST | `/api/v1/empresa/logo` | Subir logo |
| POST | `/api/v1/facturas` | Emitir factura |
| POST | `/api/v1/boletas` | Emitir boleta |
| POST | `/api/v1/notas-credito` | Emitir nota de crédito |
| POST | `/api/v1/notas-debito` | Emitir nota de débito |
| POST | `/api/v1/retenciones` | Emitir comprobante de retención |
| GET | `/api/v1/{tipo}/{id}/pdf` | Descargar PDF (a4, a5, ticket-80, ticket-58) |
| GET | `/api/v1/{tipo}/{id}/xml` | Descargar XML firmado |
| GET | `/api/v1/{tipo}/{id}/cdr` | Descargar CDR de SUNAT |

---

## Planes

| Plan | Documentos/mes |
|------|---------------|
| free | 20 |
| pro | ilimitado (configurado por admin) |
| business | ilimitado (configurado por admin) |

---

## Licencia

Propietario — todos los derechos reservados.

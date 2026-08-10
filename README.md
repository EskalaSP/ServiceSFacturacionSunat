# SUNAT API

Facturación electrónica peruana, lista para integrarse

API REST multiempresa para emitir, consultar y administrar comprobantes electrónicos ante SUNAT desde cualquier aplicación.

Producción: apisunatv2.kodevo.es
Documentación: documentacion/README.md

---

## Una sola API para todo el ciclo tributario

SUNAT API centraliza la operación de facturación de tus empresas, sucursales y series en una integración consistente. Emite un documento, sigue su estado y descarga sus archivos XML, CDR o PDF sin repartir la lógica tributaria por todo tu producto.

Emisión:
- Facturas y boletas
- Notas de crédito y débito
- Guías de remisión
- Retenciones y percepciones

Operación:
- Envío automático o manual
- Reenvío y consulta de CPE
- Resúmenes y anulaciones
- XML, CDR y PDF

Control:
- Dashboard y KPIs
- Reportes y alertas
- Exportación masiva ZIP
- SIRE / Registro de Compras

## Por qué esta API

- Multiempresa desde el inicio: cada integración trabaja con sus credenciales, empresa, sucursales y series.
- Lista para el flujo real: soporta documentos pendientes, rechazados, reenvíos, pagos y consultas posteriores.
- Segura por contrato: autenticación por X-Api-Key y X-Api-Secret en cada endpoint protegido.
- Pensada para crecer: procesamiento con colas, scheduler y una capa de servicios separada de tu negocio.

## Flujo de integración

❶ REGISTRAR EMPRESA
        │
        ▼
❷ CONFIGURAR SUCURSAL Y SERIE
        │
        ├──► 🔑 Obtener credenciales API
        │
        ▼
❸ EMITIR DOCUMENTO
        │
        ▼
❹ CONSULTAR
   ├──► Estado del documento
   ├──► Descargar XML
   ├──► Descargar CDR
   └──► Descargar PDF

## Quick start

### Requisitos

- PHP 8.2 o superior
- Composer 2.x
- Node.js 18.x o superior
- MySQL 8.0+ o MariaDB 10.6+

### Instalación local

git clone https://github.com/yorchavez9/plataform-api-sunat.git
cd plataform-api-sunat

composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build

#### Opción A — Todo en uno (recomendado)

composer dev

#### Opción B — Manual

# Terminal 1: Servidor Laravel + Vite
php artisan serve
npm run dev

# Terminal 2: Procesador de colas
php artisan queue:listen --tries=1

### Variables esenciales

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

---

## Emitir una factura

Una vez registrada la empresa y configurada la serie, crea tu primer comprobante:

curl -X POST https://tu-api.com/api/v1/facturas \
    -H "X-Api-Key: {tu_api_key}" \
    -H "X-Api-Secret: {tu_api_secret}" \
    -H "Content-Type: application/json" \
    -d '{
        "serie": "F001",
        "fecha_emision": "2026-08-10",
        "tipo_operacion": "0101",
        "tipo_moneda": "PEN",
        "forma_pago": "Contado",
        "cliente": {
            "tipo_doc": "6",
            "num_doc": "20512345678",
            "razon_social": "CLIENTE DEMO SAC",
            "direccion": "AV. AREQUIPA 1234, LIMA"
        },
        "items": [{
            "codigo": "P001",
            "descripcion": "Lapicero",
            "unidad": "NIU",
            "cantidad": 1,
            "precio_unitario": 1.50,
            "tip_afe_igv": "10"
        }]
    }'

Nota: Puedes agregar "correlativo": 600 para un número específico. Si lo omites, la API continuará el flujo según la configuración de la serie. La respuesta incluye la identificación del documento y permite acceder a sus endpoints de estado, reenvío, XML, CDR y PDF.

---

## Autenticación

Todos los endpoints, excepto /registro y /planes, requieren estas cabeceras:

X-Api-Key: {tu_api_key}
X-Api-Secret: {tu_api_secret}

Importante: Mantén api_secret exclusivamente en tu backend. Nunca lo expongas en una aplicación frontend.

---

## Documentación

Guías disponibles:
- Configuración inicial: Empresa, credenciales, certificado, logo, sucursales y series
- Facturas: CRUD, envío, XML, CDR, PDF y pagos
- Boletas: Emisión y ciclo de vida de boletas
- Notas de crédito: Anulaciones, devoluciones y descuentos
- Guías de remisión: Traslado de mercadería y documentos relacionados
- Panel de control: KPIs, alertas, aging y reportes
- SIRE: Registro de Compras y endpoints SIRE
- Despliegue en VPS: Nginx, SSL, Supervisor, cron y producción

Ver toda la documentación: documentacion/README.md

---

## Stack

Laravel 12 · PHP 8.2 · MySQL · Greenter · Queues (database)
Inertia 2 · React 19 · TypeScript · Vite 7 · Pest 4

## Desarrollo y calidad

composer dev             # Servidor, queue y Vite (todo en uno)
composer test            # Suite de tests y validaciones
composer lint:check      # Estilo PHP
npm run lint:check       # ESLint
npm run types:check      # TypeScript

## Licencia

Software propietario. Todos los derechos reservados.

---

SUNAT API · Integraciones tributarias que siguen el ritmo de tu negocio.
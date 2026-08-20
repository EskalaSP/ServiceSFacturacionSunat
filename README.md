<div align="center">

# SUNAT API

### Plataforma profesional de facturación electrónica para Perú

API REST **multiempresa** para emitir, consultar y administrar comprobantes electrónicos ante **SUNAT** desde cualquier aplicación.

<p>
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" />
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" />
  <img src="https://img.shields.io/badge/Greenter-SUNAT-0F766E?style=for-the-badge" />
  <img src="https://img.shields.io/badge/Status-Production-16A34A?style=for-the-badge" />
</p>

**Producción**
https://apisunatv2.kodevo.es

**Documentación**
`documentacion/README.md`

</div>

---

## Arquitectura

```text
                   Cliente / ERP / POS / E-commerce
                               │
                               ▼
                       SUNAT API (Laravel 12)
                               │
          ┌────────────────────┼────────────────────┐
          │                    │                    │
          ▼                    ▼                    ▼
      Empresas             Sucursales             Series
          │                    │                    │
          └──────────────── Emisión CPE ───────────┘
                               │
                               ▼
                       XML • CDR • PDF • SUNAT
```

---

## Capacidades

<table>
<tr>
<td width="50%">

### Emisión

* Facturas
* Boletas
* Notas de crédito
* Notas de débito
* Guías de remisión

</td>
<td width="50%">

### Gestión

* Multiempresa
* API Key / Secret
* XML, CDR y PDF
* Reenvío y consulta
* Colas (Queues)

</td>
</tr>
</table>

---

## Instalación

```bash
git clone https://github.com/yorchavez9/plataform-api-sunat.git
cd plataform-api-sunat

composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Enlace simbólico public/storage -> storage/app/public
# (necesario para servir logos, PDFs y demás archivos públicos)
php artisan storage:link
```

---

## Configuración

```env
APP_NAME=SUNAT_API
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

---

## Iniciar el proyecto

### Opción recomendada

```bash
composer dev
```

### Opción manual

Ejecuta cada proceso en una terminal diferente.

```bash
# Terminal 1
php artisan serve

# Terminal 2 — el worker DEBE escuchar las colas dedicadas (ver nota abajo)
php artisan queue:work --queue=sunat,webhooks,mail,default --tries=1 --timeout=120 --sleep=1

# Terminal 3
npm run dev
```

**Aplicación:** http://localhost:8000

---

## Flujo de integración

```text
Registrar empresa
        │
        ▼
Configurar sucursal y serie
        │
        ▼
Emitir comprobante
        │
        ▼
Enviar a SUNAT
        │
        ▼
Consultar estado
        │
        ▼
Descargar XML / CDR / PDF
```

---

## Autenticación

Todos los endpoints protegidos requieren las siguientes cabeceras:

```http
X-Api-Key: {API_KEY}
X-Api-Secret: {API_SECRET}
```

Mantén `API_SECRET` únicamente en tu backend.

---

## Ejemplo de emisión de factura

Una vez registrada la empresa y configurada la serie, puedes emitir una factura electrónica enviando una solicitud **POST**.

### Endpoint

```http
POST /api/v1/facturas
```

### Cabeceras

```http
X-Api-Key: {API_KEY}
X-Api-Secret: {API_SECRET}
Content-Type: application/json
```

### Cuerpo de la solicitud

```json
{
  "serie": "F001",
  // "correlativo": 600,
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
  "items": [
    {
      "codigo": "P001",
      "descripcion": "Lapicero",
      "unidad": "NIU",
      "cantidad": 1,
      "precio_unitario": 1.50,
      "tip_afe_igv": "10"
    }
  ]
}
```

El campo `correlativo` es opcional. Si no se envía, la API utilizará la numeración configurada para la serie correspondiente.

La respuesta permite continuar con las operaciones de **consulta de estado**, **reenvío**, **XML**, **CDR** y **PDF**.

---

## Documentación

| Módulo            | Archivo                                |
| ----------------- | -------------------------------------- |
| Configuración     | `documentacion/01-Configuracion.md`    |
| Facturas          | `documentacion/04-Facturas.md`         |
| Boletas           | `documentacion/05-Boletas.md`          |
| Notas de crédito  | `documentacion/06-Notas-credito.md`    |
| Guías de remisión | `documentacion/10-Guia-remision-RM.md` |
| Panel de control  | `documentacion/15-Panel-de-control.md` |
| SIRE              | `documentacion/17-Sire.md`             |
| Despliegue VPS    | `documentacion/20-Despliegue-VPS.md`   |

**Documentación completa:** `documentacion/README.md`

---

## Stack

```text
Laravel 12
PHP 8.2
MySQL
Greenter
React 19
TypeScript
Inertia
Vite
```

---

## Comandos útiles

```bash
composer dev
composer test
composer lint:check

npm run lint:check
npm run types:check
```

---

## Licencia

**Software propietario. Todos los derechos reservados.**

<div align="center">

**SUNAT API**
Integración profesional con SUNAT para aplicaciones empresariales.

</div>

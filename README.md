<div align="center">

# SUNAT API

### Facturacion electronica peruana, lista para integrarse

API REST multiempresa para emitir, consultar y administrar comprobantes electronicos ante SUNAT desde cualquier aplicacion.

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Greenter](https://img.shields.io/badge/SUNAT-Greenter-0F766E?style=flat-square)](https://greenter.dev/)
[![License](https://img.shields.io/badge/licencia-propietaria-111827?style=flat-square)](#licencia)

**Produccion:** [apisunatv2.kodevo.es](https://apisunatv2.kodevo.es)  ·  **Documentacion:** [guia completa](documentacion/README.md)

</div>

---

## Una sola API para todo el ciclo tributario

SUNAT API centraliza la operacion de facturacion de tus empresas, sucursales y series en una integracion consistente. Emite un documento, sigue su estado y descarga sus archivos XML, CDR o PDF sin repartir la logica tributaria por todo tu producto.

| Emision | Operacion | Control |
| :--- | :--- | :--- |
| Facturas y boletas | Envio automatico o manual | Dashboard y KPIs |
| Notas de credito y debito | Reenvio y consulta de CPE | Reportes y alertas |
| Guias de remision | Resumenes y anulaciones | Exportacion masiva ZIP |
| Retenciones y percepciones | XML, CDR y PDF | SIRE / Registro de Compras |

## Por que esta API

- **Multiempresa desde el inicio:** cada integracion trabaja con sus credenciales, empresa, sucursales y series.
- **Lista para el flujo real:** soporta documentos pendientes, rechazados, reenvios, pagos y consultas posteriores.
- **Segura por contrato:** autenticacion por `X-Api-Key` y `X-Api-Secret` en cada endpoint protegido.
- **Pensada para crecer:** procesamiento con colas, scheduler y una capa de servicios separada de tu negocio.

## Flujo de integracion

```text
Registrar empresa  ->  Configurar sucursal y serie  ->  Emitir documento
				|                                                     |
				+------------ credenciales de API -------------------+
																															v
												 Consultar estado / XML / CDR / PDF
```

## Quick start

### Requisitos

| Herramienta | Version |
| :--- | :--- |
| PHP | 8.2 o superior |
| Composer | 2.x |
| Node.js | 18.x o superior |
| MySQL | 8.0+ o MariaDB 10.6+ |

### Instalacion local

```bash
git clone https://github.com/yorchavez9/plataform-api-sunat.git
cd plataform-api-sunat

composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
composer dev
```

La API quedara disponible en `http://localhost:8000`. El comando `composer dev` inicia el servidor Laravel, la cola y Vite en paralelo.

### Variables esenciales

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

## Emitir una factura

Una vez registrada la empresa y configurada la serie, crea tu primer comprobante:

```bash
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
```

Puedes agregar `"correlativo": 600` cuando necesites enviar un correlativo especifico. Si lo omites, la API puede continuar el flujo segun la configuracion de la serie. La respuesta incluye la identificacion del documento y permite continuar con sus endpoints de estado, reenvio, XML, CDR y PDF.

## Autenticacion

Todos los endpoints, excepto `/registro` y `/planes`, requieren estas cabeceras:

```http
X-Api-Key: {tu_api_key}
X-Api-Secret: {tu_api_secret}
```

> Mantén `api_secret` exclusivamente en tu backend. Nunca lo expongas en una aplicacion frontend.

## Documentacion

| Guia | Para empezar |
| :--- | :--- |
| [Configuracion inicial](documentacion/01-Configuracion.md) | Empresa, credenciales, certificado, logo, sucursales y series |
| [Facturas](documentacion/04-Facturas.md) | CRUD, envio, XML, CDR, PDF y pagos |
| [Boletas](documentacion/05-Boletas.md) | Emision y ciclo de vida de boletas |
| [Notas de credito](documentacion/06-Notas-credito.md) | Anulaciones, devoluciones y descuentos |
| [Guias de remision](documentacion/10-Guia-remision-RM.md) | Traslado de mercaderia y documentos relacionados |
| [Panel de control](documentacion/15-Panel-de-control.md) | KPIs, alertas, aging y reportes |
| [SIRE](documentacion/17-Sire.md) | Registro de Compras y endpoints SIRE |
| [Despliegue en VPS](documentacion/20-Despliegue-VPS.md) | Nginx, SSL, Supervisor, cron y produccion |
| **[Ver toda la documentacion](documentacion/README.md)** | Mapa completo de rutas, operaciones y novedades SUNAT |

## Stack

```text
Laravel 12  ·  PHP 8.2  ·  MySQL  ·  Greenter  ·  Queues (database)
Inertia 2  ·  React 19  ·  TypeScript  ·  Vite 7  ·  Pest 4
```

## Desarrollo y calidad

```bash
composer dev          # Servidor, queue y Vite
composer test         # Suite de tests y validaciones
composer lint:check   # Estilo PHP
npm run lint:check    # ESLint
npm run types:check   # TypeScript
```

## Licencia

Software propietario. Todos los derechos reservados.

<div align="center">

**SUNAT API** · Integraciones tributarias que siguen el ritmo de tu negocio.

</div>

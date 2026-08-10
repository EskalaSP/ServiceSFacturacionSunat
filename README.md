<div align="center">

# SUNAT API

### Plataforma profesional de facturación electrónica para Perú

API REST multiempresa para emitir, consultar y administrar comprobantes electrónicos ante **SUNAT**.

<p>
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" />
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" />
  <img src="https://img.shields.io/badge/Greenter-SUNAT-0F766E?style=for-the-badge" />
  <img src="https://img.shields.io/badge/Status-Production-16A34A?style=for-the-badge" />
</p>

**Producción:** https://apisunatv2.kodevo.es

**Documentación:** `documentacion/README.md`

</div>

---

## Arquitectura

```text
                Cliente / ERP / POS / E-commerce
                            │
                            ▼
                    SUNAT API (Laravel 12)
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
        ▼                   ▼                   ▼
     Empresas            Series            Sucursales
        │                   │                   │
        └─────────────── Emisión CPE ───────────┘
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
```

---

## Iniciar el proyecto

### Opción recomendada

```bash
composer dev
```

### Opción manual

```bash
# Terminal 1
php artisan serve

# Terminal 2
php artisan queue:listen --tries=1

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

Todos los endpoints protegidos requieren:

```http
X-Api-Key: {API_KEY}
X-Api-Secret: {API_SECRET}
```

---

## Ejemplo

```bash
curl -X POST http://localhost:8000/api/v1/facturas \
  -H "X-Api-Key: TU_API_KEY" \
  -H "X-Api-Secret: TU_API_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"serie":"F001","tipo_moneda":"PEN"}'
```

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

---

## Licencia

**Software propietario. Todos los derechos reservados.**

<div align="center">

**SUNAT API** · Integración profesional con SUNAT para aplicaciones empresariales.

</div>

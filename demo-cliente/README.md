# 📄 Demo Cliente — API SUNAT PRO

> Sistema de ejemplo en **React + Vite + TailwindCSS** para probar la integración con la API SUNAT PRO.
>
> Permite emitir: **Facturas · Boletas · Notas de Crédito · Notas de Débito · Guías de Remisión**.

---

## 🎯 ¿Qué hace este demo?

Un cliente del API puede:
1. Configurar sus credenciales (`api_key` + `api_secret`)
2. Probar la conexión con un click
3. Ver dashboard con KPIs del negocio
4. Emitir los 5 tipos de documentos SUNAT
5. Seleccionar productos de un catálogo pre-cargado (12 productos demo)
6. Seleccionar clientes frecuentes o buscarlos en SUNAT/RENIEC
7. Ver PDF y XML de documentos emitidos
8. Listar historial de documentos con filtros

---

## 📋 Requisitos

- **Node.js 18+** y npm (o pnpm / yarn)
- API SUNAT PRO accesible (producción: `https://api.kodevo.es/sunat-api/api/v1` — o tu URL local)
- Credenciales `api_key` + `api_secret` de una empresa registrada

---

## 🚀 Instalación

### 1. Instalar dependencias

```bash
cd demo-cliente
npm install
```

### 2. Iniciar servidor de desarrollo

```bash
npm run dev
```

Abrirá automáticamente `http://localhost:5173` en tu navegador.

### 3. Configurar credenciales

La primera vez te redirige a **⚙️ Configuración**:

1. **URL Base** — default: `https://api.kodevo.es/sunat-api/api/v1` (producción) o tu URL local
2. **X-Api-Key** — la key de tu empresa (obtenida desde `POST /registro`)
3. **X-Api-Secret** — el secret correspondiente

Clic en **🔌 Probar conexión** para verificar. Si aparece tu empresa → ¡listo!

### 4. Emitir un documento

1. Ir a **🧾 Factura** (o cualquier otro tipo)
2. Clic en **🔍 Seleccionar cliente** → elegir de la lista o buscar por RUC/DNI
3. Clic en **➕ Agregar producto** → elegir del catálogo demo
4. Ajustar cantidad / precio si es necesario
5. Clic en **✅ Emitir**

Verás una respuesta con el número de documento, estado SUNAT y botones para ver PDF/XML.

---

## 📁 Estructura del proyecto

```
demo-cliente/
├── index.html                    ← entry HTML
├── package.json
├── vite.config.js
├── tailwind.config.js
├── postcss.config.js
├── README.md                     ← este archivo
│
└── src/
    ├── main.jsx                  ← entry React
    ├── App.jsx                   ← rutas principales
    ├── index.css                 ← Tailwind + estilos
    │
    ├── api/
    │   └── client.js             ← fetch wrapper con X-Api-Key/Secret
    │
    ├── data/
    │   └── productos.js          ← catálogo demo (12 productos + 5 clientes)
    │
    ├── components/
    │   ├── Layout.jsx            ← sidebar + layout general
    │   ├── ProductPicker.jsx     ← modal buscador de productos
    │   ├── ClientPicker.jsx      ← modal buscador de clientes (local + SUNAT)
    │   ├── ItemsTable.jsx        ← tabla editable con cálculo automático IGV
    │   └── ResponseModal.jsx     ← modal de respuesta tras emitir
    │
    └── pages/
        ├── Settings.jsx          ← configurar api_key/secret
        ├── Dashboard.jsx         ← KPIs + docs recientes
        ├── NewInvoice.jsx        ← emitir factura
        ├── NewBoleta.jsx         ← emitir boleta
        ├── NewCreditNote.jsx     ← emitir nota de crédito
        ├── NewDebitNote.jsx      ← emitir nota de débito
        ├── NewDispatchGuide.jsx  ← emitir guía de remisión
        └── DocumentList.jsx      ← listar documentos por tipo
```

---

## 🧰 Endpoints del API consumidos

| Endpoint | Usado en |
|----------|----------|
| `GET /empresa` | Configuración (test conexión) |
| `GET /buscar-documento?tipo=&numero=` | ClientPicker (búsqueda RUC/DNI) |
| `GET /panel/indicadores` | Dashboard (KPIs) |
| `GET /panel/documentos-recientes` | Dashboard (tabla) |
| `POST /facturas` | NewInvoice |
| `POST /boletas` | NewBoleta |
| `POST /notas-credito` | NewCreditNote |
| `POST /notas-debito` | NewDebitNote |
| `POST /guias-remision` | NewDispatchGuide |
| `GET /{tipo}` | DocumentList |
| `GET /{tipo}/{id}/pdf` | Descargas |
| `GET /{tipo}/{id}/xml` | Descargas |

**Total: 11 endpoints.** Ver documentación completa en `../documentacion/`.

---

## 🎨 Productos pre-cargados

El catálogo demo en `src/data/productos.js` incluye:

| Código | Descripción | Unidad | Precio |
|--------|-------------|--------|--------|
| P001 | Laptop HP Pavilion 15 | NIU | S/ 2,950.00 |
| P002 | Mouse Logitech M170 | NIU | S/ 59.00 |
| P003 | Teclado Mecánico Redragon | NIU | S/ 189.00 |
| P004 | Monitor LG 24" IPS | NIU | S/ 749.00 |
| P005 | Libro Clean Code (exonerado) | NIU | S/ 89.00 |
| P006 | Bolsa Plástica (con ICBPER) | BG | S/ 0.50 |
| P007 | Impresora Epson L3250 | NIU | S/ 899.00 |
| P008 | Cartucho Epson 664 BK | NIU | S/ 45.00 |
| S001 | Consultoría TI (hora) | HUR | S/ 150.00 |
| S002 | Soporte Técnico Mensual | MON | S/ 450.00 |
| S003 | Desarrollo Web (proyecto) | ZZ | S/ 3,500.00 |
| S004 | Capacitación (8 hrs) | DAY | S/ 1,200.00 |

Y 5 clientes demo (3 RUC + 2 DNI). Puedes editar `src/data/productos.js` para agregar más.

---

## ⚙️ Características técnicas

- **React 18** con hooks (sin Redux, estado local simple)
- **React Router 6** para navegación
- **Vite 5** para desarrollo instantáneo (HMR)
- **TailwindCSS 3** para estilos utility-first
- **Fetch API** nativo (sin axios)
- **localStorage** para persistir config
- **Cálculo automático** de IGV (18%) y totales
- **Soporte multi-moneda** (PEN / USD / EUR)
- **Formato Formato 1.3.4** SUNAT respetado
- **Catálogos SUNAT** integrados (Cat. 07, 09, 10, 20)

---

## 🛠️ Build de producción

```bash
npm run build
```

Genera la carpeta `dist/` lista para servir en cualquier servidor web estático (Nginx, Apache, S3, Vercel, Netlify, etc.).

```bash
npm run preview   # ver el build localmente
```

---

## 🔒 Seguridad

⚠️ **Importante:**

1. El `api_secret` se guarda en **localStorage** — está pensado solo como **demo/desarrollo**.
2. En producción, debes tener un **backend intermedio** que almacene el secret del lado del servidor.
3. Nunca expongas el secret en frontend público — usa un proxy backend.
4. El CORS debe estar configurado correctamente en tu API.

---

## 📚 Referencias

- **API completa:** ver `../documentacion/` en el proyecto principal
- **Colección Postman:** `../API SUNAT PRO V2 ⭐⭐⭐⭐⭐.postman_collection.json`
- **Documentación por tipo:**
  - [`01-Configuracion.md`](../documentacion/01-Configuracion.md)
  - [`04-Facturas.md`](../documentacion/04-Facturas.md)
  - [`05-Boletas.md`](../documentacion/05-Boletas.md)
  - [`06-Notas-credito.md`](../documentacion/06-Notas-credito.md)
  - [`07-Notas-debito.md`](../documentacion/07-Notas-debito.md)
  - [`10-Guia-remision-RM.md`](../documentacion/10-Guia-remision-RM.md)
  - [`15-Panel-de-control.md`](../documentacion/15-Panel-de-control.md)

---

## 🎬 Flujo completo de prueba

```
1. npm install           ← una sola vez
2. npm run dev           ← inicia dev server
3. Configura credenciales (primera pantalla)
4. Clic "Probar conexión" → OK
5. Ve al Dashboard
6. Emite una factura:
   - Serie: F001
   - Cliente: ACME (seleccionar de lista)
   - Agregar 1-2 productos
   - Emitir
7. Ver respuesta → número asignado + PDF/XML
8. Ir a "Facturas" → ver en el listado
9. Descargar PDF, ver detalle
```

---

## 🐛 Troubleshooting

### "Failed to fetch" / CORS error

Tu API no permite requests desde `http://localhost:5173`. Agrega en el backend Laravel (`config/cors.php`):

```php
'allowed_origins' => ['http://localhost:5173', 'http://localhost:3000'],
```

### "Error 401 Unauthorized"

Revisa que `api_key` y `api_secret` sean correctos en Configuración.

### "Error 403 Forbidden"

Tu plan no permite el tipo de documento o llegaste al límite mensual. Revisa `/suscripcion/uso`.

### "Error 422 Validation"

El API rechaza datos inválidos — la respuesta incluye detalles de qué campos faltan o están mal.

---

✨ **Demo listo para usar — simple pero funcional.**

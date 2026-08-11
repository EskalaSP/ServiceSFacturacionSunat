# Rutas completas de la API

Inventario de todas las rutas registradas en `routes/api.php`.

## Base y autenticación

- Base URL: `https://tu-dominio.com/api`
- API versionada: `/api/v1`
- Rutas protegidas: requieren `X-Api-Key` y `X-Api-Secret`.
- Rutas públicas: no requieren credenciales API.
- Las rutas `GET` también aceptan `HEAD`.
- Total verificado con `php artisan route:list --path=api`: **195 rutas**.

## 1. Acceso público

| Método | Ruta | Acceso |
|---|---|---|
| POST | `/api/v1/registro` | Público |
| GET | `/api/v1/planes` | Público |
| POST | `/api/v1/credenciales/recuperar` | Público |
| POST | `/api/v1/credenciales/recuperar/verificar` | Público |

## 2. Integración bridge

Estas rutas se usan para la integración con el SaaS principal y usan `X-Bridge-Key`.

| Método | Ruta | Acción |
|---|---|---|
| POST | `/api/bridge/auth` | Generar token |
| POST | `/api/bridge/provision` | Provisionar empresa |
| DELETE | `/api/bridge/tenant` | Eliminar empresa |

## 3. Diagnóstico

Disponible únicamente cuando `APP_DEBUG=true`. No debe exponerse en producción.

| Método | Ruta | Acción |
|---|---|---|
| GET | `/api/debug/lookup` | Probar consulta RUC/DNI contra el servicio externo |

## 4. Empresa y configuración (`/api/v1`)

| Método | Ruta | Acción |
|---|---|---|
| GET | `/api/v1/empresa` | Ver empresa |
| PUT | `/api/v1/empresa` | Actualizar empresa |
| DELETE | `/api/v1/empresa` | Eliminar empresa |
| POST | `/api/v1/empresa/logo` | Subir logo |
| POST | `/api/v1/empresa/certificado` | Subir certificado digital |
| GET | `/api/v1/empresa/credenciales` | Ver credenciales API |
| POST | `/api/v1/empresa/credenciales/regenerar` | Regenerar credenciales |
| GET | `/api/v1/suscripcion` | Ver suscripción |
| POST | `/api/v1/suscripcion` | Crear o actualizar suscripción |
| PUT | `/api/v1/suscripcion/cambiar-plan` | Cambiar plan |
| PUT | `/api/v1/suscripcion/cancelar` | Cancelar suscripción |
| GET | `/api/v1/suscripcion/pagos` | Listar pagos |
| GET | `/api/v1/suscripcion/uso` | Ver uso del plan |
| GET | `/api/v1/sunat/estado` | Estado del circuit breaker de SUNAT |

## 5. Sucursales

| Método | Ruta | Acción |
|---|---|---|
| GET | `/api/v1/sucursales` | Listar sucursales |
| POST | `/api/v1/sucursales` | Crear sucursal |
| GET | `/api/v1/sucursales/{sucursale}` | Ver sucursal |
| PUT/PATCH | `/api/v1/sucursales/{sucursale}` | Actualizar sucursal |
| DELETE | `/api/v1/sucursales/{sucursale}` | Eliminar sucursal |

## 6. Clientes

| Método | Ruta | Acción |
|---|---|---|
| GET | `/api/v1/clientes` | Listar clientes |
| POST | `/api/v1/clientes` | Crear cliente |
| GET | `/api/v1/clientes/{cliente}` | Ver cliente |
| PUT/PATCH | `/api/v1/clientes/{cliente}` | Actualizar cliente |
| DELETE | `/api/v1/clientes/{cliente}` | Eliminar cliente |
| GET | `/api/v1/buscar-documento` | Buscar RUC o DNI |

## 7. Series

| Método | Ruta | Acción |
|---|---|---|
| GET | `/api/v1/series` | Listar series |
| POST | `/api/v1/series` | Crear serie |
| GET | `/api/v1/series/{series}` | Ver serie |
| PUT/PATCH | `/api/v1/series/{series}` | Actualizar serie |
| DELETE | `/api/v1/series/{series}` | Eliminar serie |
| POST | `/api/v1/series/init-defaults` | Crear series predeterminadas |

## 8. Facturas (01)

Las operaciones de creación y envío masivo tienen límite del plan SUNAT.

| Método | Ruta | Acción |
|---|---|---|
| POST | `/api/v1/facturas` | Crear y enviar factura |
| POST | `/api/v1/facturas/masivo` | Crear facturas masivamente |
| GET | `/api/v1/facturas` | Listar facturas |
| GET | `/api/v1/facturas/{id}` | Ver factura |
| PUT | `/api/v1/facturas/{id}` | Actualizar factura |
| GET | `/api/v1/facturas/{id}/xml` | Descargar XML |
| GET | `/api/v1/facturas/{id}/cdr` | Descargar CDR |
| GET | `/api/v1/facturas/{id}/pdf` | Descargar PDF |
| POST | `/api/v1/facturas/{id}/reenviar` | Reenviar factura |
| POST | `/api/v1/facturas/{id}/enviar` | Enviar factura pendiente |
| POST | `/api/v1/facturas/{id}/pagos` | Registrar pago |
| GET | `/api/v1/facturas/{id}/pagos` | Listar pagos |
| DELETE | `/api/v1/facturas/{id}/pagos/{paymentId}` | Eliminar pago |

## 9. Boletas (03)

| Método | Ruta | Acción |
|---|---|---|
| POST | `/api/v1/boletas` | Crear y enviar boleta |
| GET | `/api/v1/boletas` | Listar boletas |
| GET | `/api/v1/boletas/{id}` | Ver boleta |
| PUT | `/api/v1/boletas/{id}` | Actualizar boleta |
| DELETE | `/api/v1/boletas/{id}` | Eliminar boleta |
| GET | `/api/v1/boletas/{id}/xml` | Descargar XML |
| GET | `/api/v1/boletas/{id}/cdr` | Descargar CDR |
| GET | `/api/v1/boletas/{id}/pdf` | Descargar PDF |
| POST | `/api/v1/boletas/{id}/reenviar` | Reenviar boleta |
| POST | `/api/v1/boletas/{id}/enviar` | Enviar boleta pendiente |
| POST | `/api/v1/boletas/{id}/pagos` | Registrar pago |
| GET | `/api/v1/boletas/{id}/pagos` | Listar pagos |
| DELETE | `/api/v1/boletas/{id}/pagos/{paymentId}` | Eliminar pago |

## 10. Notas de crédito (07)

| Método | Ruta | Acción |
|---|---|---|
| POST | `/api/v1/notas-credito` | Crear nota de crédito |
| GET | `/api/v1/notas-credito` | Listar notas |
| GET | `/api/v1/notas-credito/{id}` | Ver nota |
| PUT | `/api/v1/notas-credito/{id}` | Actualizar nota |
| GET | `/api/v1/notas-credito/{id}/xml` | Descargar XML |
| GET | `/api/v1/notas-credito/{id}/cdr` | Descargar CDR |
| GET | `/api/v1/notas-credito/{id}/pdf` | Descargar PDF |
| POST | `/api/v1/notas-credito/{id}/reenviar` | Reenviar nota |
| POST | `/api/v1/notas-credito/{id}/enviar` | Enviar nota pendiente |

## 11. Notas de débito (08)

| Método | Ruta | Acción |
|---|---|---|
| POST | `/api/v1/notas-debito` | Crear nota de débito |
| GET | `/api/v1/notas-debito` | Listar notas |
| GET | `/api/v1/notas-debito/{id}` | Ver nota |
| PUT | `/api/v1/notas-debito/{id}` | Actualizar nota |
| GET | `/api/v1/notas-debito/{id}/xml` | Descargar XML |
| GET | `/api/v1/notas-debito/{id}/cdr` | Descargar CDR |
| GET | `/api/v1/notas-debito/{id}/pdf` | Descargar PDF |
| POST | `/api/v1/notas-debito/{id}/reenviar` | Reenviar nota |
| POST | `/api/v1/notas-debito/{id}/enviar` | Enviar nota pendiente |

## 12. Catálogo de productos SUNAT

`{codigo}` debe tener exactamente 8 dígitos.

| Método | Ruta | Acción |
|---|---|---|
| GET | `/api/v1/catalogos/producto-sunat` | Buscar productos |
| GET | `/api/v1/catalogos/producto-sunat/segmentos` | Listar segmentos |
| GET | `/api/v1/catalogos/producto-sunat/familias` | Listar familias |
| GET | `/api/v1/catalogos/producto-sunat/clases` | Listar clases |
| GET | `/api/v1/catalogos/producto-sunat/productos` | Listar productos |
| GET | `/api/v1/catalogos/producto-sunat/{codigo}` | Ver producto por código |

## 13. Guías de remisión

| Método | Ruta | Acción |
|---|---|---|
| POST | `/api/v1/guias-remision` | Crear guía remitente (tipo 09 por defecto) |
| GET | `/api/v1/guias-remision` | Listar guías |
| GET | `/api/v1/guias-remision/{id}` | Ver guía |
| PUT | `/api/v1/guias-remision/{id}` | Actualizar guía |
| GET | `/api/v1/guias-remision/{id}/pdf` | Descargar PDF |
| GET | `/api/v1/guias-remision/{id}/xml` | Descargar XML |
| GET | `/api/v1/guias-remision/{id}/estado` | Consultar estado |
| POST | `/api/v1/guias-remision/{id}/enviar` | Enviar guía |
| POST | `/api/v1/guias-remision-transportista` | Crear guía transportista (tipo 31) |

## 14. Resúmenes diarios y anulaciones

| Método | Ruta | Acción |
|---|---|---|
| GET | `/api/v1/resumenes` | Listar resúmenes |
| POST | `/api/v1/resumenes` | Crear resumen diario |
| GET | `/api/v1/resumenes/{id}/estado` | Consultar estado |
| GET | `/api/v1/resumenes/{id}/xml` | Descargar XML |
| GET | `/api/v1/resumenes/{id}/cdr` | Descargar CDR |
| POST | `/api/v1/resumenes/{id}/enviar` | Enviar resumen |
| POST | `/api/v1/anulaciones` | Crear comunicación de baja |
| GET | `/api/v1/anulaciones` | Listar anulaciones |
| GET | `/api/v1/anulaciones/{id}` | Ver anulación |
| GET | `/api/v1/anulaciones/{id}/estado` | Consultar estado |
| POST | `/api/v1/anulaciones/{id}/enviar` | Enviar anulación |

## 15. Retenciones, percepciones y reversiones

| Método | Ruta | Acción |
|---|---|---|
| POST | `/api/v1/retenciones` | Crear retención |
| GET | `/api/v1/retenciones` | Listar retenciones |
| GET | `/api/v1/retenciones/{id}` | Ver retención |
| GET | `/api/v1/retenciones/{id}/pdf` | Descargar PDF |
| GET | `/api/v1/retenciones/{id}/xml` | Descargar XML |
| GET | `/api/v1/retenciones/{id}/cdr` | Descargar CDR |
| POST | `/api/v1/retenciones/{id}/enviar` | Enviar retención |
| POST | `/api/v1/percepciones` | Crear percepción |
| GET | `/api/v1/percepciones` | Listar percepciones |
| GET | `/api/v1/percepciones/{id}` | Ver percepción |
| GET | `/api/v1/percepciones/{id}/xml` | Descargar XML |
| GET | `/api/v1/percepciones/{id}/cdr` | Descargar CDR |
| POST | `/api/v1/percepciones/{id}/enviar` | Enviar percepción |
| POST | `/api/v1/reversiones` | Crear reversión de retención o percepción |

## 16. Consultas

| Método | Ruta | Acción |
|---|---|---|
| POST | `/api/v1/consultar-cdr` | Consultar CDR en SUNAT |
| GET | `/api/v1/consultar-cpe` | Consultar estado de CPE |

## 17. Documentos internos

### Cotizaciones

| Método | Ruta | Acción |
|---|---|---|
| POST | `/api/v1/cotizaciones` | Crear cotización |
| GET | `/api/v1/cotizaciones` | Listar cotizaciones |
| GET | `/api/v1/cotizaciones/{id}` | Ver cotización |
| PUT | `/api/v1/cotizaciones/{id}` | Actualizar cotización |
| PUT | `/api/v1/cotizaciones/{id}/estado` | Cambiar estado |
| GET | `/api/v1/cotizaciones/{id}/pdf` | Descargar PDF |

### Notas de venta

| Método | Ruta | Acción |
|---|---|---|
| POST | `/api/v1/notas-venta` | Crear nota de venta |
| GET | `/api/v1/notas-venta` | Listar notas |
| GET | `/api/v1/notas-venta/{id}` | Ver nota |
| PUT | `/api/v1/notas-venta/{id}` | Actualizar nota |
| GET | `/api/v1/notas-venta/{id}/pdf` | Descargar PDF |
| POST | `/api/v1/notas-venta/{id}/pagos` | Registrar pago |
| GET | `/api/v1/notas-venta/{id}/pagos` | Listar pagos |
| DELETE | `/api/v1/notas-venta/{id}/pagos/{paymentId}` | Eliminar pago |

## 18. Panel y dashboard

| Método | Ruta | Acción |
|---|---|---|
| GET | `/api/v1/panel` | Vista completa del mes |
| GET | `/api/v1/panel/indicadores` | Indicadores y crecimiento |
| GET | `/api/v1/panel/estado-sunat` | Estado SUNAT y rechazos |
| GET | `/api/v1/panel/cobranzas` | Antigüedad de cuentas por cobrar |
| GET | `/api/v1/panel/ventas-mensuales` | Ventas de 12 meses |
| GET | `/api/v1/panel/por-sucursal` | Ranking por sucursal |
| GET | `/api/v1/panel/por-moneda` | Desglose PEN/USD |
| GET | `/api/v1/panel/clientes` | Clientes principales y nuevos |
| GET | `/api/v1/panel/productos` | Productos principales |
| GET | `/api/v1/panel/documentos-recientes` | Últimos documentos |
| GET | `/api/v1/panel/alertas` | Alertas operativas |

## 19. Exportación y reportes

| Método | Ruta | Acción |
|---|---|---|
| GET | `/api/v1/comprobantes/exportar-zip` | Exportar XML y/o PDF en ZIP |
| GET | `/api/v1/reportes/registro-ventas` | Registro de ventas |
| GET | `/api/v1/reportes/ventas-consolidado` | Ventas consolidadas |
| GET | `/api/v1/reportes/notas` | Reporte de notas |
| GET | `/api/v1/reportes/cobranzas` | Reporte de cobranzas |
| GET | `/api/v1/reportes/documentos-internos` | Documentos internos |
| GET | `/api/v1/reportes/por-cliente` | Reporte por cliente |
| GET | `/api/v1/reportes/por-sucursal` | Reporte por sucursal |

## 20. SIRE

La activación y desactivación no requieren que SIRE esté activo. El resto requiere SIRE habilitado.

| Método | Ruta | Acción |
|---|---|---|
| POST | `/api/v1/sire/activar` | Activar SIRE |
| POST | `/api/v1/sire/desactivar` | Desactivar SIRE |
| GET | `/api/v1/sire/periodos` | Listar periodos |
| GET | `/api/v1/sire/rce/constancia` | Obtener constancia RCE |
| GET | `/api/v1/sire/rce/{periodo}/propuesta` | Descargar propuesta |
| GET | `/api/v1/sire/rce/{periodo}/resumen` | Ver resumen RCE |
| POST | `/api/v1/sire/rce/{periodo}/aceptar-propuesta` | Aceptar propuesta |
| POST | `/api/v1/sire/rce/{periodo}/registrar-preliminar` | Registrar preliminar |
| POST | `/api/v1/sire/rce/{periodo}/reemplazar-propuesta` | Reemplazar propuesta |
| POST | `/api/v1/sire/rce/{periodo}/no-domiciliados` | Cargar no domiciliados |
| POST | `/api/v1/sire/rce/{periodo}/complementar-propuesta` | Complementar propuesta |
| POST | `/api/v1/sire/rce/{periodo}/ajustes-posteriores/{variant}/cargar` | Cargar ajuste |
| POST | `/api/v1/sire/rce/{periodo}/ajustes-posteriores/{variant}/enviar` | Enviar ajuste |
| GET | `/api/v1/sire/rce/{periodo}/ajustes-posteriores/{variant}/descargar` | Descargar ajuste |
| POST | `/api/v1/sire/rce/{periodo}/ajustes-posteriores/{variant}/eliminar` | Eliminar ajuste |
| GET | `/api/v1/sire/rce/{periodo}/comprobantes` | Listar comprobantes RCE |
| GET | `/api/v1/sire/rce/{periodo}/comprobantes/{id}` | Ver comprobante RCE |
| GET | `/api/v1/sire/rce/{periodo}/reconciliar` | Reconciliar comprobantes |
| POST | `/api/v1/sire/rce/{periodo}/reconciliar-async` | Reconciliar de forma asíncrona |
| GET | `/api/v1/sire/rce/{periodo}/reconciliaciones` | Historial de reconciliaciones |
| GET | `/api/v1/sire/rce/reconciliaciones/{id}` | Ver reconciliación |
| GET | `/api/v1/sire/tickets` | Listar tickets |
| GET | `/api/v1/sire/tickets/{numTicket}` | Ver ticket |
| POST | `/api/v1/sire/tickets/{numTicket}/refrescar` | Refrescar ticket |
| GET | `/api/v1/sire/tickets/{numTicket}/archivo` | Descargar archivo del ticket |

## 21. Aliases simplificados SUNAT

Estas rutas no sustituyen a `/api/v1`; son aliases compatibles bajo `/api/sunat` y requieren `X-Api-Key` y `X-Api-Secret`.

| Método | Ruta | Acción |
|---|---|---|
| GET | `/api/sunat/configuracion` | Ver configuración de empresa |
| POST | `/api/sunat/configuracion` | Actualizar configuración |
| GET | `/api/sunat/facturas` | Listar facturas |
| POST | `/api/sunat/facturas` | Crear factura |
| GET | `/api/sunat/facturas/{id}/pdf` | Descargar PDF |
| GET | `/api/sunat/facturas/{id}/xml` | Descargar XML |
| POST | `/api/sunat/facturas/{id}/anular` | Anular factura |
| GET | `/api/sunat/boletas` | Listar boletas |
| POST | `/api/sunat/boletas` | Crear boleta |
| GET | `/api/sunat/boletas/{id}/pdf` | Descargar PDF |
| GET | `/api/sunat/boletas/{id}/xml` | Descargar XML |
| POST | `/api/sunat/boletas/{id}/anular` | Anular boleta |

## Notas para el cliente

- Los parámetros `{id}`, `{paymentId}`, `{codigo}`, `{periodo}`, `{variant}` y `{numTicket}` son variables de ruta.
- Los endpoints que crean documentos SUNAT llevan el middleware de límite del plan cuando se indica en la configuración de la cuenta.
- Para el detalle de payloads, validaciones y respuestas, consultar los documentos específicos de cada módulo en esta carpeta.
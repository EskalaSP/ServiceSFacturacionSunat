<?php

use App\Http\Controllers\Api\V1\BoletaController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ConsultController;
use App\Http\Controllers\Api\V1\CpeConsultaController;
use App\Http\Controllers\Api\V1\CreditNoteController;
use App\Http\Controllers\Api\V1\DebitNoteController;
use App\Http\Controllers\Api\V1\DispatchGuideController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleNoteController;
use App\Http\Controllers\Api\V1\SerieController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SucursalController;
use App\Http\Controllers\Api\V1\SummaryController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\CredentialRecoveryController;
use App\Http\Controllers\Api\V1\PerceptionController;
use App\Http\Controllers\Api\V1\RetentionController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\ReversionController;
use App\Http\Controllers\Api\V1\VoidedController;
use Illuminate\Support\Facades\Route;

// === Rutas públicas (sin autenticación) ===
Route::prefix('v1')->middleware(['throttle:api'])->group(function () {
    Route::post('registro', [RegisterController::class, 'store']);
    Route::get('planes', [SubscriptionController::class, 'plans']);
    Route::post('credenciales/recuperar', [CredentialRecoveryController::class, 'solicitar']);
    Route::post('credenciales/recuperar/verificar', [CredentialRecoveryController::class, 'verificar']);
});

// === Rutas protegidas (requieren X-Api-Key + X-Api-Secret) ===
Route::prefix('v1')->middleware(['resolve.tenant', 'throttle:api', 'log.api', 'usage.headers'])->group(function () {

    // Estado del circuit breaker de SUNAT (útil para monitoreo y antes de enviar masivos)
    Route::get('sunat/estado', function (\Illuminate\Http\Request $request) {
        $tenant   = $request->get('tenant');
        $env      = $tenant->environment;
        $endpoint = config("facturacion.sunat.{$env}.fe");
        $cb       = new \App\Services\Sunat\SunatCircuitBreaker();
        $stats    = $cb->getStats($endpoint);

        return response()->json([
            'estado'        => 'exito',
            'sunat'         => [
                'disponible'  => $stats['state'] === 'closed',
                'circuit'     => $stats['state'],   // closed | open | half_open
                'fallas'      => $stats['failures'],
                'entorno'     => $env,
            ],
        ]);
    });

    // === Documentos SUNAT (con límite de plan) ===

    // Facturas (01)
    Route::post('facturas', [InvoiceController::class, 'store'])->middleware('check.limit:sunat');
    Route::post('facturas/masivo', [InvoiceController::class, 'masivo'])->middleware('check.limit:sunat');
    Route::get('facturas', [InvoiceController::class, 'index']);
    Route::get('facturas/{id}', [InvoiceController::class, 'show']);
    Route::put('facturas/{id}', [InvoiceController::class, 'update']);
    Route::get('facturas/{id}/xml', [InvoiceController::class, 'xml']);
    Route::get('facturas/{id}/cdr', [InvoiceController::class, 'cdr']);
    Route::get('facturas/{id}/pdf', [InvoiceController::class, 'pdf']);
    Route::post('facturas/{id}/reenviar', [InvoiceController::class, 'resend']);
    Route::post('facturas/{id}/enviar', [InvoiceController::class, 'enviar']);
    Route::post('facturas/{id}/pagos', [PaymentController::class, 'store'])->defaults('docType', 'facturas');
    Route::get('facturas/{id}/pagos', [PaymentController::class, 'index'])->defaults('docType', 'facturas');
    Route::delete('facturas/{id}/pagos/{paymentId}', [PaymentController::class, 'destroy'])->defaults('docType', 'facturas');

    // Boletas (03)
    Route::post('boletas', [BoletaController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('boletas', [BoletaController::class, 'index']);
    Route::get('boletas/{id}', [BoletaController::class, 'show']);
    Route::put('boletas/{id}', [BoletaController::class, 'update']);
    Route::delete('boletas/{id}', [BoletaController::class, 'destroy']);
    Route::get('boletas/{id}/xml', [BoletaController::class, 'xml']);
    Route::get('boletas/{id}/cdr', [BoletaController::class, 'cdr']);
    Route::get('boletas/{id}/pdf', [BoletaController::class, 'pdf']);
    Route::post('boletas/{id}/reenviar', [BoletaController::class, 'resend']);
    Route::post('boletas/{id}/enviar', [BoletaController::class, 'enviar']);
    Route::post('boletas/{id}/pagos', [PaymentController::class, 'store'])->defaults('docType', 'boletas');
    Route::get('boletas/{id}/pagos', [PaymentController::class, 'index'])->defaults('docType', 'boletas');
    Route::delete('boletas/{id}/pagos/{paymentId}', [PaymentController::class, 'destroy'])->defaults('docType', 'boletas');

    // Notas de Crédito (07)
    Route::post('notas-credito', [CreditNoteController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('notas-credito', [CreditNoteController::class, 'index']);
    Route::get('notas-credito/{id}', [CreditNoteController::class, 'show']);
    Route::get('notas-credito/{id}/xml', [CreditNoteController::class, 'xml']);
    Route::get('notas-credito/{id}/cdr', [CreditNoteController::class, 'cdr']);
    Route::get('notas-credito/{id}/pdf', [CreditNoteController::class, 'pdf']);
    Route::put('notas-credito/{id}', [CreditNoteController::class, 'update']);
    Route::post('notas-credito/{id}/reenviar', [CreditNoteController::class, 'resend']);
    Route::post('notas-credito/{id}/enviar', [CreditNoteController::class, 'enviar']);

    // Notas de Débito (08)
    Route::post('notas-debito', [DebitNoteController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('notas-debito', [DebitNoteController::class, 'index']);
    Route::get('notas-debito/{id}', [DebitNoteController::class, 'show']);
    Route::get('notas-debito/{id}/xml', [DebitNoteController::class, 'xml']);
    Route::get('notas-debito/{id}/cdr', [DebitNoteController::class, 'cdr']);
    Route::get('notas-debito/{id}/pdf', [DebitNoteController::class, 'pdf']);
    Route::put('notas-debito/{id}', [DebitNoteController::class, 'update']);
    Route::post('notas-debito/{id}/reenviar', [DebitNoteController::class, 'resend']);
    Route::post('notas-debito/{id}/enviar', [DebitNoteController::class, 'enviar']);

    // Guías de remisión (GRR - tipo 09 por default, GRT - tipo 31 vía payload.tipo_documento='31')
    Route::post('guias-remision', [DispatchGuideController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('guias-remision', [DispatchGuideController::class, 'index']);
    Route::get('guias-remision/{id}', [DispatchGuideController::class, 'show']);
    Route::put('guias-remision/{id}', [DispatchGuideController::class, 'update']);
    Route::get('guias-remision/{id}/pdf', [DispatchGuideController::class, 'pdf']);
    Route::get('guias-remision/{id}/xml', [DispatchGuideController::class, 'xml']);
    Route::get('guias-remision/{id}/estado', [DispatchGuideController::class, 'checkStatus']);
    Route::post('guias-remision/{id}/enviar', [DispatchGuideController::class, 'enviar']);

    // Guía de Remisión Transportista (atajo — forza tipo_documento='31' en el payload)
    Route::post('guias-remision-transportista', [DispatchGuideController::class, 'storeTransportista'])->middleware('check.limit:sunat');

    // Resúmenes diarios
    Route::get('resumenes', [SummaryController::class, 'index']);
    Route::post('resumenes', [SummaryController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('resumenes/{id}/estado', [SummaryController::class, 'checkStatus']);
    Route::get('resumenes/{id}/xml', [SummaryController::class, 'xml']);
    Route::get('resumenes/{id}/cdr', [SummaryController::class, 'cdr']);
    Route::post('resumenes/{id}/enviar', [SummaryController::class, 'enviar']);

    // Comunicaciones de baja
    Route::post('anulaciones', [VoidedController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('anulaciones', [VoidedController::class, 'index']);
    Route::get('anulaciones/{id}', [VoidedController::class, 'show']);
    Route::get('anulaciones/{id}/estado', [VoidedController::class, 'checkStatus']);
    Route::post('anulaciones/{id}/enviar', [VoidedController::class, 'enviar']);

    // Retenciones (20)
    Route::post('retenciones', [RetentionController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('retenciones', [RetentionController::class, 'index']);
    Route::get('retenciones/{id}', [RetentionController::class, 'show']);
    Route::get('retenciones/{id}/pdf', [RetentionController::class, 'pdf']);
    Route::get('retenciones/{id}/xml', [RetentionController::class, 'xml']);
    Route::get('retenciones/{id}/cdr', [RetentionController::class, 'cdr']);
    Route::post('retenciones/{id}/enviar', [RetentionController::class, 'enviar']);

    // Percepciones (40)
    Route::post('percepciones', [PerceptionController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('percepciones', [PerceptionController::class, 'index']);
    Route::get('percepciones/{id}', [PerceptionController::class, 'show']);
    Route::get('percepciones/{id}/xml', [PerceptionController::class, 'xml']);
    Route::get('percepciones/{id}/cdr', [PerceptionController::class, 'cdr']);
    Route::post('percepciones/{id}/enviar', [PerceptionController::class, 'enviar']);

    // Reversión (RR) — Anulación de retenciones y percepciones
    Route::post('reversiones', [ReversionController::class, 'store'])->middleware('check.limit:sunat');

    // Consultar CDR en SUNAT
    Route::post('consultar-cdr', [ConsultController::class, 'cdrStatus']);

    // Consultar CPE (estado integrado de comprobante) en SUNAT
    Route::get('consultar-cpe', [CpeConsultaController::class, 'consultar']);

    // Buscar RUC/DNI (local + SUNAT/RENIEC)
    Route::get('buscar-documento', [ConsultController::class, 'lookupDocument']);

    // Empresa (perfil)
    Route::get('empresa', [TenantController::class, 'show']);
    Route::put('empresa', [TenantController::class, 'update']);
    Route::delete('empresa', [TenantController::class, 'destroy']);
    Route::post('empresa/logo', [TenantController::class, 'uploadLogo']);
    Route::post('empresa/certificado', [TenantController::class, 'uploadCertificate']);
    Route::get('empresa/credenciales', [TenantController::class, 'credenciales']);
    Route::post('empresa/credenciales/regenerar', [TenantController::class, 'regenerarCredenciales']);

    // Sucursales
    Route::apiResource('sucursales', SucursalController::class);

    // Clientes
    Route::apiResource('clientes', ClientController::class);

    // Series
    Route::apiResource('series', SerieController::class);
    Route::post('series/init-defaults', [SerieController::class, 'initDefaults']);

    // === Documentos internos (sin SUNAT) ===

    // Cotizaciones
    Route::post('cotizaciones', [QuotationController::class, 'store'])->middleware('check.limit:internal');
    Route::get('cotizaciones', [QuotationController::class, 'index']);
    Route::get('cotizaciones/{id}', [QuotationController::class, 'show']);
    Route::put('cotizaciones/{id}', [QuotationController::class, 'update']);
    Route::put('cotizaciones/{id}/estado', [QuotationController::class, 'updateStatus']);
    Route::get('cotizaciones/{id}/pdf', [QuotationController::class, 'pdf']);

    // Notas de Venta
    Route::post('notas-venta', [SaleNoteController::class, 'store'])->middleware('check.limit:internal');
    Route::get('notas-venta', [SaleNoteController::class, 'index']);
    Route::get('notas-venta/{id}', [SaleNoteController::class, 'show']);
    Route::put('notas-venta/{id}', [SaleNoteController::class, 'update']);
    Route::get('notas-venta/{id}/pdf', [SaleNoteController::class, 'pdf']);
    Route::post('notas-venta/{id}/pagos', [PaymentController::class, 'store'])->defaults('docType', 'notas-venta');
    Route::get('notas-venta/{id}/pagos', [PaymentController::class, 'index'])->defaults('docType', 'notas-venta');
    Route::delete('notas-venta/{id}/pagos/{paymentId}', [PaymentController::class, 'destroy'])->defaults('docType', 'notas-venta');

    // === Panel / Dashboard ===
    Route::prefix('panel')->controller(\App\Http\Controllers\Api\V1\DashboardController::class)->group(function () {
        Route::get('/', 'index');                          // Vista completa del mes
        Route::get('indicadores', 'indicadores');          // KPIs: hoy/semana/mes/año + crecimiento
        Route::get('estado-sunat', 'estadoSunat');         // Breakdown SUNAT + rechazos recientes
        Route::get('cobranzas', 'cobranzas');              // Aging de cuentas por cobrar
        Route::get('ventas-mensuales', 'ventasMensuales'); // Gráfico 12 meses vs año anterior
        Route::get('por-sucursal', 'porSucursal');         // Ranking por sucursal
        Route::get('por-moneda', 'porMoneda');             // Desglose PEN/USD
        Route::get('clientes', 'clientes');                // Top + nuevos + recurrentes
        Route::get('productos', 'productos');              // Top venta/cantidad + tipo IGV
        Route::get('documentos-recientes', 'documentosRecientes'); // Feed últimos 20
        Route::get('alertas', 'alertas');                  // Rechazos, vencimientos, series
    });

    // === Exportación masiva ===
    Route::get('comprobantes/exportar-zip', [ExportController::class, 'zip']);

    // === Reportes ===
    Route::get('reportes/registro-ventas', [ReportController::class, 'registroVentas']);
    Route::get('reportes/ventas-consolidado', [ReportController::class, 'ventasConsolidado']);
    Route::get('reportes/notas', [ReportController::class, 'notas']);
    Route::get('reportes/cobranzas', [ReportController::class, 'cobranzas']);
    Route::get('reportes/documentos-internos', [ReportController::class, 'documentosInternos']);
    Route::get('reportes/por-cliente', [ReportController::class, 'porCliente']);
    Route::get('reportes/por-sucursal', [ReportController::class, 'porSucursal']);

    // === SIRE (Sistema Integrado de Registros Electrónicos) ===
    Route::prefix('sire')->group(function () {
        // Activación — NO requiere sire.enabled (es el que activa)
        Route::post('activar',    [\App\Http\Controllers\Api\V1\Sire\SireActivacionController::class, 'activar']);
        Route::post('desactivar', [\App\Http\Controllers\Api\V1\Sire\SireActivacionController::class, 'desactivar']);

        // Resto de endpoints requieren SIRE activado
        Route::middleware('sire.enabled')->group(function () {
            Route::get('periodos', [\App\Http\Controllers\Api\V1\Sire\SirePeriodoController::class, 'index']);

            // === RCE ===
            Route::prefix('rce')->group(function () {
                Route::get('constancia', [\App\Http\Controllers\Api\V1\Sire\SireRceController::class, 'constancia']);

                Route::prefix('{periodo}')->group(function () {
                    Route::get('propuesta',            [\App\Http\Controllers\Api\V1\Sire\SireRceController::class, 'propuesta']);
                    Route::get('resumen',              [\App\Http\Controllers\Api\V1\Sire\SireRceController::class, 'resumen']);
                    Route::post('aceptar-propuesta',   [\App\Http\Controllers\Api\V1\Sire\SireRceController::class, 'aceptarPropuesta']);
                    Route::post('registrar-preliminar',[\App\Http\Controllers\Api\V1\Sire\SireRceController::class, 'registrarPreliminar']);

                    // Uploads TUS (5.3, 5.5, 5.6)
                    Route::post('reemplazar-propuesta',   [\App\Http\Controllers\Api\V1\Sire\SireUploadController::class, 'reemplazarPropuesta']);
                    Route::post('no-domiciliados',         [\App\Http\Controllers\Api\V1\Sire\SireUploadController::class, 'noDomiciliados']);
                    Route::post('complementar-propuesta',  [\App\Http\Controllers\Api\V1\Sire\SireUploadController::class, 'complementarPropuesta']);

                    // Ajustes Posteriores (5.18-5.29 + 5.45-5.48) — 4 variants × 4 acciones = 16 combinaciones
                    Route::prefix('ajustes-posteriores/{variant}')->group(function () {
                        Route::post('cargar',    [\App\Http\Controllers\Api\V1\Sire\SireAjustesController::class, 'cargar']);
                        Route::post('enviar',    [\App\Http\Controllers\Api\V1\Sire\SireAjustesController::class, 'enviar']);
                        Route::get('descargar',  [\App\Http\Controllers\Api\V1\Sire\SireAjustesController::class, 'descargar']);
                        Route::post('eliminar',  [\App\Http\Controllers\Api\V1\Sire\SireAjustesController::class, 'eliminar']);
                    });

                    Route::get('comprobantes',        [\App\Http\Controllers\Api\V1\Sire\SireComprobanteController::class, 'index']);
                    Route::get('comprobantes/{id}',   [\App\Http\Controllers\Api\V1\Sire\SireComprobanteController::class, 'show']);

                    // Reconciliación
                    Route::get('reconciliar',         [\App\Http\Controllers\Api\V1\Sire\SireReconciliationController::class, 'reconciliar']);
                    Route::post('reconciliar-async',  [\App\Http\Controllers\Api\V1\Sire\SireReconciliationController::class, 'reconciliarAsync']);
                    Route::get('reconciliaciones',    [\App\Http\Controllers\Api\V1\Sire\SireReconciliationController::class, 'historial']);
                });

                Route::get('reconciliaciones/{id}', [\App\Http\Controllers\Api\V1\Sire\SireReconciliationController::class, 'show']);
            });

            // === Tickets ===
            Route::get('tickets',                        [\App\Http\Controllers\Api\V1\Sire\SireTicketController::class, 'index']);
            Route::get('tickets/{numTicket}',            [\App\Http\Controllers\Api\V1\Sire\SireTicketController::class, 'show']);
            Route::post('tickets/{numTicket}/refrescar', [\App\Http\Controllers\Api\V1\Sire\SireTicketController::class, 'refresh']);
            Route::get('tickets/{numTicket}/archivo',    [\App\Http\Controllers\Api\V1\Sire\SireTicketController::class, 'archivo']);
        });
    });

    // === Suscripciones y Billing ===
    Route::get('suscripcion', [SubscriptionController::class, 'show']);
    Route::post('suscripcion', [SubscriptionController::class, 'store']);
    Route::put('suscripcion/cambiar-plan', [SubscriptionController::class, 'changePlan']);
    Route::put('suscripcion/cancelar', [SubscriptionController::class, 'cancel']);
    Route::get('suscripcion/pagos', [SubscriptionController::class, 'payments']);
    Route::get('suscripcion/uso', [SubscriptionController::class, 'usage']);
});

// ── Diagnóstico temporal (solo con APP_DEBUG=true) ───────────────────────────
if (config('app.debug')) {
    Route::get('debug/lookup', function (\Illuminate\Http\Request $request) {
        $numero = $request->input('numero', '20207845044');
        $tipo   = $request->input('tipo', '6');
        $token  = config('facturacion.lookup.token');
        $base   = config('facturacion.lookup.base_url');

        $endpoint = $tipo === '6' ? "{$base}/v2/sunat/ruc" : "{$base}/v2/reniec/dni";
        $http = \Illuminate\Support\Facades\Http::timeout(10)->acceptJson();

        $results = [];

        // Formato 1: Authorization: Bearer {token}
        try {
            $r = $http->withToken($token)->get($endpoint, ['numero' => $numero]);
            $results['bearer'] = ['status' => $r->status(), 'body' => $r->json() ?? $r->body()];
        } catch (\Throwable $e) { $results['bearer'] = ['error' => $e->getMessage()]; }

        // Formato 2: Authorization: {token} (sin "Bearer")
        try {
            $r = $http->withHeaders(['Authorization' => $token])->get($endpoint, ['numero' => $numero]);
            $results['raw_header'] = ['status' => $r->status(), 'body' => $r->json() ?? $r->body()];
        } catch (\Throwable $e) { $results['raw_header'] = ['error' => $e->getMessage()]; }

        // Formato 3: ?token={token} en query string (v2)
        try {
            $r = $http->get($endpoint, ['numero' => $numero, 'token' => $token]);
            $results['query_param_v2'] = ['status' => $r->status(), 'body' => $r->json() ?? $r->body()];
        } catch (\Throwable $e) { $results['query_param_v2'] = ['error' => $e->getMessage()]; }

        // Formato 4: v1 endpoint con token como query param (era lo que funcionaba antes)
        $endpointV1 = $tipo === '6' ? "{$base}/v1/ruc" : "{$base}/v1/dni";
        try {
            $r = $http->get($endpointV1, ['numero' => $numero, 'token' => $token]);
            $results['v1_query_param'] = ['endpoint' => $endpointV1, 'status' => $r->status(), 'body' => $r->json() ?? $r->body()];
        } catch (\Throwable $e) { $results['v1_query_param'] = ['error' => $e->getMessage()]; }

        // Formato 5: v1 endpoint con Bearer
        try {
            $r = $http->withToken($token)->get($endpointV1, ['numero' => $numero]);
            $results['v1_bearer'] = ['endpoint' => $endpointV1, 'status' => $r->status(), 'body' => $r->json() ?? $r->body()];
        } catch (\Throwable $e) { $results['v1_bearer'] = ['error' => $e->getMessage()]; }

        return response()->json([
            'token_preview' => $token ? substr($token, 0, 12) . '...' : '(vacío)',
            'endpoint_v2'   => $endpoint . '?numero=' . $numero,
            'results'       => $results,
        ]);
    });
}

// ── API Bridge para el SaaS principal ───────────────────────────────────────
// Protegido con X-Bridge-Key (SAAS_BRIDGE_SECRET).
Route::post('bridge/auth',      [\App\Http\Controllers\Auth\SaasAuthController::class, 'generateToken']);
Route::post('bridge/provision', [\App\Http\Controllers\Auth\SaasAuthController::class, 'provision']);
Route::delete('bridge/tenant',  [\App\Http\Controllers\Auth\SaasAuthController::class, 'deleteTenant']);

// Alias simplificados sobre los endpoints V1 existentes.
// Usan la misma autenticación X-Api-Key + X-Api-Secret.
Route::prefix('sunat')->middleware(['resolve.tenant', 'throttle:api'])->group(function () {

    // Configuración
    Route::get('configuracion',  [\App\Http\Controllers\Api\V1\TenantController::class, 'show']);
    Route::post('configuracion', [\App\Http\Controllers\Api\V1\TenantController::class, 'update']);

    // Comprobantes (facturas + boletas unificados)
    Route::get('facturas',               [\App\Http\Controllers\Api\V1\InvoiceController::class, 'index']);
    Route::post('facturas',              [\App\Http\Controllers\Api\V1\InvoiceController::class, 'store']);
    Route::get('facturas/{id}/pdf',      [\App\Http\Controllers\Api\V1\InvoiceController::class, 'pdf']);
    Route::get('facturas/{id}/xml',      [\App\Http\Controllers\Api\V1\InvoiceController::class, 'xml']);
    Route::post('facturas/{id}/anular',  [\App\Http\Controllers\Api\V1\VoidedController::class, 'store']);

    Route::get('boletas',                [\App\Http\Controllers\Api\V1\BoletaController::class, 'index']);
    Route::post('boletas',               [\App\Http\Controllers\Api\V1\BoletaController::class, 'store']);
    Route::get('boletas/{id}/pdf',       [\App\Http\Controllers\Api\V1\BoletaController::class, 'pdf']);
    Route::get('boletas/{id}/xml',       [\App\Http\Controllers\Api\V1\BoletaController::class, 'xml']);
    Route::post('boletas/{id}/anular',   [\App\Http\Controllers\Api\V1\VoidedController::class, 'store']);
});

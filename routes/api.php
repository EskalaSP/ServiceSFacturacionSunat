<?php

use App\Http\Controllers\Api\V1\BoletaController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ConsultController;
use App\Http\Controllers\Api\V1\CreditNoteController;
use App\Http\Controllers\Api\V1\DebitNoteController;
use App\Http\Controllers\Api\V1\DispatchGuideController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\SaleNoteController;
use App\Http\Controllers\Api\V1\SerieController;
use App\Http\Controllers\Api\V1\SucursalController;
use App\Http\Controllers\Api\V1\SummaryController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\VoidedController;
use Illuminate\Support\Facades\Route;

// === Rutas públicas (sin autenticación) ===
Route::prefix('v1')->middleware(['throttle:api'])->group(function () {
    Route::post('register', [RegisterController::class, 'store']);
    Route::get('plans', [SubscriptionController::class, 'plans']);
});

// === Rutas protegidas (requieren X-Api-Key + X-Api-Secret) ===
Route::prefix('v1')->middleware(['resolve.tenant', 'throttle:api', 'log.api', 'usage.headers'])->group(function () {

    // === Documentos SUNAT (con límite de plan) ===

    // Facturas (01)
    Route::post('invoices', [InvoiceController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/{id}', [InvoiceController::class, 'show']);
    Route::put('invoices/{id}', [InvoiceController::class, 'update']);
    Route::get('invoices/{id}/xml', [InvoiceController::class, 'xml']);
    Route::get('invoices/{id}/cdr', [InvoiceController::class, 'cdr']);
    Route::get('invoices/{id}/pdf', [InvoiceController::class, 'pdf']);
    Route::post('invoices/{id}/resend', [InvoiceController::class, 'resend']);
    Route::post('invoices/{id}/payments', [PaymentController::class, 'store'])->defaults('docType', 'invoices');
    Route::get('invoices/{id}/payments', [PaymentController::class, 'index'])->defaults('docType', 'invoices');
    Route::delete('invoices/{id}/payments/{paymentId}', [PaymentController::class, 'destroy'])->defaults('docType', 'invoices');

    // Boletas (03)
    Route::post('boletas', [BoletaController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('boletas', [BoletaController::class, 'index']);
    Route::get('boletas/{id}', [BoletaController::class, 'show']);
    Route::put('boletas/{id}', [BoletaController::class, 'update']);
    Route::get('boletas/{id}/xml', [BoletaController::class, 'xml']);
    Route::get('boletas/{id}/cdr', [BoletaController::class, 'cdr']);
    Route::get('boletas/{id}/pdf', [BoletaController::class, 'pdf']);
    Route::post('boletas/{id}/resend', [BoletaController::class, 'resend']);
    Route::post('boletas/{id}/payments', [PaymentController::class, 'store'])->defaults('docType', 'boletas');
    Route::get('boletas/{id}/payments', [PaymentController::class, 'index'])->defaults('docType', 'boletas');
    Route::delete('boletas/{id}/payments/{paymentId}', [PaymentController::class, 'destroy'])->defaults('docType', 'boletas');

    // Notas de Crédito (07)
    Route::post('credit-notes', [CreditNoteController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('credit-notes', [CreditNoteController::class, 'index']);
    Route::get('credit-notes/{id}', [CreditNoteController::class, 'show']);
    Route::get('credit-notes/{id}/xml', [CreditNoteController::class, 'xml']);
    Route::get('credit-notes/{id}/cdr', [CreditNoteController::class, 'cdr']);
    Route::get('credit-notes/{id}/pdf', [CreditNoteController::class, 'pdf']);
    Route::post('credit-notes/{id}/resend', [CreditNoteController::class, 'resend']);

    // Notas de Débito (08)
    Route::post('debit-notes', [DebitNoteController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('debit-notes', [DebitNoteController::class, 'index']);
    Route::get('debit-notes/{id}', [DebitNoteController::class, 'show']);
    Route::get('debit-notes/{id}/xml', [DebitNoteController::class, 'xml']);
    Route::get('debit-notes/{id}/cdr', [DebitNoteController::class, 'cdr']);
    Route::get('debit-notes/{id}/pdf', [DebitNoteController::class, 'pdf']);
    Route::post('debit-notes/{id}/resend', [DebitNoteController::class, 'resend']);

    // Guías de remisión
    Route::post('dispatch-guides', [DispatchGuideController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('dispatch-guides', [DispatchGuideController::class, 'index']);
    Route::get('dispatch-guides/{id}', [DispatchGuideController::class, 'show']);
    Route::put('dispatch-guides/{id}', [DispatchGuideController::class, 'update']);
    Route::get('dispatch-guides/{id}/pdf', [DispatchGuideController::class, 'pdf']);
    Route::get('dispatch-guides/{id}/status', [DispatchGuideController::class, 'checkStatus']);

    // Resúmenes diarios
    Route::get('summaries', [SummaryController::class, 'index']);
    Route::post('summaries', [SummaryController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('summaries/{id}/status', [SummaryController::class, 'checkStatus']);
    Route::get('summaries/{id}/xml', [SummaryController::class, 'xml']);
    Route::get('summaries/{id}/cdr', [SummaryController::class, 'cdr']);

    // Comunicaciones de baja
    Route::post('voided', [VoidedController::class, 'store'])->middleware('check.limit:sunat');
    Route::get('voided/{id}/status', [VoidedController::class, 'checkStatus']);

    // Consultar CDR en SUNAT
    Route::post('consult-cdr', [ConsultController::class, 'cdrStatus']);

    // Buscar RUC/DNI (local + SUNAT/RENIEC)
    Route::get('lookup-document', [ConsultController::class, 'lookupDocument']);

    // Tenant (perfil)
    Route::get('tenant', [TenantController::class, 'show']);
    Route::put('tenant', [TenantController::class, 'update']);
    Route::post('tenant/logo', [TenantController::class, 'uploadLogo']);
    Route::post('tenant/certificate', [TenantController::class, 'uploadCertificate']);

    // Sucursales
    Route::apiResource('sucursales', SucursalController::class);

    // Clientes
    Route::apiResource('clients', ClientController::class);

    // Series
    Route::apiResource('series', SerieController::class)->except(['destroy']);

    // === Documentos internos (sin SUNAT) ===

    // Cotizaciones
    Route::post('quotations', [QuotationController::class, 'store'])->middleware('check.limit:internal');
    Route::get('quotations', [QuotationController::class, 'index']);
    Route::get('quotations/{id}', [QuotationController::class, 'show']);
    Route::put('quotations/{id}/status', [QuotationController::class, 'updateStatus']);
    Route::get('quotations/{id}/pdf', [QuotationController::class, 'pdf']);

    // Notas de Venta
    Route::post('sale-notes', [SaleNoteController::class, 'store'])->middleware('check.limit:internal');
    Route::get('sale-notes', [SaleNoteController::class, 'index']);
    Route::get('sale-notes/{id}', [SaleNoteController::class, 'show']);
    Route::get('sale-notes/{id}/pdf', [SaleNoteController::class, 'pdf']);
    Route::post('sale-notes/{id}/payments', [PaymentController::class, 'store'])->defaults('docType', 'sale-notes');
    Route::get('sale-notes/{id}/payments', [PaymentController::class, 'index'])->defaults('docType', 'sale-notes');
    Route::delete('sale-notes/{id}/payments/{paymentId}', [PaymentController::class, 'destroy'])->defaults('docType', 'sale-notes');

    // === Reportes ===
    Route::get('reports/registro-ventas', [ReportController::class, 'registroVentas']);
    Route::get('reports/ventas-consolidado', [ReportController::class, 'ventasConsolidado']);
    Route::get('reports/notas', [ReportController::class, 'notas']);
    Route::get('reports/cobranzas', [ReportController::class, 'cobranzas']);
    Route::get('reports/documentos-internos', [ReportController::class, 'documentosInternos']);
    Route::get('reports/por-cliente', [ReportController::class, 'porCliente']);
    Route::get('reports/por-sucursal', [ReportController::class, 'porSucursal']);

    // === Suscripciones y Billing ===
    Route::get('subscription', [SubscriptionController::class, 'show']);
    Route::post('subscription', [SubscriptionController::class, 'store']);
    Route::put('subscription/change-plan', [SubscriptionController::class, 'changePlan']);
    Route::put('subscription/cancel', [SubscriptionController::class, 'cancel']);
    Route::get('subscription/payments', [SubscriptionController::class, 'payments']);
    Route::get('subscription/usage', [SubscriptionController::class, 'usage']);
});

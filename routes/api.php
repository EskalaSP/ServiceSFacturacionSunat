<?php

use App\Http\Controllers\Api\V1\BoletaController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ConsultController;
use App\Http\Controllers\Api\V1\CreditNoteController;
use App\Http\Controllers\Api\V1\DebitNoteController;
use App\Http\Controllers\Api\V1\DispatchGuideController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\SerieController;
use App\Http\Controllers\Api\V1\SucursalController;
use App\Http\Controllers\Api\V1\SummaryController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\VoidedController;
use Illuminate\Support\Facades\Route;

// === Ruta pública (sin autenticación) ===
Route::prefix('v1')->middleware(['throttle:api'])->group(function () {
    Route::post('register', [RegisterController::class, 'store']);
});

// === Rutas protegidas (requieren X-Api-Key + X-Api-Secret) ===
Route::prefix('v1')->middleware(['resolve.tenant', 'throttle:api', 'log.api'])->group(function () {

    // === Nuevas rutas por tipo de comprobante ===

    // Facturas (01)
    Route::post('invoices', [InvoiceController::class, 'store'])->middleware('check.limit');
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/{id}', [InvoiceController::class, 'show']);
    Route::get('invoices/{id}/xml', [InvoiceController::class, 'xml']);
    Route::get('invoices/{id}/cdr', [InvoiceController::class, 'cdr']);
    Route::get('invoices/{id}/pdf', [InvoiceController::class, 'pdf']);

    // Boletas (03)
    Route::post('boletas', [BoletaController::class, 'store'])->middleware('check.limit');
    Route::get('boletas', [BoletaController::class, 'index']);
    Route::get('boletas/{id}', [BoletaController::class, 'show']);
    Route::get('boletas/{id}/xml', [BoletaController::class, 'xml']);
    Route::get('boletas/{id}/cdr', [BoletaController::class, 'cdr']);
    Route::get('boletas/{id}/pdf', [BoletaController::class, 'pdf']);

    // Notas de Crédito (07)
    Route::post('credit-notes', [CreditNoteController::class, 'store'])->middleware('check.limit');
    Route::get('credit-notes', [CreditNoteController::class, 'index']);
    Route::get('credit-notes/{id}', [CreditNoteController::class, 'show']);
    Route::get('credit-notes/{id}/xml', [CreditNoteController::class, 'xml']);
    Route::get('credit-notes/{id}/cdr', [CreditNoteController::class, 'cdr']);
    Route::get('credit-notes/{id}/pdf', [CreditNoteController::class, 'pdf']);

    // Notas de Débito (08)
    Route::post('debit-notes', [DebitNoteController::class, 'store'])->middleware('check.limit');
    Route::get('debit-notes', [DebitNoteController::class, 'index']);
    Route::get('debit-notes/{id}', [DebitNoteController::class, 'show']);
    Route::get('debit-notes/{id}/xml', [DebitNoteController::class, 'xml']);
    Route::get('debit-notes/{id}/cdr', [DebitNoteController::class, 'cdr']);
    Route::get('debit-notes/{id}/pdf', [DebitNoteController::class, 'pdf']);

    // Guías de remisión
    Route::post('dispatch-guides', [DispatchGuideController::class, 'store'])->middleware('check.limit');
    Route::get('dispatch-guides', [DispatchGuideController::class, 'index']);
    Route::get('dispatch-guides/{id}', [DispatchGuideController::class, 'show']);
    Route::get('dispatch-guides/{id}/pdf', [DispatchGuideController::class, 'pdf']);
    Route::get('dispatch-guides/{id}/status', [DispatchGuideController::class, 'checkStatus']);

    // Resúmenes diarios
    Route::post('summaries', [SummaryController::class, 'store']);
    Route::get('summaries/{id}/status', [SummaryController::class, 'checkStatus']);

    // Comunicaciones de baja
    Route::post('voided', [VoidedController::class, 'store']);
    Route::get('voided/{id}/status', [VoidedController::class, 'checkStatus']);

    // Consultar CDR en SUNAT
    Route::post('consult-cdr', [ConsultController::class, 'cdrStatus']);

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
});

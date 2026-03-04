<?php

use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ConsultController;
use App\Http\Controllers\Api\V1\DispatchGuideController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\SerieController;
use App\Http\Controllers\Api\V1\SummaryController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\VoidedController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['resolve.tenant', 'log.api'])->group(function () {

    // Documentos (facturas, boletas, NC, ND) — con check de límite en creación
    Route::post('documents', [DocumentController::class, 'store'])->middleware('check.limit');
    Route::get('documents', [DocumentController::class, 'index']);
    Route::get('documents/{id}', [DocumentController::class, 'show']);
    Route::get('documents/{id}/xml', [DocumentController::class, 'xml']);
    Route::get('documents/{id}/cdr', [DocumentController::class, 'cdr']);
    Route::get('documents/{id}/pdf', [DocumentController::class, 'pdf']);

    // Guías de remisión
    Route::post('dispatch-guides', [DispatchGuideController::class, 'store'])->middleware('check.limit');
    Route::get('dispatch-guides', [DispatchGuideController::class, 'index']);
    Route::get('dispatch-guides/{id}', [DispatchGuideController::class, 'show']);
    Route::get('dispatch-guides/{id}/status', [DispatchGuideController::class, 'checkStatus']);

    // Resúmenes diarios
    Route::post('summaries', [SummaryController::class, 'store']);
    Route::get('summaries/{ticket}/status', [SummaryController::class, 'checkStatus']);

    // Comunicaciones de baja
    Route::post('voided', [VoidedController::class, 'store']);
    Route::get('voided/{ticket}/status', [VoidedController::class, 'checkStatus']);

    // Consultar CDR en SUNAT
    Route::post('consult-cdr', [ConsultController::class, 'cdrStatus']);

    // Tenant (perfil)
    Route::get('tenant', [TenantController::class, 'show']);
    Route::put('tenant', [TenantController::class, 'update']);

    // Clientes
    Route::apiResource('clients', ClientController::class);

    // Series
    Route::apiResource('series', SerieController::class)->except(['destroy']);
});

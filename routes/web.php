<?php

use App\Http\Controllers\Auth\SaasAuthController;
use App\Http\Controllers\Web\Sunat\BuscarClienteController;
use App\Http\Controllers\Web\Sunat\ClienteController;
use App\Http\Controllers\Web\Sunat\ConfiguracionController;
use App\Http\Controllers\Web\Sunat\CotizacionController;
use App\Http\Controllers\Web\Sunat\DashboardController;
use App\Http\Controllers\Web\Sunat\FacturaController;
use App\Http\Controllers\Web\Sunat\HistorialController;
use App\Http\Controllers\Web\Sunat\MiApiKeyController;
use App\Http\Controllers\Web\Sunat\NotaCreditoController;
use App\Http\Controllers\Web\Sunat\NotaDebitoController;
use Illuminate\Support\Facades\Route;

// ── API root status ─────────────────────────────────────────────────────────
Route::get('/', function () {
    return response()->json([
        'api'     => 'SUNAT API',
        'version' => 'v1',
        'status'  => 'ok',
        'docs'    => url('/api/v1'),
    ]);
})->name('home');

// ── Auth Bridge (entrada desde el SaaS principal) ───────────────────────────
// El SaaS principal genera un token con POST /api/bridge/auth y redirige
// al usuario a esta URL con el token firmado.
Route::get('/auth/from-saas', [SaasAuthController::class, 'login'])->name('auth.from-saas');

// ── Módulo SUNAT (requiere autenticación) ────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // /sunat → redirect inteligente (configuracion si no tiene SOL, facturas si ya tiene)
    Route::prefix('sunat')->name('sunat.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/configuracion', [ConfiguracionController::class, 'edit'])->name('configuracion');
        Route::put('/configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');
        Route::post('/configuracion/probar', [ConfiguracionController::class, 'probarConexion'])->name('configuracion.probar');

        Route::get('/facturas/nueva', [FacturaController::class, 'create'])->name('facturas.create');
        Route::post('/facturas', [FacturaController::class, 'store'])->name('facturas.store');

        Route::get('/historial', [HistorialController::class, 'index'])->name('historial');
        Route::get('/historial/{tipo}/{id}/pdf', [HistorialController::class, 'pdf'])->name('historial.pdf');
        Route::get('/historial/{tipo}/{id}/xml', [HistorialController::class, 'xml'])->name('historial.xml');

        Route::get('/nota-credito/nueva', [NotaCreditoController::class, 'create'])->name('nota-credito.create');
        Route::post('/nota-credito', [NotaCreditoController::class, 'store'])->name('nota-credito.store');

        Route::get('/nota-debito/nueva', [NotaDebitoController::class, 'create'])->name('nota-debito.create');
        Route::post('/nota-debito', [NotaDebitoController::class, 'store'])->name('nota-debito.store');

        Route::get('/cotizaciones', [CotizacionController::class, 'index'])->name('cotizaciones');
        Route::get('/cotizaciones/nueva', [CotizacionController::class, 'create'])->name('cotizaciones.create');
        Route::post('/cotizaciones', [CotizacionController::class, 'store'])->name('cotizaciones.store');
        Route::patch('/cotizaciones/{id}/estado', [CotizacionController::class, 'updateEstado'])->name('cotizaciones.estado');
        Route::post('/cotizaciones/{id}/convertir', [CotizacionController::class, 'convertir'])->name('cotizaciones.convertir');
        Route::delete('/cotizaciones/{id}', [CotizacionController::class, 'destroy'])->name('cotizaciones.destroy');

        Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes');
        Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
        Route::put('/clientes/{id}', [ClienteController::class, 'update'])->name('clientes.update');
        Route::delete('/clientes/{id}', [ClienteController::class, 'destroy'])->name('clientes.destroy');
        Route::get('/buscar-ruc', [ClienteController::class, 'lookupRuc'])->name('clientes.buscar-ruc');

        Route::get('/buscar-cliente', [BuscarClienteController::class, 'buscar'])->name('buscar-cliente');
        Route::get('/buscar-documento', [BuscarClienteController::class, 'buscarDocumento'])->name('buscar-documento');

        Route::get('/mi-api-key', [MiApiKeyController::class, 'show'])->name('mi-api-key');
    });
});

require __DIR__.'/settings.php';

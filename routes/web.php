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

// ── Home root ───────────────────────────────────────────────────────────────
// La detección es por Accept:
//   · Cliente acepta HTML (navegador)  → dashboard/login/register según estado
//   · Cliente NO acepta HTML (curl/API) → JSON con status de la API
//
// Se usa `Accept: text/html` en vez de `wantsJson()` porque proxies (nginx,
// cloudflare, cdn) a veces reescriben Accept y disparaban wantsJson()=true
// en navegadores reales, mostrando JSON en la raíz.
Route::get('/', function (\Illuminate\Http\Request $request) {
    $aceptaHtml = str_contains((string) $request->header('Accept', ''), 'text/html');

    if ($aceptaHtml) {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        if (! \App\Models\User::query()->exists()) {
            return redirect('/register');
        }

        return redirect()->route('login');
    }

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
// Rutas temporales de mantenimiento
Route::get('/limpiar-cache-ahora', function () {
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    
    // De paso, le creamos el tenant al usuario principal por si acaso
    $user = \App\Models\User::where('email', 'admin@lu-tec.net')->first();
    if ($user) {
        $tenant = \App\Models\Tenant::firstOrCreate(
            ['user_id' => $user->id],
            [
                'ruc' => '10463838327',
                'razon_social' => 'SUAREZ ORBEGOSO LUIS ANDRES',
                'sol_user' => '',
                'sol_pass' => '',
                'environment' => 'beta',
                'plan' => 'free',
                'is_active' => true,
            ]
        );
    }
    
    return "Caché de Laravel y OPcache limpiados con éxito. Empresa creada. Ya puedes ir a configurar tu clave SOL.";
});

Route::get('/ver-errores', function () {
    $logFile = storage_path('logs/laravel.log');
    if (!file_exists($logFile)) return "No hay archivo de log.";
    
    // Read the last 200 lines
    $lines = file($logFile);
    $lastLines = array_slice($lines, -200);
    return '<pre>' . htmlspecialchars(implode("", $lastLines)) . '</pre>';
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

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

// ── Panel de Administración ─────────────────────────────────────────────────
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Empresas (Tenants)
        Route::get('empresas',                    [\App\Http\Controllers\Admin\TenantController::class, 'index'])->name('empresas.index');
        Route::get('empresas/nueva',              [\App\Http\Controllers\Admin\TenantController::class, 'create'])->name('empresas.create');
        Route::post('empresas',                   [\App\Http\Controllers\Admin\TenantController::class, 'store'])->name('empresas.store');
        Route::get('empresas/{tenant}',           [\App\Http\Controllers\Admin\TenantController::class, 'show'])->name('empresas.show');
        Route::get('empresas/{tenant}/editar',    [\App\Http\Controllers\Admin\TenantController::class, 'edit'])->name('empresas.edit');
        Route::put('empresas/{tenant}',           [\App\Http\Controllers\Admin\TenantController::class, 'update'])->name('empresas.update');
        Route::delete('empresas/{tenant}',        [\App\Http\Controllers\Admin\TenantController::class, 'destroy'])->name('empresas.destroy');
        Route::post('empresas/{tenant}/toggle',   [\App\Http\Controllers\Admin\TenantController::class, 'toggle'])->name('empresas.toggle');
        Route::post('empresas/{tenant}/regenerar-credenciales', [\App\Http\Controllers\Admin\TenantController::class, 'regenerarCredenciales'])->name('empresas.regenerar');

        // Sucursales anidadas por empresa
        Route::get('empresas/{tenant}/sucursales',                      [\App\Http\Controllers\Admin\SucursalController::class, 'index'])->name('sucursales.index');
        Route::get('empresas/{tenant}/sucursales/nueva',                [\App\Http\Controllers\Admin\SucursalController::class, 'create'])->name('sucursales.create');
        Route::post('empresas/{tenant}/sucursales',                     [\App\Http\Controllers\Admin\SucursalController::class, 'store'])->name('sucursales.store');
        Route::get('empresas/{tenant}/sucursales/{sucursal}/editar',    [\App\Http\Controllers\Admin\SucursalController::class, 'edit'])->name('sucursales.edit');
        Route::put('empresas/{tenant}/sucursales/{sucursal}',           [\App\Http\Controllers\Admin\SucursalController::class, 'update'])->name('sucursales.update');
        Route::delete('empresas/{tenant}/sucursales/{sucursal}',        [\App\Http\Controllers\Admin\SucursalController::class, 'destroy'])->name('sucursales.destroy');

        // Series anidadas por empresa
        Route::get('empresas/{tenant}/series',                    [\App\Http\Controllers\Admin\SerieController::class, 'index'])->name('series.index');
        Route::get('empresas/{tenant}/series/nueva',              [\App\Http\Controllers\Admin\SerieController::class, 'create'])->name('series.create');
        Route::post('empresas/{tenant}/series',                   [\App\Http\Controllers\Admin\SerieController::class, 'store'])->name('series.store');
        Route::get('empresas/{tenant}/series/{serie}/editar',     [\App\Http\Controllers\Admin\SerieController::class, 'edit'])->name('series.edit');
        Route::put('empresas/{tenant}/series/{serie}',            [\App\Http\Controllers\Admin\SerieController::class, 'update'])->name('series.update');
        Route::delete('empresas/{tenant}/series/{serie}',         [\App\Http\Controllers\Admin\SerieController::class, 'destroy'])->name('series.destroy');
        Route::post('empresas/{tenant}/series/{serie}/toggle',    [\App\Http\Controllers\Admin\SerieController::class, 'toggle'])->name('series.toggle');

        // Sucursales (listado global, cross-empresa)
        Route::get('sucursales',              [\App\Http\Controllers\Admin\SucursalGlobalController::class, 'index'])->name('sucursales.todas');

        // Series (listado global, cross-empresa)
        Route::get('series',                  [\App\Http\Controllers\Admin\SerieGlobalController::class, 'index'])->name('series.todas');

        // Planes
        Route::get('planes',                  [\App\Http\Controllers\Admin\PlanController::class, 'index'])->name('planes.index');
        Route::get('planes/nuevo',            [\App\Http\Controllers\Admin\PlanController::class, 'create'])->name('planes.create');
        Route::post('planes',                 [\App\Http\Controllers\Admin\PlanController::class, 'store'])->name('planes.store');
        Route::get('planes/{plan}/editar',    [\App\Http\Controllers\Admin\PlanController::class, 'edit'])->name('planes.edit');
        Route::put('planes/{plan}',           [\App\Http\Controllers\Admin\PlanController::class, 'update'])->name('planes.update');
        Route::delete('planes/{plan}',        [\App\Http\Controllers\Admin\PlanController::class, 'destroy'])->name('planes.destroy');
        Route::post('planes/{plan}/toggle',   [\App\Http\Controllers\Admin\PlanController::class, 'toggle'])->name('planes.toggle');

        // Configuración global de emisión (switches ilimitado global / nuevas empresas)
        Route::get('configuracion',           [\App\Http\Controllers\Admin\ConfiguracionController::class, 'edit'])->name('configuracion');
        Route::put('configuracion',           [\App\Http\Controllers\Admin\ConfiguracionController::class, 'update'])->name('configuracion.update');
    });

require __DIR__.'/settings.php';

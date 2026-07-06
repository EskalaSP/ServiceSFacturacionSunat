<?php

use App\Http\Middleware\CheckDocumentLimit;
use App\Http\Middleware\CheckPlanLimit;
use App\Http\Middleware\EnsureFirstUserRegistration;
use App\Http\Middleware\EnsureSireEnabled;
use App\Http\Middleware\ForceJsonAccept;
use App\Http\Middleware\UsageWarningHeader;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Confiar en los headers del reverse proxy (Docker + Nginx).
        // Esto permite a Laravel detectar correctamente scheme (http/https),
        // host y path original — imprescindible para que url() y asset()
        // generen URLs correctas cuando el api-sunat está detrás del proxy nginx.
        $middleware->trustProxies(
            at: '*',
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureFirstUserRegistration::class,
        ]);

        $middleware->alias([
            'resolve.tenant' => ResolveTenant::class,
            'log.api' => LogApiRequest::class,
            'check.limit' => CheckDocumentLimit::class,
            'plan' => CheckPlanLimit::class,
            'usage.headers' => UsageWarningHeader::class,
            'sire.enabled' => EnsureSireEnabled::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);

        // Forzar respuesta JSON en todas las rutas /api/*.
        // Sin esto, si el cliente no manda "Accept: application/json" y hay un error
        // de validación (422) o 404, Laravel redirige/renderiza HTML en vez de devolver
        // JSON con los detalles del error.
        $middleware->prepend(ForceJsonAccept::class);

        // CORS — permite peticiones desde Zaresk (u otros orígenes configurados)
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Devolver JSON para cualquier excepción en rutas /api/*
        // (validation errors, 404, 500, etc.) — sin importar el header Accept.
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Formato español unificado para todas las excepciones en rutas /api/*.
        // Sobrescribe el default de Laravel ({"message": "Server Error"}) con
        // nuestro wrapper {"estado": "error", "mensaje": "...", ...}.
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // Dejar que Laravel renderice normal (HTML)
            }

            // ValidationException: mantener 422 con 'errores'
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'estado' => 'error',
                    'mensaje' => 'Error de validación',
                    'errores' => $e->errors(),
                ], 422);
            }

            // ModelNotFoundException / NotFoundHttpException → 404
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                return response()->json([
                    'estado' => 'error',
                    'mensaje' => 'Recurso no encontrado.',
                ], 404);
            }

            // AuthenticationException → 401
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'estado' => 'error',
                    'mensaje' => 'No autenticado.',
                ], 401);
            }

            // AuthorizationException → 403
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                return response()->json([
                    'estado' => 'error',
                    'mensaje' => 'No autorizado.',
                ], 403);
            }

            // MethodNotAllowedHttpException → 405
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                return response()->json([
                    'estado' => 'error',
                    'mensaje' => 'Método HTTP no permitido para esta ruta.',
                ], 405);
            }

            // ThrottleRequestsException → 429
            if ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                return response()->json([
                    'estado' => 'error',
                    'mensaje' => 'Demasiadas solicitudes. Intenta de nuevo más tarde.',
                ], 429);
            }

            // HttpException genérico (abort(403), abort(404) con mensaje, etc.)
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $status = $e->getStatusCode();
                return response()->json([
                    'estado' => 'error',
                    'mensaje' => $e->getMessage() ?: 'Error.',
                ], $status);
            }

            // Fallback: 500 Internal Server Error
            $response = [
                'estado' => 'error',
                'mensaje' => 'Error interno del servidor.',
            ];

            // En debug: incluir detalles del error
            if (config('app.debug')) {
                $response['excepcion'] = get_class($e);
                $response['detalle'] = $e->getMessage();
                $response['archivo'] = $e->getFile() . ':' . $e->getLine();
            }

            return response()->json($response, 500);
        });
    })->create();

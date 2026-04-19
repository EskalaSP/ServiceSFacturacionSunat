<?php

use App\Http\Middleware\CheckDocumentLimit;
use App\Http\Middleware\CheckPlanLimit;
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
        ]);

        $middleware->alias([
            'resolve.tenant' => ResolveTenant::class,
            'log.api' => LogApiRequest::class,
            'check.limit' => CheckDocumentLimit::class,
            'plan' => CheckPlanLimit::class,
            'usage.headers' => UsageWarningHeader::class,
            'sire.enabled' => EnsureSireEnabled::class,
        ]);

        // Forzar respuesta JSON en todas las rutas /api/*.
        // Sin esto, si el cliente no manda "Accept: application/json" y hay un error
        // de validación (422) o 404, Laravel redirige/renderiza HTML en vez de devolver
        // JSON con los detalles del error.
        $middleware->prepend(ForceJsonAccept::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Devolver JSON para cualquier excepción en rutas /api/*
        // (validation errors, 404, 500, etc.) — sin importar el header Accept.
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();

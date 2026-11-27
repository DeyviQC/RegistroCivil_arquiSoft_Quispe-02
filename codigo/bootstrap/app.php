<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Provider webhooks and the Firma Perú invoker post without a CSRF token (they are HMAC/token checked).
        $middleware->validateCsrfTokens(except: ['pagos/mercadopago/notificacion', 'firma-peru/*/*/parametros', 'firma-peru/*/*/resultado']);
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureUserIsActive::class,
            'portal.acceso' => \App\Http\Middleware\EnsurePortalAccess::class,
            // Same limits as Laravel's throttle; only load tests may skip them (never in production).
            'throttle' => \App\Http\Middleware\ThrottleConPruebasCarga::class,
        ]);

        $middleware->web(append: [
            EnsureUserIsActive::class,
            \App\Http\Middleware\CabecerasSeguridad::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

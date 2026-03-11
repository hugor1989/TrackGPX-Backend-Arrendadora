<?php

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


        /**
         * 🔹 2) Middleware API por defecto
         * (Throttle, binding, etc)
         */
        $middleware->api(prepend: [
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
        ]);


        /**
         * 🔹 3) Alias para tu middleware de roles
         * Ya no existe Kernel.php, se registran aquí.
         */
        $middleware->alias([
            'api.key' => \App\Http\Middleware\VerifyApiKey::class,
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);

        $middleware->alias([
            'company.access' => \App\Http\Middleware\EnsureCompanyAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

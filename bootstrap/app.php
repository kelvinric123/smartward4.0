<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/vital-sign/*',
            'api/infusion/*',
            'api/adt/*',
            'api/cplus/*',
            'api/v1/*',
            'api/doctor/*',
            'api/nurse/*',
            'api/terminal/*',
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\SiemUserRestriction::class,
            \App\Http\Middleware\RequireDeletePassphrase::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

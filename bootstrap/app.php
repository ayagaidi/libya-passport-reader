<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API-only foundation. Authentication can be added when a hosted tier is introduced.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keep exception rendering on Laravel defaults for now.
    })->create();

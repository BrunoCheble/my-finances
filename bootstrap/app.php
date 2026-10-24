<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The app's lock: after 15 minutes without use it asks for the fingerprint / Face ID or the password again.
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureSessionUnlocked::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

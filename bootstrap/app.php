<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                   | Request::HEADER_X_FORWARDED_HOST
                   | Request::HEADER_X_FORWARDED_PORT
                   | Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->replace(
            \Illuminate\Http\Middleware\ValidatePostSize::class,
            \App\Http\Middleware\ValidateLargePostSize::class
        );

        $middleware->alias([
            'role'                  => \App\Http\Middleware\CheckRole::class,
            'audit'                 => \App\Http\Middleware\AuditLogger::class,
            'client.auth'           => \App\Http\Middleware\EnsureClientIsAuthenticated::class,
            'client.must-change-pw' => \App\Http\Middleware\ForceClientPasswordChange::class,
            'sync.reactive'         => \App\Http\Middleware\SyncReactiveState::class,
        ]);

        // Sync auto des statuts (campagnes, options) à chaque request web —
        // évite d'attendre le cron 01h30 pour voir une campagne passer en
        // "terminé" ou une option expirer. Mutex 60s côté middleware.
        $middleware->web(append: [
            \App\Http\Middleware\SyncReactiveState::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
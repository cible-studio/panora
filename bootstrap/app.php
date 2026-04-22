<?php
// bootstrap/app.php — VERSION CORRIGÉE

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ✅ CORRECT : replace($search, $replace) — 2 arguments séparés
        $middleware->replace(
            \Illuminate\Http\Middleware\ValidatePostSize::class,  // ← 1er arg : ce qu'on remplace
            \App\Http\Middleware\ValidateLargePostSize::class     // ← 2ème arg : par quoi
        );

        $middleware->alias([
            'role'              => \App\Http\Middleware\CheckRole::class,
            'audit'             => \App\Http\Middleware\AuditLogger::class,
            'client.auth'       => \App\Http\Middleware\EnsureClientIsAuthenticated::class,
            'client.must-change-pw' => \App\Http\Middleware\ForceClientPasswordChange::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
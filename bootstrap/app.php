<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.access' => \App\Http\Middleware\AdminAccess::class,
            'admin.only' => \App\Http\Middleware\AdminOnly::class,
            'module' => \App\Http\Middleware\CheckModule::class,
        ]);

        // Endpoints públicos do probe de diagnóstico — usuário acessa sem sessão ativa
        $middleware->validateCsrfTokens(except: [
            'api/diagnostico/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

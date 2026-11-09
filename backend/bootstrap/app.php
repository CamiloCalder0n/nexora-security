<?php

use App\Modules\Identidad\Http\Middleware\CerrarSesionInactiva;
use App\Modules\Identidad\Http\Middleware\EnsureRol;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        // El panel llama al API de Alertas (atender y cerrar) con su sesión y su token CSRF.
        $middleware->statefulApi();
        $middleware->appendToGroup('web', CerrarSesionInactiva::class);
        $middleware->appendToGroup('api', CerrarSesionInactiva::class);
        $middleware->alias([
            'rol' => EnsureRol::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

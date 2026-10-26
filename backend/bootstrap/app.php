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
        then: function (): void {
            // MCP staat buiten /api: https://api.veiligerbuiten.nl/mcp/{token}
            \Illuminate\Support\Facades\Route::middleware(['api', 'throttle:60,1'])->group(base_path('routes/mcp.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'subscribed' => \App\Http\Middleware\EnsureSubscribed::class,
            'gzip' => \App\Http\Middleware\CompressResponse::class,
        ]);
        $middleware->appendToGroup('api', \App\Http\Middleware\SetLocale::class);
        // Er is geen login-pagina op de backend: gasten op de API krijgen een 401, geen redirect.
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Onverwachte fouten ook naar het beheerlogboek (Zschot CMS); gewone 4xx niet.
        $exceptions->report(fn (\Throwable $e) => \App\Services\Ops::exception($e));
        // De API antwoordt altijd met JSON (401, 422), ook als een proxy de Accept-header niet doorgeeft.
        $exceptions->shouldRenderJsonWhen(fn (\Illuminate\Http\Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();

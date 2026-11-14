<?php

use App\Http\Middleware\EnsureLocalEnvironment;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Route::middleware(['auth', 'role:seller']) etc. See App\Http\Middleware\EnsureRole.
        $middleware->alias([
            'role' => EnsureRole::class,
            'local' => EnsureLocalEnvironment::class,
        ]);

        // Where `auth` sends people who are not signed in. The login screen
        // itself arrives in Stage 4.
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

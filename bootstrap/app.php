<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Retiré : API pure en Bearer token (Sanctum::createToken), pas de
        // frontend SPA partageant le cookie de session avec l'API.
        // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,

        $middleware->alias([
            'isAdmin'  => \App\Http\Middleware\IsAdmin::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);

        $middleware->redirectGuestsTo(fn() => response()->json([
            'message' => 'Unauthenticated. Please provide a valid token.'
        ], 401));

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*'),
        );
    })->create();

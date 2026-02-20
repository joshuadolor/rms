<?php

use App\Http\Middleware\EnsureEmailIsVerifiedApi;
use App\Http\Middleware\EnsureSuperadmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API: unverified email → 403 Forbidden + JSON message (never 404)
        $middleware->alias([
            'verified' => EnsureEmailIsVerifiedApi::class,
            'superadmin' => EnsureSuperadmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Group API exception rendering: HTTP exceptions return JSON when path is api/* OR client expects JSON
        $exceptions->renderable(function (\Throwable $e, Request $request) {
            if (! $e instanceof HttpExceptionInterface) {
                return null;
            }
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage() ?: 'Server Error',
            ], $e->getStatusCode(), $e->getHeaders());
        });
    })->create();

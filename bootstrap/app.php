<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'phone.verified' => \App\Http\Middleware\EnsurePhoneIsVerified::class,
        ]);

        $middleware->prependToGroup('api', \App\Http\Middleware\QueryTokenToHeader::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        \Sentry\Laravel\Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (\Illuminate\Database\QueryException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (app()->bound('sentry')) {
                    \Sentry\captureException($e);
                }

                \Illuminate\Support\Facades\Log::error('Database QueryException: ' . $e->getMessage(), [
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong processing your request. Please try again later.',
                ], 500);
            }
        });

        $exceptions->render(function (\PDOException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (app()->bound('sentry')) {
                    \Sentry\captureException($e);
                }

                \Illuminate\Support\Facades\Log::error('Database PDOException: ' . $e->getMessage(), [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'A database connection error occurred. Please try again later.',
                ], 500);
            }
        });
    })->create();

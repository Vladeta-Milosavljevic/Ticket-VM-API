<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Ensure Sanctum middleware is applied for SPA authentication
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (AuthorizationException $e) {
            $user = request()->user();
            Log::warning('Unauthorized access attempt', [
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'action' => $e->getMessage(),
                'path' => request()->path(),
            ]);
        });
    })->create();

<?php

use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantWritable;
use App\Http\Middleware\RequireFeature;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\UseWelcomeBroker;
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
        // Sanctum cookie auth for the SPA on the same domain.
        $middleware->statefulApi();

        $middleware->web(append: [UseWelcomeBroker::class]);

        $middleware->alias([
            'idempotent' => EnsureIdempotency::class,
            'feature' => RequireFeature::class,
            'platform' => EnsureSuperAdmin::class,
            'tenant' => ResolveTenant::class,
            'tenant.writable' => EnsureTenantWritable::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

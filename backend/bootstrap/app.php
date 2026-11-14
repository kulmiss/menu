<?php

use App\Http\Middleware\EnsureAccessToken;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsureOwnerTwoFactor;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantWritable;
use App\Http\Middleware\GuardImpersonation;
use App\Http\Middleware\MobileAppGate;
use App\Http\Middleware\RequireFeature;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ThrottlePasswordEmails;
use App\Http\Middleware\UseWelcomeBroker;
use Illuminate\Console\Scheduling\Schedule;
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
        // Behind Cloudflare or a host's proxy, set TRUSTED_PROXIES (e.g. "*") so rate limits
        // and HTTPS detection see the visitor, not the proxy (SECURITY_REPORT.md M9).
        if (($proxies = (string) env('TRUSTED_PROXIES', '')) !== '') {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Security headers on every answer (SECURITY_REPORT.md H1).
        $middleware->append(SecurityHeaders::class);

        // Sanctum cookie auth for the SPA on the same domain.
        $middleware->statefulApi();

        // "Log in as": 2-hour limit and blocked actions, for the API and Fortify (web).
        $middleware->api(append: [GuardImpersonation::class, MobileAppGate::class]);
        $middleware->web(append: [GuardImpersonation::class, UseWelcomeBroker::class]);

        // One-click unsubscribe (List-Unsubscribe-Post) comes from mail clients without a CSRF token; the link is signed.
        $middleware->preventRequestForgery(except: ['news/*/unsubscribe']);

        // Signed-out requests: the API answers 401 (also without "Accept: application/json"),
        // anything else goes to the sign-in page. Laravel's default looked for a "login"
        // route that doesn't exist and answered 500 (pre-launch check).
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/auth/sign-in');

        $middleware->alias([
            'idempotent' => EnsureIdempotency::class,
            'mobile.token' => EnsureAccessToken::class,
            'password.throttle' => ThrottlePasswordEmails::class,
            'owner.two_factor' => EnsureOwnerTwoFactor::class,
            'feature' => RequireFeature::class,
            'platform' => EnsureSuperAdmin::class,
            'tenant' => ResolveTenant::class,
            'tenant.writable' => EnsureTenantWritable::class,
        ]);
    })
    // Kept here, not in routes/console.php, so it stays clear of the schedule there.
    ->withSchedule(function (Schedule $schedule): void {
        // One "turn on two-factor" reminder for Owners (SECURITY_REPORT.md M17).
        $schedule->command('security:two-factor-reminders')->dailyAt('07:10')->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

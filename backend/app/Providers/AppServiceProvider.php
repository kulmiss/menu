<?php

namespace App\Providers;

use App\Support\MailSettings;
use App\Support\Ops\Alerts;
use App\Tenancy\CurrentTenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One per request / job, reset automatically between them.
        $this->app->scoped(CurrentTenant::class);
    }

    public function boot(): void
    {
        self::guardDebug($this->app->isProduction());

        // Server errors reach the Super Admins by e-mail / Telegram, grouped (QUESTIONS #233).
        Event::listen(MessageLogged::class, [Alerts::class, 'onLog']);

        // Catch lazy loading, silently discarded attributes and missing attributes early.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Landing page clicks: one shared limit, never per visitor (owner, 2026-11-17).
        RateLimiter::for('landing-click', fn () => Limit::perMinute(600)->by('landing-click'));

        // The two signed-in areas (QUESTIONS #220), per person: the Super Admin's is stricter.
        RateLimiter::for('super', fn (Request $request) => Limit::perMinute(240)->by('super:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('restaurant', fn (Request $request) => Limit::perMinute(600)->by('restaurant:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Every e-mail uses the SMTP server saved by the Super Admin, if any (else .env).
        $this->app->afterResolving('mail.manager', fn () => MailSettings::apply());
    }

    /**
     * Never show error details (code, paths, settings) to visitors on a live site,
     * even if .env says APP_DEBUG=true by mistake (SECURITY_REPORT.md L3).
     */
    public static function guardDebug(bool $production): void
    {
        if ($production && config('app.debug')) {
            config(['app.debug' => false]);
            if (Cache::add('security:debug-forced-off', true, now()->addDay())) {
                Log::critical('APP_DEBUG=true on a production site: error details were turned off. Set APP_DEBUG=false in .env.');
            }
        }
    }
}

<?php

namespace App\Providers;

use App\Support\MailSettings;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

        // Catch lazy loading, silently discarded attributes and missing attributes early.
        Model::shouldBeStrict(! $this->app->isProduction());

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

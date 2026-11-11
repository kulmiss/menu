<?php

namespace App\Providers;

use App\Support\MailSettings;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
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
        // Catch lazy loading, silently discarded attributes and missing attributes early.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Every e-mail uses the SMTP server saved by the Super Admin, if any (else .env).
        $this->app->afterResolving('mail.manager', fn () => MailSettings::apply());
    }
}

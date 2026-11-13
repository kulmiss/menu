<?php

use App\Jobs\SendCampaignBatch;
use App\Models\Tenant;
use App\Support\Backups\Backups;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Static guest pages (PLAN.md §15.2.1): drop pages of restaurants whose
// subscription ran out, and rebuild everything daily so "New" badges expire.
// Queued e-mails (invitations) on hosts without a long-running worker: the
// one cron line for schedule:run also empties the queue every minute.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();

// Heartbeat for the Super Admin's "System health" (cron last run).
Schedule::call(fn () => Cache::forever('cron:last_run', now()->toIso8601String()))->everyMinute()->name('cron-heartbeat');

// Super Admin → Messages: a few e-mails per minute (owner, 2026-11-13).
Schedule::call(fn () => SendCampaignBatch::dispatch())->everyMinute()->name('campaigns:send')
    ->when(fn () => SendCampaignBatch::pending());

// Backups (owner, 2026-11-13): daily, and an hourly check that warns the Super Admins.
Schedule::command('backup:run --scheduled')->dailyAt('02:30')->withoutOverlapping(120);
Schedule::call(fn () => app(Backups::class)->check())->hourlyAt(17)->name('backup:check')
    ->when(fn () => (bool) PlatformSettings::get('backup_enabled'));

// Time-based prices: rebuild pages when a period starts or ends (owner, 2026-11-14).
Schedule::command('menu:price-periods')->everyMinute()->withoutOverlapping();

Schedule::command('menu:refresh-pages')->hourly();
Schedule::command('menu:refresh-pages --all')->dailyAt('00:05');
// Self sign-ups that never confirmed their e-mail (owner, 2026-11-13).
Schedule::command('signup:prune')->dailyAt('03:20');

// Insights keep 13 months of daily counters (enough for "same month last year").
Schedule::call(fn () => DB::table('stats_daily')->where('date', '<', now()->subDays(400)->toDateString())->delete())
    ->dailyAt('03:15')->name('stats:prune');

// Deleted restaurants are kept 30 days (Super Admin can restore), then purged.
Schedule::call(fn () => Tenant::onlyTrashed()->where('deleted_at', '<', now()->subDays(30))->get()->each->forceDelete())
    ->dailyAt('03:30')->name('restaurants:purge');

<?php

use App\Jobs\SendCampaignBatch;
use App\Models\AppNotification;
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
// Times are UTC (the app's clock); the daily jobs run at night in Addis Ababa
// (UTC+3, no daylight saving): 21:05 = 00:05, 22:10 = 01:10, 23:30 = 02:30,
// 00:15–00:30 = 03:15–03:30 (owner, 2026-11-14).
Schedule::command('backup:run --scheduled')->dailyAt('23:30')->withoutOverlapping(120);
Schedule::call(fn () => app(Backups::class)->check())->hourlyAt(17)->name('backup:check')
    ->when(fn () => (bool) PlatformSettings::get('backup_enabled'));

// Time-based prices: rebuild pages when a period starts or ends (owner, 2026-11-14).
Schedule::command('menu:price-periods')->everyMinute()->withoutOverlapping();
// Monthly Insights e-mail: the command itself waits for the 1st, 07:00 local time, and sends each month once.
Schedule::command('insights:monthly')->hourly()->withoutOverlapping();

Schedule::command('menu:refresh-pages')->hourly();
// Restaurants on a replaced plan move to the new one once their paid time has ended (QUESTIONS #184).
Schedule::command('plans:move-replaced')->hourlyAt(7)->withoutOverlapping();
// Super Admin dashboard numbers (QUESTIONS #186): hourly for today and
// yesterday, nightly for the last 35 days (late payments, restored restaurants).
Schedule::command('platform:aggregate --days=2')->hourlyAt(12)->withoutOverlapping(30);
Schedule::command('platform:aggregate --days=35')->dailyAt('22:10')->withoutOverlapping(60);
Schedule::command('menu:refresh-pages --all')->dailyAt('21:05');
// Self sign-ups that never confirmed their e-mail (owner, 2026-11-13).
Schedule::command('signup:prune')->dailyAt('00:20');
// Mobile app sign-ins that expired or were unused too long (QUESTIONS #187). At night in Addis Ababa (#91).
Schedule::command('mobile:prune-tokens')->dailyAt('00:25');
// Push notifications for the app (QUESTIONS #189); sending itself runs on the queue.
// Messages to owners go out in the Addis Ababa daytime (09:00 UTC = 12:00, 08:00 UTC = 11:00).
Schedule::command('push:plan-reminders')->dailyAt('09:00');
Schedule::command('push:followups')->dailyAt('08:00');
Schedule::command('push:health')->everyFifteenMinutes()->withoutOverlapping(10);
Schedule::call(fn () => AppNotification::where('created_at', '<', now()->subDays(AppNotification::KEEP_DAYS))->delete())
    ->dailyAt('00:35')->name('notifications:prune');

// Insights keep 13 months of daily counters (enough for "same month last year").
Schedule::call(fn () => DB::table('stats_daily')->where('date', '<', now()->subDays(400)->toDateString())->delete())
    ->dailyAt('00:15')->name('stats:prune');

// Deleted restaurants are kept 30 days (Super Admin can restore), then purged.
Schedule::call(fn () => Tenant::onlyTrashed()->where('deleted_at', '<', now()->subDays(30))->get()->each->forceDelete())
    ->dailyAt('00:30')->name('restaurants:purge');

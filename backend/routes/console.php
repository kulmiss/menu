<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Static guest pages (PLAN.md §15.2.1): drop pages of restaurants whose
// subscription ran out, and rebuild everything daily so "New" badges expire.
Schedule::command('menu:refresh-pages')->hourly();
Schedule::command('menu:refresh-pages --all')->dailyAt('00:05');

// Insights keep 13 months of daily counters (enough for "same month last year").
Schedule::call(fn () => DB::table('stats_daily')->where('date', '<', now()->subDays(400)->toDateString())->delete())
    ->dailyAt('03:15')->name('stats:prune');

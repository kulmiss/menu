<?php

namespace App\Console\Commands;

use App\Support\PlatformStats\Aggregator;
use Illuminate\Console\Command;

/**
 * Builds the Super Admin dashboard's summary tables (QUESTIONS #186). Runs
 * from the existing cron: hourly for the last two days, nightly for the last
 * 35 days. The first run (empty tables) fills 400 days.
 */
class AggregatePlatformStats extends Command
{
    protected $signature = 'platform:aggregate {--days=2 : how many days back to rebuild}';

    protected $description = 'Build the Super Admin dashboard numbers from restaurants, payments, menu counters and the activity log';

    public function handle(Aggregator $aggregator): int
    {
        $timings = $aggregator->run(max(1, (int) $this->option('days')));
        $this->info('Dashboard numbers built ('.collect($timings)->map(fn ($s, $k) => "{$k} {$s}s")->implode(', ').').');

        return self::SUCCESS;
    }
}

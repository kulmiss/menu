<?php

use Illuminate\Console\Scheduling\Schedule;

it('runs everything from the one schedule:run cron line (docs/deploy.md)', function () {
    $commands = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command.' '.$e->description)->implode("\n");

    // Queued e-mails are sent every minute without a long-running worker (shared hosting).
    expect($commands)->toContain('queue:work --stop-when-empty')
        ->toContain('menu:refresh-pages')
        ->toContain('stats:prune')
        ->toContain('restaurants:purge');
});

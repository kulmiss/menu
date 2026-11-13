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

it('runs the daily jobs at night in Addis Ababa while the app clock stays UTC (owner, 2026-11-14)', function () {
    expect(config('app.timezone'))->toBe('UTC');
    $daily = collect(app(Schedule::class)->events())->filter(fn ($e) => preg_match('/^\d+ \d+ \* \* \*$/', $e->expression));
    expect($daily->count())->toBeGreaterThanOrEqual(6);
    foreach ($daily as $e) {
        [$minute, $hour] = array_map('intval', explode(' ', $e->expression));
        $addis = ($hour + 3) % 24;
        expect($addis)->toBeLessThan(4, ($e->description ?: $e->command)." runs at {$addis}:{$minute} in Addis");
    }
    $at = fn (string $needle) => $daily->first(fn ($e) => str_contains((string) $e->command.' '.$e->description, $needle))?->expression;
    expect($at('backup:run'))->toBe('30 23 * * *')       // 02:30 Addis
        ->and($at('refresh-pages --all'))->toBe('5 21 * * *'); // 00:05 Addis: "New" badges expire after midnight
});

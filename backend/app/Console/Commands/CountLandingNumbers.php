<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\PlatformSettings;
use App\Support\PlatformStats\Aggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Landing 2's real numbers (owner, 2026-11-22), counted once a day and kept in a setting,
 * so the page never counts on a visit: restaurants (live, not the website demos), their
 * dishes, and their menu views in the last 30 days (the dashboard's numbers, demos left out).
 */
class CountLandingNumbers extends Command
{
    protected $signature = 'landing2:numbers';

    protected $description = 'Count the real numbers shown on Landing 2 (restaurants, dishes, menu views)';

    public function handle(): int
    {
        $demo = array_keys(Aggregator::demoIds());
        $live = Tenant::query()->whereNull('suspended_at')->whereNotIn('id', $demo)->pluck('id');
        $counted = [
            'at' => now()->toIso8601String(),
            'restaurants' => $live->count(),
            'dishes' => (int) DB::table('items')->whereIn('tenant_id', $live)->count(),
            'views' => (int) DB::table('platform_daily')->where('demo', false)->where('date', '>=', now()->subDays(30)->toDateString())->sum('menu_views'),
        ];
        PlatformSettings::save(['landing2_numbers_counted' => $counted]);
        $this->info("Landing 2 numbers: {$counted['restaurants']} restaurants, {$counted['dishes']} dishes, {$counted['views']} menu views (30 days).");

        return self::SUCCESS;
    }
}

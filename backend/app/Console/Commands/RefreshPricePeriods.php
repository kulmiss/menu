<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\PublicMenu\PricePeriods;
use App\Tenancy\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Every minute: when a price period starts or ends, the restaurant's static
 * guest pages are removed (rebuilt on the next visit) and open phones
 * update, so guests always see the price that is valid now.
 */
class RefreshPricePeriods extends Command
{
    protected $signature = 'menu:price-periods';

    protected $description = 'Rebuild guest menu pages when a price period starts or ends';

    public function handle(CurrentTenant $current): int
    {
        $ids = DB::table('price_periods')->where('is_active', true)->distinct()->pluck('tenant_id');
        $changed = 0;
        Tenant::whereIn('id', $ids)->get()->each(function (Tenant $tenant) use ($current, &$changed) {
            $signature = $current->runAs($tenant, fn () => PricePeriods::signature($tenant));
            $key = "price-periods:{$tenant->id}";
            if (Cache::get($key) !== $signature) {
                Cache::forever($key, $signature);
                $tenant->bumpCacheVersion();
                $changed++;
            }
        });
        $this->info("{$changed} restaurant(s) updated.");

        return self::SUCCESS;
    }
}

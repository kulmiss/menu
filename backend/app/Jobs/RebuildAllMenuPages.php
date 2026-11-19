<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * After a platform-wide switch (Settings → Features): every restaurant's saved
 * guest page is out of date. The static copies are already deleted at once
 * (FeatureSwitchController); this job then moves every restaurant's cache
 * version on in small batches, so nothing old is stored again (QUESTIONS #236).
 */
class RebuildAllMenuPages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function handle(): void
    {
        Tenant::query()->select(['id', 'slug', 'cache_version'])->chunkById(200, fn ($tenants) => $tenants->each->bumpCacheVersion());
    }
}

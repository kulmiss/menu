<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\PublicMenu\MenuPageCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * The web server sends static guest pages without asking PHP, so things that
 * change with time alone must remove them: a subscription that runs out, and
 * "New" badges that expire at midnight (PLAN.md §15.2.1).
 */
#[Signature('menu:refresh-pages {--all : Remove every static page}')]
#[Description('Remove static guest menu pages that may be out of date')]
class RefreshMenuPages extends Command
{
    public function handle(): int
    {
        $root = public_path('menu-cache');
        if (! is_dir($root)) {
            return self::SUCCESS;
        }

        if ($this->option('all')) {
            File::deleteDirectory($root, preserve: true);
            $this->info('All static menu pages removed.');

            return self::SUCCESS;
        }

        $slugs = array_map('basename', File::directories($root));
        $removed = 0;
        Tenant::whereIn('slug', $slugs)->get()->each(function (Tenant $tenant) use (&$removed) {
            if (! $tenant->subscriptionState()->showsPublicMenu()) {
                MenuPageCache::forget($tenant);
                $removed++;
            }
        });
        // Folders left from deleted restaurants or old addresses.
        $known = Tenant::whereIn('slug', $slugs)->pluck('slug')->all();
        foreach (array_diff($slugs, $known) as $slug) {
            File::deleteDirectory(MenuPageCache::dir($slug));
            $removed++;
        }
        $this->info("Removed {$removed} static menu page(s).");

        return self::SUCCESS;
    }
}

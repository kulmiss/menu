<?php

namespace App\Support\PublicMenu;

use App\Models\Tenant;
use App\Models\TenantSlugHistory;
use Illuminate\Support\Facades\File;

/**
 * The static copy of each menu page (PLAN.md §15.2.1):
 * public/menu-cache/{slug}/index.html plus a gzip copy. Apache/Nginx send
 * it without starting PHP; it is deleted whenever the menu changes and
 * rebuilt on the next visit.
 */
class MenuPageCache
{
    public static function dir(string $slug, ?string $branch = null): string
    {
        return public_path('menu-cache/'.$slug.($branch === null ? '' : '/'.$branch));
    }

    /** Main branch: menu-cache/{slug}/index.html; others: menu-cache/{slug}/{branch}/index.html. */
    public static function path(string $slug, ?string $branch = null): string
    {
        return self::dir($slug, $branch).'/index.html';
    }

    public static function enabled(): bool
    {
        return (bool) config('menu.static_pages', true);
    }

    public static function write(string $slug, string $html, ?string $branch = null): void
    {
        if (! self::enabled()) {
            return;
        }
        $dir = self::dir($slug, $branch);
        File::ensureDirectoryExists($dir);
        // Write to temporary names, then rename: a visitor never gets half a file.
        $tmp = $dir.'/.index.html.'.bin2hex(random_bytes(4));
        file_put_contents($tmp, $html);
        file_put_contents($tmp.'.gz', (string) gzencode($html, 9));
        rename($tmp.'.gz', $dir.'/index.html.gz');
        rename($tmp, $dir.'/index.html');
    }

    /** Removes the static copies for the restaurant's current and old addresses. */
    public static function forget(Tenant $tenant): void
    {
        $slugs = TenantSlugHistory::where('tenant_id', $tenant->id)->pluck('old_slug')->push($tenant->slug);
        foreach ($slugs as $slug) {
            File::deleteDirectory(self::dir((string) $slug));
        }
    }
}

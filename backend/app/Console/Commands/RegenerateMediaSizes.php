<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\Tenant;
use App\Support\ImageProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Photos uploaded before a new size existed (800 px, 2026-11-11) only get it
 * when re-uploaded. This makes the missing sizes from the largest copy each
 * photo has, then rebuilds the menu pages that show them. Safe to run again:
 * photos that already have every size are skipped.
 */
#[Signature('media:regenerate {--tenant= : Only this restaurant (id or slug)} {--dry-run : Only count what would change}')]
#[Description('Make missing photo sizes (e.g. 800 px) for photos uploaded earlier')]
class RegenerateMediaSizes extends Command
{
    public function handle(ImageProcessor $images): int
    {
        $query = Media::withoutGlobalScopes()->orderBy('id');
        if ($filter = $this->option('tenant')) {
            $tenant = Tenant::where('id', $filter)->orWhere('slug', $filter)->first();
            if (! $tenant) {
                $this->error("No restaurant \"{$filter}\".");

                return self::FAILURE;
            }
            $query->where('tenant_id', $tenant->id);
        }
        // Banners are saved a little lighter (QUESTIONS #114).
        $banners = DB::table('tenant_appearance')->whereNotNull('cover_media_id')->pluck('cover_media_id')->flip();

        $updated = $failed = 0;
        $tenants = [];
        $query->chunkById(100, function ($chunk) use ($images, $banners, &$updated, &$failed, &$tenants) {
            foreach ($chunk as $media) {
                /** @var Media $media */
                $variants = (array) $media->variants;
                try {
                    if ($this->option('dry-run')) {
                        $missing = array_filter(ImageProcessor::WIDTHS, fn (int $w) => ! array_key_exists($w, $variants));
                        $updated += $missing === [] ? 0 : 1;

                        continue;
                    }
                    $added = $images->addMissing($media->disk, $media->base_path, $variants, $banners->has($media->id));
                } catch (Throwable $e) {
                    $failed++;
                    $this->warn("Photo {$media->id}: {$e->getMessage()}");

                    continue;
                }
                if ($added !== []) {
                    // array_replace keeps the numeric size keys ('320', '800', …); spreading would renumber them.
                    $media->forceFill(['variants' => array_replace($variants, $added)])->saveQuietly();
                    $updated++;
                    $tenants[$media->tenant_id] = true;
                }
            }
        });

        // Pages list the photo sizes in srcset: rebuild those that show changed photos.
        Tenant::whereIn('id', array_keys($tenants))->get()->each->bumpCacheVersion();

        $this->info($this->option('dry-run')
            ? "{$updated} photo(s) are missing sizes."
            : "{$updated} photo(s) updated, {$failed} failed, ".count($tenants).' menu(s) rebuilt.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

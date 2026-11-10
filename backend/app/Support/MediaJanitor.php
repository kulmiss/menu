<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Item;
use App\Models\Media;
use App\Models\Promotion;
use App\Models\TenantAppearance;

/**
 * A replaced or orphaned photo is deleted (row and files), so it no longer
 * counts against the plan's photo limit.
 */
class MediaJanitor
{
    public static function releaseIfUnused(?int $mediaId): void
    {
        if ($mediaId === null) {
            return;
        }

        $used = Item::where('media_id', $mediaId)->exists()
            || Category::where('media_id', $mediaId)->exists()
            || Promotion::where('media_id', $mediaId)->exists()
            || TenantAppearance::where(fn ($q) => $q->where('logo_media_id', $mediaId)->orWhere('cover_media_id', $mediaId))->exists();
        if ($used) {
            return;
        }

        $media = Media::find($mediaId);
        if ($media !== null) {
            $media->deleteFiles();
            $media->delete();
        }
    }
}

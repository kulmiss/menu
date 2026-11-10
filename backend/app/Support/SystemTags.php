<?php

namespace App\Support;

use App\Models\Tag;

/** Makes sure the current restaurant has every system tag (idempotent). */
class SystemTags
{
    public static function ensure(): void
    {
        $keys = config('menu.system_tags');
        $existing = Tag::query()->whereNotNull('key')->pluck('key')->all();

        foreach (array_values(array_diff($keys, $existing)) as $key) {
            Tag::create(['key' => $key, 'sort' => (int) array_search($key, $keys, true)]);
        }
    }
}

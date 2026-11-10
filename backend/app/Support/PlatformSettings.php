<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide settings (PLAN.md §7.3 screen 8), cached; defaults below.
 */
class PlatformSettings
{
    public const DEFAULTS = [
        'name' => 'Menu',
        'support_email' => null,
        'domain' => null,
        'default_locale' => 'en',
        'default_currency' => 'ETB',
        'terms' => '',
        'privacy' => '',
    ];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return Cache::remember('platform_settings', 3600, function () {
            $stored = DB::table('platform_settings')->pluck('value', 'key')
                ->map(fn ($v) => json_decode((string) $v, true))->all();

            return [...self::DEFAULTS, ...array_intersect_key($stored, self::DEFAULTS)];
        });
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    /** @param array<string, mixed> $values */
    public static function save(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            DB::table('platform_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        }
        Cache::forget('platform_settings');
    }
}

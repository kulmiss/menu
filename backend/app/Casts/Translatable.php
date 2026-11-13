<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Menu text in several languages, stored as one JSON column keyed by locale:
 * {"en": "Macchiato", "am": "ማኪያቶ"}. Blank translations are dropped, so
 * "missing" always means "no key" (PLAN.md §4, QUESTIONS #33).
 *
 * @implements CastsAttributes<array<string, string>, array<string, string|null>|string|null>
 */
class Translatable implements CastsAttributes
{
    /** @return array<string, string> */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? self::clean($decoded) : [];
    }

    /** @return array<string, string|null> */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $clean = is_array($value) ? self::clean($value) : [];

        return [$key => $clean === [] ? null : json_encode($clean, JSON_UNESCAPED_UNICODE)];
    }

    /**
     * @param  array<mixed>  $value
     * @return array<string, string>
     */
    public static function clean(array $value): array
    {
        $out = [];
        foreach ($value as $locale => $text) {
            if (is_string($locale) && is_scalar($text) && trim((string) $text) !== '') {
                $out[$locale] = trim((string) $text);
            }
        }
        ksort($out);

        return $out;
    }
}

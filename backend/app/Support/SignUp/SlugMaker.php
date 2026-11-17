<?php

namespace App\Support\SignUp;

use App\Rules\ValidSlug;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * A free menu address from the restaurant name (owner, 2026-11-17: the
 * shortest sign-up asks no address): "Buna & Bread" → "buna-bread", else
 * "buna-bread-2" … , else a short random ending. Names in other scripts
 * (e.g. Amharic) start from "menu". The owner can change it later.
 */
class SlugMaker
{
    public const MAX_BASE = 44;

    public static function from(string $name): string
    {
        // At most 44 characters, so "-2" or a 4-letter ending still fits the 50 allowed.
        $base = Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->limit(self::MAX_BASE, '')->rtrim('-')->toString();
        if (strlen($base) < 3) {
            $base = 'menu'.($base === '' ? '' : '-'.$base);
        }
        foreach ([$base, ...array_map(fn (int $n) => "{$base}-{$n}", range(2, 9))] as $slug) {
            if (self::free($slug)) {
                return $slug;
            }
        }
        for ($try = 0; $try < 20; $try++) {
            $slug = $base.'-'.Str::lower(Str::random(4));
            if (self::free($slug)) {
                return $slug;
            }
        }

        throw new \RuntimeException('No free menu address for "'.$name.'".');
    }

    private static function free(string $slug): bool
    {
        return Validator::make(['slug' => $slug], ['slug' => [new ValidSlug]])->passes();
    }
}

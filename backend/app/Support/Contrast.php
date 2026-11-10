<?php

namespace App\Support;

/**
 * WCAG 2 contrast helpers (PLAN.md §2: contrast ≥ 4.5:1). The guest page never
 * shows a colour pair below the minimum: colours are nudged until they pass.
 */
class Contrast
{
    public const MIN = 4.5;

    public const LIGHT_BG = '#ffffff';

    public const DARK_BG = '#141210';

    public static function valid(string $hex): bool
    {
        return (bool) preg_match('/^#[0-9a-f]{6}$/i', $hex);
    }

    /** @return array{0: int, 1: int, 2: int} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    public static function hex(int $r, int $g, int $b): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }

    public static function luminance(string $hex): float
    {
        $c = array_map(function (int $v) {
            $s = $v / 255;

            return $s <= 0.04045 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** White or near-black text, whichever reads better on the colour. */
    public static function textOn(string $hex): string
    {
        return self::ratio($hex, '#ffffff') >= self::ratio($hex, self::DARK_BG) ? '#ffffff' : self::DARK_BG;
    }

    /**
     * The colour itself if it reads on the background, otherwise the nearest
     * darker (light background) or lighter (dark background) shade that does.
     */
    public static function readableOn(string $hex, string $background): string
    {
        $towards = self::luminance($background) > 0.5 ? 0 : 255;
        [$r, $g, $b] = self::rgb($hex);
        for ($step = 0; $step <= 20; $step++) {
            $t = $step / 20;
            $candidate = self::hex(
                (int) round($r + ($towards - $r) * $t),
                (int) round($g + ($towards - $g) * $t),
                (int) round($b + ($towards - $b) * $t),
            );
            if (self::ratio($candidate, $background) >= self::MIN) {
                return $candidate;
            }
        }

        return $towards === 0 ? '#000000' : '#ffffff';
    }

    /**
     * Warnings for the admin: the page fixes colours itself, but the owner
     * should know their brand colour is shown darker on the normal (light)
     * page. Dark mode always uses a lighter shade, so it needs no warning.
     *
     * @return list<string> translation keys
     */
    public static function warnings(string $primary): array
    {
        return self::ratio($primary, self::LIGHT_BG) < self::MIN ? ['appearance.contrast.light'] : [];
    }
}

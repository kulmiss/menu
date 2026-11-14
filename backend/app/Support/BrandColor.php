<?php

namespace App\Support;

/**
 * Accessible shades of the platform's brand colour (landing page). Text on
 * the brand colour, and brand-coloured text on the light and dark
 * backgrounds, always reach a contrast of at least 4.5:1.
 */
class BrandColor
{
    public const DEFAULT = '#c2410c';

    public const LIGHT_BG = '#ffffff';

    public const DARK_BG = '#0b1020';

    /** @return array{brand: string, on_brand: string, ink: string, ink_dark: string, button_dark: string} */
    public static function palette(?string $hex): array
    {
        $brand = self::valid($hex) ? strtolower((string) $hex) : self::DEFAULT;
        $onBrand = self::contrast($brand, '#ffffff') >= 4.5 ? '#ffffff' : '#111111';

        return [
            'brand' => $brand,
            'on_brand' => $onBrand,
            // Brand-coloured text and links on each background.
            'ink' => self::until($brand, '#000000', self::LIGHT_BG),
            'ink_dark' => self::until($brand, '#ffffff', self::DARK_BG),
            'button_dark' => $brand,
        ];
    }

    public static function valid(?string $hex): bool
    {
        return is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    public static function contrast(string $a, string $b): float
    {
        [$x, $y] = [self::luminance($a), self::luminance($b)];

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    /** Mixes $hex towards $towards in 5% steps until it reads on $background. */
    private static function until(string $hex, string $towards, string $background): string
    {
        for ($step = 0; $step <= 20; $step++) {
            $mixed = self::mix($hex, $towards, $step / 20);
            if (self::contrast($mixed, $background) >= 4.5) {
                return $mixed;
            }
        }

        return $towards;
    }

    private static function mix(string $a, string $b, float $t): string
    {
        $ca = sscanf($a, '#%02x%02x%02x');
        $cb = sscanf($b, '#%02x%02x%02x');
        $out = '#';
        for ($i = 0; $i < 3; $i++) {
            $out .= sprintf('%02x', (int) round($ca[$i] + ($cb[$i] - $ca[$i]) * $t));
        }

        return $out;
    }

    private static function luminance(string $hex): float
    {
        $c = array_map(function ($v) {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, sscanf($hex, '#%02x%02x%02x'));

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }
}

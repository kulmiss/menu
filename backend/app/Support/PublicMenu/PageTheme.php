<?php

namespace App\Support\PublicMenu;

use App\Models\TenantAppearance;
use App\Support\Contrast;

/**
 * Turns a restaurant's appearance settings into what the guest page needs:
 * CSS custom properties (always readable, §2) and switches.
 */
class PageTheme
{
    private const FONTS = [
        'system' => 'system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans",sans-serif',
        'serif' => '"Iowan Old Style","Palatino Linotype",Palatino,Georgia,"Noto Serif",serif',
        'rounded' => 'ui-rounded,"SF Pro Rounded","Hiragino Maru Gothic ProN",Quicksand,Nunito,system-ui,sans-serif',
    ];

    /**
     * Per style: the surfaces the brand colour is drawn on (the least
     * contrasting one in light and in dark mode; must match style-*.css) and
     * the heading font used while the restaurant keeps the default font.
     */
    public const STYLES = [
        'editorial' => ['light' => '#f3ebe0', 'dark' => '#231c18', 'heading' => 'serif'],
        'grid' => ['light' => '#f5f7f6', 'dark' => '#18201d', 'heading' => 'system'],
        'bistro' => ['light' => '#faf8f5', 'dark' => '#161412', 'heading' => 'serif'],
    ];

    /**
     * @param  list<string>|null  $allowed  styles the plan allows (null: all)
     * @return array<string, mixed>
     */
    public static function from(TenantAppearance $a, ?array $allowed = null): array
    {
        $style = TenantAppearance::effectiveStyle($a->style, $allowed ?? TenantAppearance::STYLES);
        $surface = self::STYLES[$style];
        $primary = Contrast::valid($a->primary_color) ? strtolower($a->primary_color) : TenantAppearance::DEFAULTS['primary_color'];
        $light = Contrast::readableOn($primary, $surface['light']);
        $dark = Contrast::readableOn($primary, $surface['dark']);
        $font = self::FONTS[$a->font] ?? self::FONTS['system'];
        // "Default" font keeps the style's own heading font; a chosen font is used everywhere.
        $heading = $a->font === 'system' ? self::FONTS[$surface['heading']] : $font;

        return [
            'css' => sprintf(
                ':root{--a:%s;--af:%s;--ff:%s;--fh:%s}@media (prefers-color-scheme:dark){:root{--a:%s;--af:%s}}',
                $light, Contrast::textOn($light), $font, $heading, $dark, Contrast::textOn($dark),
            ),
            'theme_color' => $light,
            'style' => $style,
            'show_prices' => $a->show_prices,
            'show_photos' => $a->show_photos,
            'show_descriptions' => $a->show_descriptions,
            'show_calories' => $a->show_calories,
            'logo' => $a->logo?->urls(),
            'cover' => $a->cover ? ['urls' => $a->cover->urls(), 'placeholder' => $a->cover->placeholder, 'color' => $a->cover->dominant_color] : null,
        ];
    }
}

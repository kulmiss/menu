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

    /** @return array<string, mixed> */
    public static function from(TenantAppearance $a): array
    {
        $primary = Contrast::valid($a->primary_color) ? strtolower($a->primary_color) : TenantAppearance::DEFAULTS['primary_color'];
        $light = Contrast::readableOn($primary, Contrast::LIGHT_BG);
        $dark = Contrast::readableOn($primary, Contrast::DARK_BG);

        return [
            'css' => sprintf(
                ':root{--a:%s;--af:%s;--ff:%s}@media (prefers-color-scheme:dark){:root{--a:%s;--af:%s}}',
                $light, Contrast::textOn($light), self::FONTS[$a->font] ?? self::FONTS['system'], $dark, Contrast::textOn($dark),
            ),
            'theme_color' => $light,
            'layout' => in_array($a->layout, TenantAppearance::LAYOUTS, true) ? $a->layout : 'list',
            'show_prices' => $a->show_prices,
            'show_photos' => $a->show_photos,
            'show_descriptions' => $a->show_descriptions,
            'show_calories' => $a->show_calories,
            'logo' => $a->logo?->urls(),
            'cover' => $a->cover ? ['urls' => $a->cover->urls(), 'placeholder' => $a->cover->placeholder, 'color' => $a->cover->dominant_color] : null,
        ];
    }
}

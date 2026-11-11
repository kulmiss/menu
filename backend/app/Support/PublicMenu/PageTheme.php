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
        'editorial' => ['light' => '#f3ebe0', 'dark' => '#231c18', 'heading' => 'serif', 'banner' => [16, 9], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw'],
        'grid' => ['light' => '#f5f7f6', 'dark' => '#18201d', 'heading' => 'system', 'banner' => [2, 1], 'text' => 'over', 'sizes' => '(min-width:44rem) 680px, calc(100vw - 1.5rem)'],
        'bistro' => ['light' => '#faf8f5', 'dark' => '#161412', 'heading' => 'serif', 'banner' => [5, 2], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw'],
    ];

    public const BANNER_TEXT = ['over', 'under'];

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
            'show_share' => $a->show_share ?? true,
            'show_pdf_link' => (bool) ($a->show_pdf_link ?? false),
            'logo' => $a->logo?->urls(),
            'cover' => $a->cover ? self::cover($a, $style) : null,
        ];
    }

    /**
     * The banner: sizes for srcset, its shape in this style and whether the
     * logo and name sit on it.
     *
     * @return array<string, mixed>
     */
    private static function cover(TenantAppearance $a, string $style): array
    {
        $urls = $a->cover?->urls() ?? [];
        $ratio = self::STYLES[$style]['banner'];

        return [
            'webp' => MenuPageData::srcset($urls),
            'avif' => MenuPageData::srcset($urls, 'avif'),
            'jpg' => $urls['jpg'] ?? '',
            'og' => $urls['og'] ?? null, // 1200 × 630, the link preview when the menu is shared
            'sizes' => self::STYLES[$style]['sizes'],
            'w' => 1280,
            'h' => (int) round(1280 * $ratio[1] / $ratio[0]),
            'placeholder' => $a->cover?->placeholder,
            'color' => $a->cover?->dominant_color,
            'over' => ($a->banner_text ?? self::STYLES[$style]['text']) === 'over',
        ];
    }
}

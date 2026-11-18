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
        // Street Board's headings: a tall sign-painter face where the phone has one, else the system font (no web fonts).
        'board' => '"Arial Narrow","Roboto Condensed","Helvetica Neue Condensed","Avenir Next Condensed",Impact,system-ui,sans-serif',
    ];

    /**
     * Per style: the surfaces the brand colour is drawn on (the least
     * contrasting one in light and in dark mode; must match style-*.css), the
     * heading font used while the restaurant keeps the default font, the
     * banner's shape and text place, and the dish photos: their width on
     * screen (`photo`, `lcp` for the first one), and how many load at once
     * without / with a banner (`fold`, §15.2.4). `dark_only` styles have one
     * palette for light and dark phones (light = dark surface).
     */
    public const STYLES = [
        'editorial' => ['light' => '#f3ebe0', 'dark' => '#231c18', 'heading' => 'serif', 'banner' => [16, 9], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '(min-width:44rem) 672px, 100vw', 'lcp' => '(min-width:44rem) 672px, 44vw', 'fold' => [2, 0]],
        'grid' => ['light' => '#f5f7f6', 'dark' => '#18201d', 'heading' => 'system', 'banner' => [2, 1], 'text' => 'over', 'sizes' => '(min-width:44rem) 680px, calc(100vw - 1.5rem)',
            'photo' => '(min-width:40rem) 220px, 50vw', 'lcp' => '(min-width:40rem) 220px, 40vw', 'fold' => [4, 0]],
        'bistro' => ['light' => '#faf8f5', 'dark' => '#161412', 'heading' => 'serif', 'banner' => [5, 2], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '104px', 'lcp' => '104px', 'fold' => [5, 0]],
        // D · Minimal: no dish photos at all, text list (owner, 2026-11-12).
        'minimal' => ['light' => '#f5f5f4', 'dark' => '#1a1a19', 'heading' => 'system', 'banner' => [5, 2], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '1px', 'lcp' => '1px', 'fold' => [0, 0]],
        // E · Mesob: warm, woven bands, round "platter" photos (owner, 2026-11-18).
        'mesob' => ['light' => '#f8f0e4', 'dark' => '#231a14', 'heading' => 'serif', 'banner' => [5, 2], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '76px', 'lcp' => '76px', 'fold' => [5, 0]],
        // F · Night Lounge: always dark, a soft glow in the brand colour (owner, 2026-11-18).
        'lounge' => ['light' => '#17131f', 'dark' => '#17131f', 'heading' => 'system', 'banner' => [2, 1], 'text' => 'over', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '68px', 'lcp' => '68px', 'fold' => [6, 0], 'dark_only' => true],
        // G · Street Board: a clean chalkboard, price tags (owner, 2026-11-18).
        'board' => ['light' => '#253530', 'dark' => '#253530', 'heading' => 'board', 'banner' => [2, 1], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '72px', 'lcp' => '72px', 'fold' => [5, 0], 'dark_only' => true],
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

        $css = sprintf(':root{--a:%s;--af:%s;--ff:%s;--fh:%s}', $light, Contrast::textOn($light), $font, $heading);
        if (! ($surface['dark_only'] ?? false)) {
            $css .= sprintf('@media (prefers-color-scheme:dark){:root{--a:%s;--af:%s}}', $dark, Contrast::textOn($dark));
        }

        return [
            'css' => $css,
            'theme_color' => $light,
            'style' => $style,
            'show_prices' => $a->show_prices,
            // Style D shows no dish or special photos, whatever the switch says.
            'show_photos' => $a->show_photos && $style !== 'minimal',
            'show_descriptions' => $a->show_descriptions,
            'show_calories' => $a->show_calories,
            'show_share' => $a->show_share ?? true,
            'show_pdf_link' => (bool) ($a->show_pdf_link ?? false),
            'show_other_branches' => (bool) ($a->show_other_branches ?? true),
            'show_order_list' => (bool) ($a->show_order_list ?? true),
            'logo' => ($a->show_logo ?? true) ? $a->logo?->urls() : null,
            'cover' => ($a->show_banner ?? true) && $a->cover ? self::cover($a, $style) : null,
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
            // 1200 × 630, the link preview when the menu is shared; ?v= so apps refetch after a change.
            'og' => isset($urls['og']) ? $urls['og'].'?v='.($a->cover->updated_at->timestamp ?? 0) : null,
            'sizes' => self::STYLES[$style]['sizes'],
            'w' => 1280,
            'h' => (int) round(1280 * $ratio[1] / $ratio[0]),
            'placeholder' => $a->cover?->placeholder,
            'color' => $a->cover?->dominant_color,
            'over' => ($a->banner_text ?? self::STYLES[$style]['text']) === 'over',
        ];
    }
}

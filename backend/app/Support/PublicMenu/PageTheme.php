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
        // H · Arabic's headings: Noto Kufi Arabic Bold (SIL Open Font License), two small subsets in
        // /menu-assets/fonts (Arabic, Latin; style-arabic.css). Shown only when it is ready at once
        // (font-display: optional), else the phone's own font: no layout shift, no wait.
        'kufi' => '"Menu Kufi",system-ui,"Segoe UI",Tahoma,"Noto Sans Arabic",sans-serif',
        // K · Black & White: a refined serif, the phone's own.
        'didone' => '"Didot","Bodoni 72","Bodoni MT","Iowan Old Style","Palatino Linotype",Georgia,"Noto Serif",serif',
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
        // H · Arabic: sand, the brand colour as a scalloped band, gold ornaments, Kufi headings, right-to-left when the menu is (owner, 2026-11-18).
        'arabic' => ['light' => '#f6efe2', 'dark' => '#172723', 'heading' => 'kufi', 'banner' => [5, 2], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '72px', 'lcp' => '72px', 'fold' => [5, 0]],
        // I · Islamic: star lattice, an arch-shaped header and arched photos in two columns, teal and cream (owner, 2026-11-18).
        'islamic' => ['light' => '#f7f3e8', 'dark' => '#132b28', 'heading' => 'serif', 'banner' => [5, 2], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '(min-width:40rem) 224px, 47vw', 'lcp' => '(min-width:40rem) 224px, 45vw', 'fold' => [4, 0]],
        // J · Somali: ocean band with waves, woven-basket stripe, aqal-dome marks (owner, 2026-11-18).
        'somali' => ['light' => '#f2f8fc', 'dark' => '#10263a', 'heading' => 'system', 'banner' => [2, 1], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '80px', 'lcp' => '80px', 'fold' => [5, 0]],
        // K · Black & White: monochrome, big serif, hairlines, dotted price leaders (owner, 2026-11-18).
        'mono' => ['light' => '#f4f4f4', 'dark' => '#1b1b1b', 'heading' => 'didone', 'banner' => [2, 1], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '88px', 'lcp' => '88px', 'fold' => [5, 0], 'mono' => true],
        // L · Colourful: one bright colour per category, made from the brand colour (owner, 2026-11-18).
        'colour' => ['light' => '#fffaf2', 'dark' => '#1e1b2e', 'heading' => 'rounded', 'banner' => [5, 2], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '72px', 'lcp' => '72px', 'fold' => [5, 0]],
        // M–Q (owner, 2026-11-19, QUESTIONS #232): each with its own artwork (`art`): a header picture
        // when there is no banner, textures and category icons, all switchable (show_decoration).
        // M · Coffee House: dark-roast band with a jebena and cups, cream page, round photos.
        'coffee' => ['light' => '#f5ece0', 'dark' => '#261b14', 'heading' => 'serif', 'banner' => [5, 2], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '72px', 'lcp' => '72px', 'fold' => [5, 0], 'art' => true],
        // N · Grill & BBQ: always dark, charcoal and embers, bold tall headings, price tags.
        'grill' => ['light' => '#231d1a', 'dark' => '#231d1a', 'heading' => 'board', 'banner' => [2, 1], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '80px', 'lcp' => '80px', 'fold' => [5, 0], 'dark_only' => true, 'art' => true],
        // O · Garden Fresh: leaves, soft greens, rounded white cards.
        'garden' => ['light' => '#f1f7ef', 'dark' => '#16291f', 'heading' => 'rounded', 'banner' => [5, 2], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '72px', 'lcp' => '72px', 'fold' => [5, 0], 'art' => true],
        // P · Coastal: sea and a dhow sail, sand, ticket-like cards.
        'coastal' => ['light' => '#f6f1e7', 'dark' => '#12304a', 'heading' => 'system', 'banner' => [2, 1], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '76px', 'lcp' => '76px', 'fold' => [5, 0], 'art' => true],
        // Q · Fine Dining: marble veins, thin gold lines, a refined serif, centred headings.
        'luxe' => ['light' => '#f7f4ee', 'dark' => '#1a1814', 'heading' => 'didone', 'banner' => [2, 1], 'text' => 'under', 'sizes' => '(min-width:44rem) 704px, 100vw',
            'photo' => '72px', 'lcp' => '72px', 'fold' => [5, 0], 'art' => true],
    ];

    /** L · Colourful: hue steps from the brand colour for categories 2–6 (category 1 is the brand colour). */
    private const PALETTE_STEPS = [0, 48, 150, 205, 265, 320];

    /** K · Black & White without its accent: ink on paper, paper on ink. */
    private const MONO_INK = ['light' => ['#111111', '#ffffff'], 'dark' => ['#f2f2f2', '#111111']];

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

        // K · Black & White keeps to ink and paper unless the restaurant turns its accent on.
        $mono = ($surface['mono'] ?? false) && ! $a->mono_accent;
        [$lightA, $lightF] = $mono ? self::MONO_INK['light'] : [$light, Contrast::textOn($light)];
        [$darkA, $darkF] = $mono ? self::MONO_INK['dark'] : [$dark, Contrast::textOn($dark)];

        $css = sprintf(':root{--a:%s;--af:%s;--ff:%s;--fh:%s%s}', $lightA, $lightF, $font, $heading, $style === 'colour' ? self::palette($primary, 'light') : '');
        if (! ($surface['dark_only'] ?? false)) {
            $css .= sprintf('@media (prefers-color-scheme:dark){:root{--a:%s;--af:%s%s}}', $darkA, $darkF, $style === 'colour' ? self::palette($primary, 'dark') : '');
        }

        return [
            'css' => $css,
            'theme_color' => $lightA,
            'style' => $style,
            // Style options, each used only in its own style (QUESTIONS #229).
            'direction' => in_array($style, TenantAppearance::DIRECTION_STYLES, true)
                ? (in_array($a->menu_direction, TenantAppearance::DIRECTIONS, true) ? $a->menu_direction : 'auto')
                : 'ltr',
            'halal' => $style === 'islamic' && $a->show_halal,
            'ramadan' => $style === 'islamic' && $a->ramadan_mode,
            'greyscale' => $style === 'mono' && $a->photos_greyscale,
            // M–Q: their artwork (header picture, textures, category icons) unless switched off.
            'decoration' => ($surface['art'] ?? false) && ($a->show_decoration ?? true),
            // M–Q show category photos (the restaurant's own pictures) in the category headings.
            'art' => (bool) ($surface['art'] ?? false),
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
     * L · Colourful: six bright category colours (--c1…--c6, the brand colour
     * first), a pale tint of each for its cards (--t1…--t6) and the text on
     * each colour (--o1…--o6: white or near-black, at least 4.5:1). The
     * colours are only ever backgrounds (pills, chips, "+"), never text, so
     * they can stay bright; the text on them is always readable.
     */
    public static function palette(string $primary, string $mode): string
    {
        [$h, $sat] = self::hsl($primary);
        $page = self::STYLES['colour'][$mode];
        $out = '';
        foreach (self::PALETTE_STEPS as $i => $step) {
            // The brand colour as it is; the others bright and clear, whatever the brand's own saturation.
            $c = $i === 0 ? $primary : self::fromHsl(fmod($h + $step, 360), max(0.7, min(0.9, $sat)), $mode === 'light' ? 0.52 : 0.6);
            // Mid-tones can miss 4.5:1 with both black and white: then the colour moves away from its text a little.
            $text = Contrast::textOn($c);
            $c = Contrast::readableOn($c, $text);
            $tint = self::mix($c, $page, $mode === 'light' ? 0.13 : 0.16);
            $out .= sprintf(';--c%1$d:%2$s;--t%1$d:%3$s;--o%1$d:%4$s', $i + 1, $c, $tint, $text);
        }

        return $out;
    }

    /** @return array{0: float, 1: float, 2: float} hue 0–360, saturation and lightness 0–1 */
    private static function hsl(string $hex): array
    {
        [$r, $g, $b] = array_map(fn (int $v) => $v / 255, Contrast::rgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        if ($d == 0) {
            return [0.0, 0.0, $l];
        }
        $s = $d / (1 - abs(2 * $l - 1));
        $h = match ($max) {
            $r => fmod(($g - $b) / $d + 6, 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h * 60, $s, $l];
    }

    private static function fromHsl(float $h, float $s, float $l): string
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return Contrast::hex((int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
    }

    /** $amount of $colour over $base (0 = base, 1 = colour). */
    private static function mix(string $colour, string $base, float $amount): string
    {
        $c = Contrast::rgb($colour);
        $b = Contrast::rgb($base);

        return Contrast::hex(...array_map(fn (int $i) => (int) round($b[$i] + ($c[$i] - $b[$i]) * $amount), [0, 1, 2]));
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

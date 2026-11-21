<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How a restaurant's guest menu looks (PLAN.md §4.4).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $logo_media_id
 * @property int|null $cover_media_id
 * @property string $primary_color "#b4432a"
 * @property string $font system|serif|rounded
 * @property string $style editorial|grid|bistro|minimal|mesob|lounge|board|arabic|islamic|somali|mono|colour (A · Warm editorial, B · Fresh grid, C · Quiet bistro, D · Minimal, E · Mesob, F · Night Lounge, G · Street Board, H · Arabic, I · Islamic, J · Somali, K · Black & White, L · Colourful)
 * @property bool $show_logo logo on the guest page (kept when off)
 * @property bool $show_banner banner on the guest page (kept when off)
 * @property string|null $banner_text over|under (logo and name on or below the banner); null = style default
 * @property bool $show_prices
 * @property bool $show_photos off: the text layout, whatever menu_layout says
 * @property string|null $menu_layout grid|list (owner, 2026-11-21); null = the style's own layout
 * @property bool $show_descriptions
 * @property bool $show_calories
 * @property bool $show_share "Share menu" button on the guest page
 * @property bool $show_pdf_link "Download menu (PDF)" link on the guest page
 * @property bool $show_other_branches "Other branches" list at the bottom of a branch's menu
 * @property bool $show_order_list "My order" list on the guest page (kept on the guest's phone)
 * @property string $menu_direction auto|ltr|rtl, style H only (auto: right-to-left when most of the menu is in Arabic script)
 * @property bool $show_halal "Halal" badge, style I only
 * @property bool $ramadan_mode crescent accent, style I only
 * @property bool $photos_greyscale black-and-white photos, style K only
 * @property bool $mono_accent brand colour as a small accent, style K only
 * @property int|null $theme_id
 * @property-read Media|null $logo
 * @property-read Media|null $cover
 */
#[Fillable(['logo_media_id', 'cover_media_id', 'primary_color', 'font', 'style', 'banner_text', 'show_prices', 'show_photos', 'menu_layout', 'show_descriptions', 'show_calories', 'show_share', 'show_pdf_link', 'show_order_list', 'show_other_branches', 'show_logo', 'show_banner', 'menu_direction', 'show_halal', 'ramadan_mode', 'photos_greyscale', 'mono_accent', 'show_decoration', 'theme_id'])]
class TenantAppearance extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_appearance';

    public const FONTS = ['system', 'serif', 'rounded'];

    /** Guest-menu styles; the order is the order of the admin picker. */
    public const STYLES = ['editorial', 'grid', 'bistro', 'minimal', 'mesob', 'lounge', 'board', 'arabic', 'islamic', 'somali', 'mono', 'colour', 'coffee', 'grill', 'garden', 'coastal', 'luxe'];

    /** Menu direction (style H): auto follows the menu's script. */
    public const DIRECTIONS = ['auto', 'ltr', 'rtl'];

    public const DEFAULT_STYLE = 'grid';

    /**
     * Layouts a restaurant can pick instead of its style's own (owner, 2026-11-21): photo cards
     * in two columns, or a list with small photos. The text layout is "show_photos" off.
     */
    public const LAYOUTS = ['grid', 'list'];

    /** Guest-page extras switched in Appearance (optional in API requests; added 2026-11-11). */
    public const EXTRA_FLAGS = ['show_share', 'show_pdf_link', 'show_order_list', 'show_other_branches', 'show_logo', 'show_banner', ...self::STYLE_FLAGS];

    /**
     * Options of one style each (added 2026-11-19): Halal and Ramadan mode (I), greyscale photos and
     * accent (K); the artwork of M–Q (header picture, textures, category icons) on or off (QUESTIONS #232).
     */
    public const STYLE_FLAGS = ['show_halal', 'ramadan_mode', 'photos_greyscale', 'mono_accent', 'show_decoration'];

    /** M–Q: styles with their own artwork, replaceable by the restaurant's banner and category photos. */
    public const ART_STYLES = ['coffee', 'grill', 'garden', 'coastal', 'luxe'];

    /** Styles with "Menu direction" (right-to-left for Arabic menus): H and M–Q (QUESTIONS #229, #232). */
    public const DIRECTION_STYLES = ['arabic', ...self::ART_STYLES];

    /** Values for a restaurant that never opened the Appearance screen. */
    public const DEFAULTS = [
        'logo_media_id' => null,
        'cover_media_id' => null,
        'primary_color' => '#b4432a',
        'font' => 'system',
        'style' => self::DEFAULT_STYLE,
        'banner_text' => null,
        'show_prices' => true,
        'show_photos' => true,
        'menu_layout' => null,
        'show_descriptions' => true,
        'show_calories' => true,
        'show_share' => true,
        'show_pdf_link' => false,
        'show_other_branches' => true,
        'show_logo' => true,
        'show_banner' => true,
        'show_order_list' => true,
        'menu_direction' => 'auto',
        'show_halal' => false,
        'ramadan_mode' => false,
        'photos_greyscale' => false,
        'mono_accent' => false,
        'show_decoration' => true,
        'theme_id' => null,
    ];

    protected function casts(): array
    {
        return [
            'show_prices' => 'boolean',
            'show_photos' => 'boolean',
            'show_descriptions' => 'boolean',
            'show_calories' => 'boolean',
            'show_share' => 'boolean',
            'show_pdf_link' => 'boolean',
            'show_other_branches' => 'boolean',
            'show_logo' => 'boolean',
            'show_banner' => 'boolean',
            'show_order_list' => 'boolean',
            'show_halal' => 'boolean',
            'ramadan_mode' => 'boolean',
            'photos_greyscale' => 'boolean',
            'mono_accent' => 'boolean',
            'show_decoration' => 'boolean',
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /**
     * The style to show: the saved one when the plan allows it, otherwise the
     * default (or the first allowed) style.
     *
     * @param  list<string>  $allowed
     */
    public static function effectiveStyle(?string $style, array $allowed): string
    {
        if (in_array($style, $allowed, true)) {
            return (string) $style;
        }

        return in_array(self::DEFAULT_STYLE, $allowed, true) || $allowed === [] ? self::DEFAULT_STYLE : $allowed[0];
    }

    /** The saved settings, or the defaults (not saved) when there are none yet. */
    public static function current(): self
    {
        return self::with(['logo', 'cover'])->first() ?? new self(self::DEFAULTS);
    }
}

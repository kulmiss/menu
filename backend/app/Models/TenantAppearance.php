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
 * @property string $style editorial|grid|bistro (A · Warm editorial, B · Fresh grid, C · Quiet bistro)
 * @property string|null $banner_text over|under (logo and name on or below the banner); null = style default
 * @property bool $show_prices
 * @property bool $show_photos
 * @property bool $show_descriptions
 * @property bool $show_calories
 * @property bool $show_share "Share menu" button on the guest page
 * @property bool $show_pdf_link "Download menu (PDF)" link on the guest page
 * @property bool $show_order_list "My order" list on the guest page (kept on the guest's phone)
 * @property int|null $theme_id
 * @property-read Media|null $logo
 * @property-read Media|null $cover
 */
#[Fillable(['logo_media_id', 'cover_media_id', 'primary_color', 'font', 'style', 'banner_text', 'show_prices', 'show_photos', 'show_descriptions', 'show_calories', 'show_share', 'show_pdf_link', 'show_order_list', 'theme_id'])]
class TenantAppearance extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_appearance';

    public const FONTS = ['system', 'serif', 'rounded'];

    /** Guest-menu styles; the order is the order of the admin picker. */
    public const STYLES = ['editorial', 'grid', 'bistro'];

    public const DEFAULT_STYLE = 'grid';

    /** Guest-page extras switched in Appearance (optional in API requests; added 2026-11-11). */
    public const EXTRA_FLAGS = ['show_share', 'show_pdf_link', 'show_order_list'];

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
        'show_descriptions' => true,
        'show_calories' => true,
        'show_share' => true,
        'show_pdf_link' => false,
        'show_order_list' => true,
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
            'show_order_list' => 'boolean',
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

<?php

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $category_id
 * @property array<string, string> $name
 * @property array<string, string> $summary
 * @property array<string, string> $description
 * @property int|null $price_minor null when the variants carry the prices
 * @property int|null $media_id
 * @property int|null $calories
 * @property int|null $prep_minutes
 * @property bool $is_sold_out
 * @property bool $is_visible
 * @property bool $is_new
 * @property Carbon|null $new_until
 * @property bool $is_popular
 * @property int $sort
 * @property-read Media|null $media
 * @property-read Category $category
 * @property-read Collection<int, ItemVariant> $variants
 * @property-read Collection<int, AddonGroup> $addonGroups
 * @property bool $show_photo false: the dish's photos stay saved but the menu doesn't show them
 * @property-read Collection<int, Tag> $tags
 * @property-read Collection<int, ItemAllergen> $allergenRows
 */
#[Fillable([
    'category_id', 'name', 'summary', 'description', 'price_minor', 'media_id', 'show_photo', 'calories',
    'prep_minutes', 'is_sold_out', 'is_visible', 'is_new', 'new_until', 'is_popular', 'sort',
])]
class Item extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'name' => Translatable::class,
            'summary' => Translatable::class,
            'description' => Translatable::class,
            'price_minor' => 'integer',
            'calories' => 'integer',
            'prep_minutes' => 'integer',
            'is_sold_out' => 'boolean',
            'is_visible' => 'boolean',
            'is_new' => 'boolean',
            'new_until' => 'date',
            'is_popular' => 'boolean',
            'show_photo' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ItemVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ItemVariant::class)->orderBy('sort')->orderBy('id');
    }

    /** @return BelongsToMany<AddonGroup, $this> */
    public function addonGroups(): BelongsToMany
    {
        return $this->belongsToMany(AddonGroup::class, 'item_addon_group')
            ->withPivot(['tenant_id', 'sort'])
            ->orderByPivot('sort');
    }

    /**
     * All photos of the dish in order (at most 5); the first is the main one
     * (also kept in media_id).
     *
     * @return BelongsToMany<Media, $this>
     */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'item_photos')
            ->withPivot(['tenant_id', 'sort'])
            ->orderByPivot('sort');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'item_tag')->withPivot('tenant_id')->orderBy('sort');
    }

    /** @return HasMany<ItemAllergen, $this> */
    public function allergenRows(): HasMany
    {
        return $this->hasMany(ItemAllergen::class);
    }

    /** @return list<string> */
    public function allergens(): array
    {
        return $this->allergenRows->pluck('allergen')->sort()->values()->all();
    }

    /** Lowest price a guest can pay: the item price or the cheapest variant. */
    public function fromPriceMinor(): ?int
    {
        if ($this->variants->isNotEmpty()) {
            return (int) $this->variants->min('price_minor');
        }

        return $this->price_minor;
    }
}

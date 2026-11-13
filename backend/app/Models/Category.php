<?php

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $menu_id
 * @property array<string, string> $name
 * @property array<string, string> $description
 * @property string|null $icon
 * @property int|null $media_id
 * @property bool $is_visible
 * @property int $sort
 * @property-read Media|null $media
 * @property-read Menu $menu
 * @property-read Collection<int, Item> $items
 */
#[Fillable(['menu_id', 'name', 'description', 'icon', 'media_id', 'is_visible', 'sort'])]
class Category extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'name' => Translatable::class,
            'description' => Translatable::class,
            'is_visible' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class)->orderBy('sort')->orderBy('id');
    }
}

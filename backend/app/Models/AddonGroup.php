<?php

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reusable choices such as "Extra toppings" (QUESTIONS #35). min/max are
 * shown to guests as information only — there is no ordering.
 *
 * @property int $id
 * @property array<string, string> $name
 * @property int $min_choices
 * @property int|null $max_choices
 * @property int $sort
 * @property-read Collection<int, AddonOption> $options
 */
#[Fillable(['name', 'min_choices', 'max_choices', 'sort'])]
class AddonGroup extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'name' => Translatable::class,
            'min_choices' => 'integer',
            'max_choices' => 'integer',
            'sort' => 'integer',
        ];
    }

    /** @return HasMany<AddonOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(AddonOption::class)->orderBy('sort')->orderBy('id');
    }

    /** @return BelongsToMany<Item, $this> */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_addon_group');
    }
}

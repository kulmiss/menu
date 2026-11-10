<?php

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A size or version of an item with its own price ("Small", "Large").
 *
 * @property int $id
 * @property int $item_id
 * @property array<string, string> $name
 * @property int $price_minor
 * @property bool $is_default
 * @property int $sort
 */
#[Fillable(['item_id', 'name', 'price_minor', 'is_default', 'sort'])]
class ItemVariant extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'name' => Translatable::class,
            'price_minor' => 'integer',
            'is_default' => 'boolean',
            'sort' => 'integer',
        ];
    }
}

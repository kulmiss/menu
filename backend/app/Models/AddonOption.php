<?php

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $addon_group_id
 * @property array<string, string> $name
 * @property int $price_minor
 * @property bool $is_sold_out
 * @property int $sort
 */
#[Fillable(['addon_group_id', 'name', 'price_minor', 'is_sold_out', 'sort'])]
class AddonOption extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'name' => Translatable::class,
            'price_minor' => 'integer',
            'is_sold_out' => 'boolean',
            'sort' => 'integer',
        ];
    }
}

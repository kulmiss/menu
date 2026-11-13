<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A branch's own price / sold-out / hidden for an item; null = inherit.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $item_id
 * @property int|null $price_minor
 * @property bool|null $is_sold_out
 * @property bool|null $is_hidden
 */
#[Fillable(['branch_id', 'item_id', 'price_minor', 'is_sold_out', 'is_hidden'])]
class BranchItemOverride extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'is_sold_out' => 'boolean', 'is_hidden' => 'boolean'];
    }
}

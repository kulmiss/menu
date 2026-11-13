<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $item_variant_id
 * @property int $price_minor
 */
#[Fillable(['branch_id', 'item_variant_id', 'price_minor'])]
class BranchVariantPrice extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['price_minor' => 'integer'];
    }
}

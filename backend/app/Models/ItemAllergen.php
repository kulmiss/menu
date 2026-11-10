<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One EU-14 allergen on an item (see config('menu.allergens')).
 *
 * @property int $item_id
 * @property string $allergen
 */
#[Fillable(['item_id', 'allergen'])]
class ItemAllergen extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'item_id';
}

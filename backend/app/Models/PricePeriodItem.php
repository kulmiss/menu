<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One special price in a period: for a dish without sizes (variant null) or
 * for one size. mode "fixed" = value is the new price (minor units);
 * "percent" = value is the % off.
 *
 * @property int $id
 * @property int $price_period_id
 * @property int $item_id
 * @property int|null $item_variant_id
 * @property string $mode
 * @property int $value
 */
#[Fillable(['price_period_id', 'item_id', 'item_variant_id', 'mode', 'value'])]
class PricePeriodItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['value' => 'integer'];
    }

    /** The special price for a normal price (minor units). */
    public function apply(int $normal): int
    {
        return $this->mode === 'percent' ? (int) round($normal * (100 - $this->value) / 100) : $this->value;
    }
}

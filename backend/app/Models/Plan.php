<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A subscription plan. `limits` holds counts per key of
 * config('menu.limit_keys'); null or missing = unlimited.
 *
 * @property int $id
 * @property array<string, string> $name
 * @property string $slug
 * @property array<string, int|null>|null $limits
 * @property int $trial_days
 * @property int $price_minor
 * @property int|null $price_year_minor price for a whole year when paid at once (null = not offered)
 * @property string $currency
 * @property string $interval
 * @property int|null $replaced_by_plan_id restaurants move to this plan when their paid time ends (QUESTIONS #184)
 */
#[Fillable(['name', 'slug', 'price_minor', 'price_year_minor', 'currency', 'interval', 'trial_days', 'limits', 'is_public', 'is_active', 'sort', 'replaced_by_plan_id'])]
class Plan extends Model
{
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'limits' => 'array',
            'price_minor' => 'integer',
            'price_year_minor' => 'integer',
            'replaced_by_plan_id' => 'integer',
            'trial_days' => 'integer',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}

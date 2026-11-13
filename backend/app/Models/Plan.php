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
 */
#[Fillable(['name', 'slug', 'price_minor', 'currency', 'interval', 'trial_days', 'limits', 'is_public', 'is_active', 'sort'])]
class Plan extends Model
{
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'limits' => 'array',
            'price_minor' => 'integer',
            'trial_days' => 'integer',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}

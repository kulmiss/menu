<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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

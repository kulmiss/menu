<?php

namespace App\Models;

use App\Casts\Translatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A look the platform offers restaurants (Super Admin manages them in Partition 11).
 *
 * @property int $id
 * @property array<string, string> $name
 * @property array{primary: string, font: string, style: string} $preset
 * @property bool $is_active
 * @property int $sort
 */
#[Fillable(['name', 'preset', 'is_active', 'sort'])]
class Theme extends Model
{
    protected function casts(): array
    {
        return [
            'name' => Translatable::class,
            'preset' => 'array',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * When a scheduled menu is shown: one row per weekday window.
 * ends_at earlier than starts_at means the window runs past midnight.
 *
 * @property int $id
 * @property int $menu_id
 * @property int $weekday 0 = Sunday … 6 = Saturday
 * @property string $starts_at HH:MM:SS
 * @property string $ends_at HH:MM:SS
 */
#[Fillable(['menu_id', 'weekday', 'starts_at', 'ends_at'])]
class MenuSchedule extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}

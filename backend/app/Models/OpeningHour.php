<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One opening window. branch_id null = the restaurant's hours, used by every
 * branch that has none of its own.
 *
 * @property int $id
 * @property int|null $branch_id
 * @property int $weekday 0 = Sunday … 6 = Saturday
 * @property string $opens_at HH:MM:SS
 * @property string $closes_at HH:MM:SS (≤ opens_at = past midnight)
 */
#[Fillable(['branch_id', 'weekday', 'opens_at', 'closes_at'])]
class OpeningHour extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}

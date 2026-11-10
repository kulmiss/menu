<?php

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A holiday or other closed period (whole days, restaurant time zone).
 *
 * @property int $id
 * @property int|null $branch_id null = every branch
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property array<string, string> $note
 */
#[Fillable(['branch_id', 'starts_on', 'ends_on', 'note'])]
class Closure extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'note' => Translatable::class];
    }
}

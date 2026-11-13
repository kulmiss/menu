<?php

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A time window with special prices ("Happy hour", Mon–Fri 16:00–18:00).
 *
 * @property int $id
 * @property int|null $branch_id null = every branch
 * @property array<string, string> $name
 * @property list<int> $weekdays 0 = Sunday … 6 = Saturday
 * @property string $starts_at HH:MM:SS, restaurant local time
 * @property string $ends_at HH:MM:SS; earlier than starts_at = the next day
 * @property bool $is_active
 * @property-read Collection<int, PricePeriodItem> $items
 */
#[Fillable(['branch_id', 'name', 'weekdays', 'starts_at', 'ends_at', 'is_active'])]
class PricePeriod extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['name' => Translatable::class, 'weekdays' => 'array', 'is_active' => 'boolean'];
    }

    /** @return HasMany<PricePeriodItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PricePeriodItem::class);
    }

    /** The end of the window that contains $now (local time), or null when outside it. */
    public function endsAfter(Carbon $now): ?Carbon
    {
        $start = substr($this->starts_at, 0, 5);
        $end = substr($this->ends_at, 0, 5);
        $time = $now->format('H:i');
        $days = array_map('intval', $this->weekdays);
        if ($start < $end) {
            return in_array($now->dayOfWeek, $days, true) && $time >= $start && $time < $end ? $now->copy()->setTimeFromTimeString($end) : null;
        }
        // Over midnight: today's evening part, or the morning part of a window that began yesterday.
        if (in_array($now->dayOfWeek, $days, true) && $time >= $start) {
            return $now->copy()->addDay()->setTimeFromTimeString($end);
        }
        if (in_array(($now->dayOfWeek + 6) % 7, $days, true) && $time < $end) {
            return $now->copy()->setTimeFromTimeString($end);
        }

        return null;
    }
}

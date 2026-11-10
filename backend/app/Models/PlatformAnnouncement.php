<?php

namespace App\Models;

use App\Casts\Translatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A message from the platform to restaurant admins.
 *
 * @property int $id
 * @property array<string, string> $title
 * @property array<string, string> $body
 * @property string $level info|warning
 * @property list<int>|null $plan_ids null = every restaurant
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
#[Fillable(['title', 'body', 'level', 'plan_ids', 'starts_at', 'ends_at', 'created_by'])]
class PlatformAnnouncement extends Model
{
    protected function casts(): array
    {
        return [
            'title' => Translatable::class,
            'body' => Translatable::class,
            'plan_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Showing now to a restaurant on this plan.
     *
     * @param  Builder<PlatformAnnouncement>  $query
     */
    public function scopeShowingFor(Builder $query, ?int $planId): void
    {
        $query->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('plan_ids')->when($planId !== null, fn ($q) => $q->orWhereJsonContains('plan_ids', $planId)));
    }
}

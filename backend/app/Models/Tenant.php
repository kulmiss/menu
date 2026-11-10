<?php

namespace App\Models;

use App\Enums\SubscriptionState;
use App\Support\PublicMenu\MenuPageCache;
use App\Support\SystemTags;
use App\Tenancy\CurrentTenant;
use Carbon\CarbonInterface;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $status
 * @property int|null $plan_id
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $paid_until
 * @property Carbon|null $suspended_at
 * @property string|null $suspended_reason
 * @property string $timezone
 * @property string $default_locale
 * @property string $currency_code
 * @property array<string, mixed>|null $currency_format
 * @property int $cache_version
 *
 * A restaurant: the tenant every restaurant-owned row belongs to.
 */
#[Fillable(['slug', 'name', 'plan_id', 'trial_ends_at', 'paid_until', 'timezone', 'default_locale', 'currency_code', 'currency_format'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'cache_version' => 1,
    ];

    protected static function booted(): void
    {
        // Every new restaurant starts with the built-in tags (vegan, spicy…).
        static::created(function (Tenant $tenant): void {
            app(CurrentTenant::class)->runAs($tenant, fn () => SystemTags::ensure());
        });
    }

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'paid_until' => 'datetime',
            'suspended_at' => 'datetime',
            'currency_format' => 'array',
            'cache_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
            ->withPivot(['role', 'can_edit_prices'])
            ->withTimestamps();
    }

    /** @return HasMany<TenantSlugHistory, $this> */
    public function slugHistory(): HasMany
    {
        return $this->hasMany(TenantSlugHistory::class);
    }

    /**
     * Where the subscription stands right now (see PLAN.md §4.2):
     * trial/active → grace (7 days, banner) → read-only (14 days, menu still
     * shown) → unavailable. Suspension by the Super Admin wins over all.
     */
    public function subscriptionState(?CarbonInterface $now = null): SubscriptionState
    {
        $now ??= now();

        if ($this->status === self::STATUS_SUSPENDED) {
            return SubscriptionState::Suspended;
        }

        if ($this->paid_until !== null && $now->lt($this->paid_until)) {
            return SubscriptionState::Active;
        }

        if ($this->trial_ends_at !== null && $now->lt($this->trial_ends_at)) {
            return SubscriptionState::Trial;
        }

        $expiredAt = $this->expiredAt();

        // Created by the Super Admin without dates: active until dates are set.
        if ($expiredAt === null) {
            return SubscriptionState::Active;
        }

        $graceEnds = $expiredAt->copy()->addDays((int) config('menu.grace_days'));
        $readOnlyEnds = $graceEnds->copy()->addDays((int) config('menu.read_only_days'));

        return match (true) {
            $now->lt($graceEnds) => SubscriptionState::Grace,
            $now->lt($readOnlyEnds) => SubscriptionState::ReadOnly,
            default => SubscriptionState::Unavailable,
        };
    }

    /** The moment the current stage of the expiry timeline ends, for banners. */
    public function subscriptionStageEndsAt(?CarbonInterface $now = null): ?CarbonInterface
    {
        $state = $this->subscriptionState($now);
        $expiredAt = $this->expiredAt();

        return match ($state) {
            SubscriptionState::Active => $this->paid_until,
            SubscriptionState::Trial => $this->trial_ends_at,
            SubscriptionState::Grace => $expiredAt?->copy()->addDays((int) config('menu.grace_days')),
            SubscriptionState::ReadOnly => $expiredAt?->copy()->addDays((int) config('menu.grace_days') + (int) config('menu.read_only_days')),
            default => null,
        };
    }

    /** The later of trial end and paid-until: when the subscription ran out. */
    private function expiredAt(): ?CarbonInterface
    {
        $dates = array_filter([$this->paid_until, $this->trial_ends_at]);

        if ($dates === []) {
            return null;
        }

        return max($dates);
    }

    /** Any change to menu data calls this so the cached public page is rebuilt. */
    public function bumpCacheVersion(): void
    {
        $this->increment('cache_version');
        // Drop the static guest page once the change is saved, so a visitor
        // racing the transaction can't store the old version again.
        DB::afterCommit(fn () => MenuPageCache::forget($this));
    }
}

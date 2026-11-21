<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;

/**
 * "My order" (the "+" buttons and the list guests show the waiter), one rule for
 * every part of the platform (owner, 2026-11-19, QUESTIONS #236):
 *
 *   guests see it only when the platform (Settings → Features), the restaurant's
 *   plan (or the Super Admin's override for that restaurant) AND the restaurant's
 *   own switch (Appearance → "Show 'Add to my order' (+) buttons") are all on.
 *
 * The reason says which level turned it off, the highest first, so the owner's
 * switch can say "Turned off by the platform" or "Not in your plan".
 */
final class MyOrder
{
    public const FEATURE = 'order_list';

    /**
     * @return array{enabled: bool, available: bool, restaurant: bool, reason: 'platform'|'plan'|'restaurant'|null}
     */
    public static function state(Tenant $tenant, ?TenantAppearance $appearance = null): array
    {
        $available = Features::enabled($tenant, self::FEATURE);
        $reason = null;
        if (! Features::globallyOn(self::FEATURE)) {
            $reason = 'platform';
        } elseif (! $available) {
            // Off for this restaurant: the Super Admin's own switch for it, or else the plan.
            $override = DB::table('tenant_feature_overrides')->where('tenant_id', $tenant->id)->where('feature_key', self::FEATURE)->value('enabled');
            $reason = $override !== null && ! (bool) $override ? 'platform' : 'plan';
        }
        $restaurant = (bool) (($appearance ?? self::appearance($tenant))->show_order_list ?? true);
        if ($reason === null && ! $restaurant) {
            $reason = 'restaurant';
        }

        return ['enabled' => $available && $restaurant, 'available' => $available, 'restaurant' => $restaurant, 'reason' => $reason];
    }

    public static function enabled(Tenant $tenant, ?TenantAppearance $appearance = null): bool
    {
        return self::state($tenant, $appearance)['enabled'];
    }

    private static function appearance(Tenant $tenant): ?TenantAppearance
    {
        return app(CurrentTenant::class)->runAs($tenant, fn () => TenantAppearance::query()->first());
    }
}

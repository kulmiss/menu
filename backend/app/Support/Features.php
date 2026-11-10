<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * On/off features per plan, with per-restaurant overrides from the Super
 * Admin (PLAN.md §4.1–4.2; QUESTIONS #60). A plan without a row for a
 * feature uses the default below, so existing plans keep working.
 */
class Features
{
    public const DEFAULTS = [
        'branches' => true,       // more than the main branch
        'promotions' => true,
        'insights' => true,
        'import_export' => true,
        'remove_branding' => false, // hide "Menu by …" on the guest page
    ];

    /** @var array<int, array<string, bool>> */
    private static array $memo = [];

    /** @return array<string, bool> */
    public static function for(Tenant $tenant): array
    {
        return self::$memo[$tenant->id] ??= (function () use ($tenant) {
            $plan = $tenant->plan_id === null ? [] : DB::table('plan_features')->where('plan_id', $tenant->plan_id)->pluck('enabled', 'feature_key')->all();
            $own = DB::table('tenant_feature_overrides')->where('tenant_id', $tenant->id)->pluck('enabled', 'feature_key')->all();
            $out = [];
            foreach (self::DEFAULTS as $key => $default) {
                $out[$key] = (bool) ($own[$key] ?? $plan[$key] ?? $default);
            }

            return $out;
        })();
    }

    public static function enabled(Tenant $tenant, string $key): bool
    {
        return self::for($tenant)[$key] ?? false;
    }

    public static function forget(?int $tenantId = null): void
    {
        if ($tenantId === null) {
            self::$memo = [];
        } else {
            unset(self::$memo[$tenantId]);
        }
    }
}

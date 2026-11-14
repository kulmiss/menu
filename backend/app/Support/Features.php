<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Tenancy\CurrentTenant;
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
        'pdf_menu' => true, // printable PDF menu (admin download, optional guest link)
        'order_list' => true, // "My order" list on the guest page (on the guest's phone only)
        // Guest-menu styles the restaurant may pick (owner, 2026-11-11: all on by default).
        'style_editorial' => true,
        'style_grid' => true,
        'style_bistro' => true,
        'style_minimal' => true, // D · Minimal, text only (owner, 2026-11-12)
        'price_periods' => true, // time-based prices: happy hour, lunch (owner, 2026-11-14; off on Free)
        // "Sold out" marks (owner, 2026-11-14). Off: no toggles, list or widget, and the guest
        // menu never says "sold out"; existing marks are kept but ignored.
        'sold_out' => true,
        'share' => true, // "Share" button on the guest menu (owner, 2026-11-14)
        'print_materials' => true, // table tents, posters, stickers (owner, 2026-11-14)
    ];

    /**
     * Switches that exist only for the whole platform (Settings → Features),
     * stored in their own settings; plans and restaurants don't have them.
     */
    public const PLATFORM_ONLY = ['signup' => 'signup_open'];

    /** @var array<int, array<string, bool>> */
    private static array $memo = [];

    /** @return array<string, bool> */
    public static function for(Tenant $tenant): array
    {
        return self::$memo[$tenant->id] ??= (function () use ($tenant) {
            $plan = $tenant->plan_id === null ? [] : DB::table('plan_features')->where('plan_id', $tenant->plan_id)->pluck('enabled', 'feature_key')->all();
            $own = DB::table('tenant_feature_overrides')->where('tenant_id', $tenant->id)->pluck('enabled', 'feature_key')->all();
            $global = self::global();
            $out = [];
            foreach (self::DEFAULTS as $key => $default) {
                // Global OFF (Settings → Features) wins over the plan and the restaurant (owner, 2026-11-14).
                $out[$key] = $global[$key] && (bool) ($own[$key] ?? $plan[$key] ?? $default);
            }

            return $out;
        })();
    }

    public static function enabled(Tenant $tenant, string $key): bool
    {
        return self::for($tenant)[$key] ?? false;
    }

    /**
     * The platform-wide switches: every plan feature plus the platform-only
     * ones; missing = on.
     *
     * @return array<string, bool>
     */
    public static function global(): array
    {
        $stored = PlatformSettings::get('features_global');
        $stored = is_array($stored) ? $stored : [];
        $out = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $out[$key] = (bool) ($stored[$key] ?? true);
        }
        foreach (self::PLATFORM_ONLY as $key => $setting) {
            $out[$key] = (bool) PlatformSettings::get($setting);
        }

        return $out;
    }

    public static function globallyOn(string $key): bool
    {
        return self::global()[$key] ?? false;
    }

    /** For the restaurant of this request (true outside one, e.g. in a console job). */
    public static function here(string $key): bool
    {
        $tenant = app(CurrentTenant::class)->get();

        return $tenant === null || self::enabled($tenant, $key);
    }

    /**
     * The menu styles this restaurant may use, in picker order.
     *
     * @return list<string>
     */
    public static function styles(Tenant $tenant): array
    {
        $on = self::for($tenant);

        return array_values(array_filter(TenantAppearance::STYLES, fn (string $s) => $on['style_'.$s] ?? false));
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

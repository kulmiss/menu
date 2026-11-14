<?php

namespace App\Support\Plans;

use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Features;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;

/**
 * The plan ladder (owner, 2026-11-13, QUESTIONS #184): Free → Plus → Pro →
 * Business. Dishes, categories, menus, tables/QR codes and team members are
 * unlimited on every plan; only photos (storage) and branches are counted.
 * People upgrade for business value: all menu styles, specials, Insights,
 * happy-hour prices, their own brand, more branches.
 *
 * Prices are suggestions in minor units (ETB cents); the Super Admin can edit
 * everything in Plans afterwards.
 */
final class PlanLadder
{
    /** Limits nobody pays for any more (null = unlimited on every plan). */
    public const UNLIMITED = ['menus', 'items', 'tables', 'members', 'languages'];

    /**
     * @return array<string, array{name: string, price: int, year: int|null, sort: int, limits: array<string, int|null>, features: array<string, bool>}>
     */
    public static function plans(): array
    {
        $all = array_fill_keys(array_keys(Features::DEFAULTS), true);
        $styles2 = ['style_editorial' => false, 'style_bistro' => false];

        return [
            'free' => [
                'name' => 'Free', 'price' => 0, 'year' => null, 'sort' => 0,
                'limits' => ['photos' => 100, 'branches' => 1],
                'features' => [...$all, ...$styles2, 'branches' => false, 'promotions' => false, 'insights' => false, 'price_periods' => false, 'remove_branding' => false],
            ],
            'plus' => [
                'name' => 'Plus', 'price' => 49_000, 'year' => 490_000, 'sort' => 1,
                'limits' => ['photos' => 500, 'branches' => 1],
                'features' => [...$all, 'branches' => false, 'price_periods' => false, 'remove_branding' => false],
            ],
            'pro' => [
                'name' => 'Pro', 'price' => 99_000, 'year' => 990_000, 'sort' => 2,
                'limits' => ['photos' => 2_000, 'branches' => 3],
                'features' => $all,
            ],
            'business' => [
                'name' => 'Business', 'price' => 249_000, 'year' => 2_490_000, 'sort' => 3,
                'limits' => [],
                'features' => $all,
            ],
        ];
    }

    /**
     * What a plan gives now: its limits (unlimited keys removed) and every feature (defaults filled in).
     *
     * @return array{limits: array<string, int|null>, features: array<string, bool>}
     */
    public static function effective(Plan $plan): array
    {
        $rows = DB::table('plan_features')->where('plan_id', $plan->id)->pluck('enabled', 'feature_key')->all();
        $features = [];
        foreach (Features::DEFAULTS as $key => $default) {
            $features[$key] = (bool) ($rows[$key] ?? $default);
        }
        $limits = [];
        foreach ((array) ($plan->limits ?? []) as $key => $value) {
            $limits[$key] = is_numeric($value) ? (int) $value : null;
        }

        return ['limits' => $limits, 'features' => $features];
    }

    /**
     * True when moving from $old to $new takes anything away: a lower or new
     * limit, or a feature switched off.
     *
     * @param  array{limits: array<string, int|null>, features: array<string, bool>}  $old
     * @param  array{limits: array<string, int|null>, features: array<string, bool>}  $new
     */
    public static function reduces(array $old, array $new): bool
    {
        foreach ($new['limits'] as $key => $limit) {
            $before = $old['limits'][$key] ?? null;
            if ($limit !== null && ($before === null || $limit < $before)) {
                return true;
            }
        }
        foreach ($old['features'] as $key => $on) {
            if ($on && ! ($new['features'][$key] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The best of both: the higher limit (unlimited wins) and every feature that either has.
     *
     * @param  array{limits: array<string, int|null>, features: array<string, bool>}  $a
     * @param  array{limits: array<string, int|null>, features: array<string, bool>}  $b
     * @return array{limits: array<string, int|null>, features: array<string, bool>}
     */
    public static function best(array $a, array $b): array
    {
        $limits = [];
        foreach (array_unique([...array_keys($a['limits']), ...array_keys($b['limits'])]) as $key) {
            $x = array_key_exists($key, $a['limits']) ? $a['limits'][$key] : null;
            $y = array_key_exists($key, $b['limits']) ? $b['limits'][$key] : null;
            // Missing from one side means unlimited there.
            if (! array_key_exists($key, $a['limits']) || ! array_key_exists($key, $b['limits']) || $x === null || $y === null) {
                continue;
            }
            $limits[$key] = max($x, $y);
        }
        $features = [];
        foreach ([...array_keys($a['features']), ...array_keys($b['features'])] as $key) {
            $features[$key] = ($a['features'][$key] ?? false) || ($b['features'][$key] ?? false);
        }

        return ['limits' => $limits, 'features' => $features];
    }

    /** @param array{limits: array<string, int|null>, features: array<string, bool>} $def */
    public static function write(Plan $plan, array $def): void
    {
        $plan->limits = array_filter($def['limits'], fn ($v) => $v !== null);
        $plan->save();
        foreach ($def['features'] as $key => $on) {
            DB::table('plan_features')->updateOrInsert(
                ['plan_id' => $plan->id, 'feature_key' => $key],
                ['enabled' => $on, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    /**
     * Puts the ladder in place (see the migration for the rules). Safe to run again.
     */
    public static function apply(): void
    {
        $now = now();
        $currency = (string) (PlatformSettings::get('default_currency') ?: 'ETB');
        foreach (PlanLadder::plans() as $slug => $def) {
            $target = ['limits' => $def['limits'], 'features' => $def['features']];
            $plan = Plan::where('slug', $slug)->first();
            if ($plan === null) {
                self::create($slug, $def, $currency, $target);

                continue;
            }
            $old = PlanLadder::effective($plan);
            if ($slug === 'free') {
                PlanLadder::write($plan, PlanLadder::best($old, $target));
                $plan->forceFill(['is_public' => true, 'is_active' => true, 'sort' => $def['sort']])->save();

                continue;
            }
            $protected = Tenant::where('plan_id', $plan->id)
                ->where(fn ($q) => $q->where('paid_until', '>', $now)->orWhere('trial_ends_at', '>', $now))
                ->exists();
            if (! PlanLadder::reduces($old, $target) || ! $protected) {
                PlanLadder::write($plan, $target);
                $plan->forceFill([
                    'is_public' => true, 'is_active' => true, 'sort' => $def['sort'],
                    'price_year_minor' => $plan->price_year_minor ?? ($plan->interval === 'month' && $plan->price_minor > 0 ? $plan->price_minor * 10 : null),
                ])->save();

                continue;
            }
            // Paying restaurants keep exactly what they have until their paid time ends.
            $legacy = $slug.'-legacy';
            for ($n = 2; Plan::where('slug', $legacy)->exists(); $n++) {
                $legacy = $slug.'-legacy-'.$n;
            }
            $name = (array) $plan->name;
            $plan->forceFill([
                'slug' => $legacy,
                'name' => array_map(fn ($v) => $v.' (old)', $name ?: ['en' => $def['name']]),
                'is_public' => false,
            ])->save();
            $new = self::create($slug, $def, $plan->currency ?: $currency, $target);
            $plan->forceFill(['replaced_by_plan_id' => $new->id])->save();
        }

        // Few plans on the website: other public plans are hidden (their restaurants keep them).
        Plan::whereNotIn('slug', array_keys(PlanLadder::plans()))->where('is_public', true)->update(['is_public' => false]);

        Features::forget();
        // Menus may now show other styles or drop "Menu by …": rebuild every page.
        Tenant::query()->each(fn (Tenant $t) => $t->bumpCacheVersion());
    }

    /**
     * @param  array{name: string, price: int, year: int|null, sort: int}  $def
     * @param  array{limits: array<string, int|null>, features: array<string, bool>}  $target
     */
    private static function create(string $slug, array $def, string $currency, array $target): Plan
    {
        $plan = new Plan;
        $plan->forceFill([
            'name' => ['en' => $def['name']], 'slug' => $slug, 'price_minor' => $def['price'], 'price_year_minor' => $def['year'],
            'currency' => $currency, 'interval' => 'month', 'trial_days' => 0, 'limits' => [],
            'is_public' => true, 'is_active' => true, 'sort' => $def['sort'],
        ]);
        PlanLadder::write($plan, $target);

        return $plan;
    }
}

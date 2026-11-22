<?php

namespace App\Support\PlatformStats;

use App\Http\Controllers\SiteController;
use App\Models\Tenant;
use App\Support\PlatformSettings;
use App\Support\Setup\SetupStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds the Super Admin dashboard's summary tables (owner, 2026-11-13,
 * QUESTIONS #186) from data the app already keeps: restaurants, plans,
 * payments, the anonymous daily menu counters and the activity log. Nothing
 * new is recorded about guests or users. "Edits" are restaurant team actions
 * in the activity log (not Super Admin actions, not while impersonating). A few grouped queries per run; the
 * per-day and per-restaurant numbers are then put together in PHP.
 */
final class Aggregator
{
    public const BACKFILL_DAYS = 400;

    private CarbonImmutable $today;

    public function __construct()
    {
        $this->today = CarbonImmutable::now()->startOfDay();
    }

    /** @return array<string, float> seconds per step */
    public function run(int $days): array
    {
        $timings = [];
        $t = microtime(true);
        $this->tenants();
        $timings['restaurants'] = round(microtime(true) - $t, 2);

        $t = microtime(true);
        if (DB::table('platform_daily')->doesntExist()) {
            $days = self::BACKFILL_DAYS;
        }
        $this->daily($this->today->subDays(max(1, $days) - 1), $this->today);
        $timings['days'] = round(microtime(true) - $t, 2);

        $t = microtime(true);
        $this->snapshot();
        $timings['snapshot'] = round(microtime(true) - $t, 2);
        Cache::forever(Stats::VERSION_KEY, now()->toIso8601String());

        return $timings;
    }

    /**
     * The website demo restaurants (owner, 2026-11-22): at a demo address and with only demo
     * logins (@example.com, like "Delete demo restaurants"). Counted only with "Include demo data".
     *
     * @return array<int, bool> restaurant id => true
     */
    public static function demoIds(): array
    {
        return array_fill_keys(SetupStatus::demo()->withTrashed()->whereIn('slug', SiteController::demoSlugs())
            ->pluck('id')->map(fn ($id) => (int) $id)->all(), true);
    }

    /** Team changes to the menu itself (dishes, categories, menus, add-ons) in the activity log. */
    private const MENU_ACTIONS = ['item.%', 'category.%', 'menu.%', 'addon_%'];

    /** Actions that can move a restaurant to another plan (their changes hold plan_id from/to). */
    private const PLAN_ACTIONS = ['platform.restaurant_updated', 'platform.plan_moved', 'platform.plan_changed'];

    /** One row per restaurant: activity windows, setup, usage, revenue and a health score. */
    public function tenants(): void
    {
        $d = fn (int $back) => $this->today->subDays($back)->toDateString();
        $tenants = Tenant::query()->with('plan')->get();
        $demo = self::demoIds();

        $stats = DB::table('stats_daily')->where('date', '>=', $d(89))
            ->whereIn('metric', ['menu_view', 'qr_scan', 'order_add'])
            ->groupBy('tenant_id', 'metric')
            ->selectRaw('tenant_id, metric,
                SUM(CASE WHEN date >= ? THEN count ELSE 0 END) AS d7,
                SUM(CASE WHEN date >= ? THEN count ELSE 0 END) AS d30,
                SUM(CASE WHEN date BETWEEN ? AND ? THEN count ELSE 0 END) AS p30,
                SUM(count) AS d90', [$d(6), $d(29), $d(59), $d(30)])
            ->get()->groupBy('tenant_id');
        $views = DB::table('stats_daily')->where('metric', 'menu_view')->groupBy('tenant_id')
            ->selectRaw('tenant_id, MAX(date) AS d, SUM(count) AS n')->get()->keyBy('tenant_id');
        $edits = DB::table('activity_log')->whereNotNull('tenant_id')->whereNotNull('actor_id')->where('action', 'not like', 'platform.%')->whereNull('impersonator_id')->groupBy('tenant_id')
            ->selectRaw('tenant_id, SUM(created_at >= ?) AS d7, SUM(created_at >= ?) AS d30, MAX(created_at) AS last', [$d(6), $d(29)])
            ->get()->keyBy('tenant_id');
        $members = DB::table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')->groupBy('memberships.tenant_id')
            ->selectRaw('memberships.tenant_id, COUNT(*) AS n, SUM(users.two_factor_confirmed_at IS NOT NULL) AS tfa, MAX(users.last_login_at) AS signed_in')
            ->get()->keyBy('tenant_id');
        $count = fn (string $table, ?callable $where = null) => tap(DB::table($table), fn ($q) => $where ? $where($q) : null)
            ->groupBy('tenant_id')->selectRaw('tenant_id, COUNT(*) AS n')->pluck('n', 'tenant_id');
        $photos = $count('media');
        $branches = $count('branches');
        $dishes = $count('items');
        $withPhoto = $count('items', fn ($q) => $q->whereNotNull('media_id'));
        $hours = DB::table('opening_hours')->distinct()->pluck('tenant_id')->flip();
        $look = DB::table('tenant_appearance')->get(['tenant_id', 'logo_media_id', 'cover_media_id', 'style', 'show_pdf_link'])->keyBy('tenant_id');
        // Dishes named in more than one language.
        $languages = DB::table('items')->whereRaw('JSON_LENGTH(name) > 1')->distinct()->pluck('tenant_id')->flip();
        $profile = DB::table('tenant_profiles')->get(['tenant_id', 'address', 'phone', 'whatsapp'])->keyBy('tenant_id');
        $periods = DB::table('price_periods')->where('is_active', true)->distinct()->pluck('tenant_id')->flip();
        $promotions = DB::table('promotions')->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->distinct()->pluck('tenant_id')->flip();
        $currency = (string) PlatformSettings::get('default_currency');
        $revenue = DB::table('payments')->where('paid_at', '>=', $d(364))->where('currency', $currency)->groupBy('tenant_id')
            ->selectRaw('tenant_id, SUM(CASE WHEN paid_at >= ? THEN amount_minor ELSE 0 END) AS d30,
                SUM(CASE WHEN paid_at >= ? THEN amount_minor ELSE 0 END) AS d90, SUM(amount_minor) AS d365', [$d(29), $d(89)])
            ->get()->keyBy('tenant_id');

        $paid = DB::table('payments')->groupBy('tenant_id')
            ->selectRaw('tenant_id, MIN(paid_at) AS first, MAX(period_end) AS until')->get()->keyBy('tenant_id');

        $rows = [];
        $now = now();
        foreach ($tenants as $tenant) {
            $id = $tenant->id;
            $s = $stats->get($id, collect())->keyBy('metric');
            $v = fn (string $metric, string $window) => (int) ($s->get($metric)->{$window} ?? 0);
            $nDishes = (int) ($dishes[$id] ?? 0);
            $l = $look->get($id);
            $p = $profile->get($id);
            $address = $p ? array_filter((array) json_decode((string) $p->address, true)) : [];
            $steps = [
                $l?->logo_media_id !== null,
                $l?->cover_media_id !== null,
                $nDishes >= 5,
                $nDishes > 0 && (int) ($withPhoto[$id] ?? 0) >= min(5, $nDishes),
                isset($hours[$id]),
                $p !== null && ($address !== [] || $p->phone || $p->whatsapp),
                $tenant->qr_downloaded_at !== null,
            ];
            $setup = (int) round(count(array_filter($steps)) * 100 / count($steps));
            $state = $tenant->subscriptionState();
            $views30 = $v('menu_view', 'd30');
            $edits30 = (int) ($edits[$id]->d30 ?? 0);
            $score = Health::score($setup, $views30, $edits30, $state, (int) ($tenant->plan->price_minor ?? 0) > 0);
            $limits = (array) ($tenant->plan->limits ?? []);
            $rows[] = [
                'tenant_id' => $id,
                'plan_id' => $tenant->plan_id,
                'state' => $state->value,
                'created_on' => $tenant->created_at?->toDateString() ?? $this->today->toDateString(),
                'confirmed' => $tenant->signup_state === null,
                'setup_pct' => $setup,
                'dishes' => $nDishes,
                'views_7d' => $v('menu_view', 'd7'),
                'views_30d' => $views30,
                'views_90d' => $v('menu_view', 'd90'),
                'views_prev_30d' => $v('menu_view', 'p30'),
                'views_total' => (int) ($views[$id]->n ?? 0),
                'scans_30d' => $v('qr_scan', 'd30'),
                'scans_90d' => $v('qr_scan', 'd90'),
                'edits_7d' => (int) ($edits[$id]->d7 ?? 0),
                'edits_30d' => $edits30,
                'last_edit_at' => $edits[$id]->last ?? null,
                'last_sign_in_at' => $members[$id]->signed_in ?? null,
                'last_view_on' => $views[$id]->d ?? null,
                'members' => (int) ($members[$id]->n ?? 0),
                'members_2fa' => (int) ($members[$id]->tfa ?? 0),
                'photos' => (int) ($photos[$id] ?? 0),
                'photos_limit' => isset($limits['photos']) ? (int) $limits['photos'] : null,
                'branches' => (int) ($branches[$id] ?? 0),
                'branches_limit' => isset($limits['branches']) ? (int) $limits['branches'] : null,
                'style' => $l?->style,
                'is_demo' => isset($demo[$id]),
                'chose_style' => $l !== null && $l->style !== null,
                'uses_photos' => (int) ($withPhoto[$id] ?? 0) > 0,
                'uses_pdf' => (bool) ($l->show_pdf_link ?? false),
                'uses_languages' => isset($languages[$id]),
                'locale' => $tenant->default_locale ?: null,
                'place' => self::place((string) $tenant->timezone),
                'uses_price_periods' => isset($periods[$id]),
                'uses_promotions' => isset($promotions[$id]),
                'uses_order_list' => $v('order_add', 'd30') > 0,
                'uses_print' => $tenant->qr_downloaded_at !== null,
                'revenue_30d_minor' => (int) ($revenue[$id]->d30 ?? 0),
                'revenue_90d_minor' => (int) ($revenue[$id]->d90 ?? 0),
                'revenue_365d_minor' => (int) ($revenue[$id]->d365 ?? 0),
                'first_paid_on' => isset($paid[$id]) ? CarbonImmutable::parse($paid[$id]->first)->toDateString() : null,
                'paid_until' => isset($paid[$id]) ? CarbonImmutable::parse($paid[$id]->until)->toDateString() : null,
                'score' => $score,
                'band' => Health::band($score, $views30, $edits30, $state, isset($members[$id]->signed_in) && $members[$id]->signed_in >= $this->today->subDays(29)->toDateTimeString()),
                'computed_at' => $now,
            ];
        }
        DB::transaction(function () use ($rows, $tenants) {
            DB::table('tenant_health')->whereNotIn('tenant_id', $tenants->pluck('id'))->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('tenant_health')->upsert($chunk, ['tenant_id']);
            }
        });
    }

    /** "Africa/Addis_Ababa" → "Addis Ababa"; null for UTC and other zones without a city. */
    public static function place(string $timezone): ?string
    {
        if (! str_contains($timezone, '/')) {
            return null;
        }

        return mb_substr(str_replace('_', ' ', (string) substr($timezone, (int) strrpos($timezone, '/') + 1)), 0, 60);
    }

    /**
     * Platform numbers per day and plan (plan 0 = all) for the days from $from to $to, twice:
     * without the demo restaurants (demo = 0) and with them (demo = 1).
     */
    public function daily(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $days = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $days[] = $day->toDateString();
        }
        $index = array_flip($days);
        $a = $from->toDateString();
        $b = $to->toDateString();
        $tenants = Tenant::withTrashed()->get(['id', 'plan_id', 'created_at', 'deleted_at']);
        $planOf = $tenants->mapWithKeys(fn (Tenant $t) => [$t->id => (int) $t->plan_id])->all();
        $demo = self::demoIds();
        $plans = array_values(array_unique([0, ...array_values($planOf)]));
        $zero = ['restaurants' => 0, 'new_restaurants' => 0, 'deleted_restaurants' => 0, 'activated' => 0, 'paying' => 0, 'active' => 0,
            'revenue_minor' => 0, 'mrr_minor' => 0, 'menu_views' => 0, 'qr_scans' => 0, 'dish_views' => 0, 'order_adds' => 0, 'order_lists' => 0, 'edits' => 0,
            'dishes_added' => 0, 'menu_changes' => 0, 'upgrades' => 0, 'downgrades' => 0, 'cancellations' => 0];
        $out = [];
        foreach ([0, 1] as $scope) {
            foreach ($plans as $plan) {
                foreach ($days as $day) {
                    $out[$scope][$plan][$day] = $zero;
                }
            }
        }
        // A demo restaurant counts only "with demo data"; a real one in both.
        // A restaurant without a plan (plan 0) counts once, in "all plans" (it was counted twice before).
        $add = function (int $plan, string $day, string $field, int $n, bool $isDemo) use (&$out) {
            foreach ($isDemo ? [1] : [0, 1] as $scope) {
                $out[$scope][0][$day][$field] += $n;
                if ($plan !== 0) {
                    $out[$scope][$plan][$day][$field] += $n;
                }
            }
        };
        $byTenant = fn (int $tenant, string $day, string $field, int $n) => $add($planOf[$tenant] ?? 0, $day, $field, $n, isset($demo[$tenant]));
        // Grouped in SQL by day, plan and demo (1/0), so thousands of restaurants stay a few rows a day.
        $ids = array_keys($demo);
        // (Ids are positive, so "IN (0)" is false for all: still a column expression, as GROUP BY needs.)
        $isDemoSql = fn (string $column) => '('.$column.' IN ('.($ids === [] ? '0' : implode(',', array_map('intval', $ids))).'))';

        // Restaurants that existed at the end of each day (+1 on the day made, -1 on
        // the day deleted, then a running total), new ones and deleted ones.
        $delta = [];
        $last = count($days);
        foreach ($tenants as $t) {
            $plan = $planOf[$t->id];
            $isDemo = isset($demo[$t->id]);
            $created = $t->created_at?->toDateString() ?? $a;
            $gone = $t->deleted_at?->toDateString();
            if (isset($index[$created])) {
                $add($plan, $created, 'new_restaurants', 1, $isDemo);
            }
            if ($gone !== null && isset($index[$gone])) {
                $add($plan, $gone, 'deleted_restaurants', 1, $isDemo);
            }
            if ($created > $b || ($gone !== null && $gone <= $a)) {
                continue;
            }
            $start = $created < $a ? 0 : $index[$created];
            $end = $gone === null || $gone > $b ? $last : $index[$gone];
            $key = $plan.':'.(int) $isDemo;
            $delta[$key][$start] = ($delta[$key][$start] ?? 0) + 1;
            $delta[$key][$end] = ($delta[$key][$end] ?? 0) - 1;
        }
        foreach ($delta as $key => $changes) {
            [$plan, $isDemo] = array_map('intval', explode(':', (string) $key));
            $running = 0;
            foreach ($days as $i => $day) {
                $running += $changes[$i] ?? 0;
                if ($running !== 0) {
                    $add($plan, $day, 'restaurants', $running, $isDemo === 1);
                }
            }
        }

        // Activated: the day a restaurant's menu had its first guest view.
        foreach (DB::table('stats_daily')->where('metric', 'menu_view')->groupBy('tenant_id')
            ->havingRaw('MIN(date) BETWEEN ? AND ?', [$a, $b])->selectRaw('tenant_id, MIN(date) AS d')->get() as $r) {
            $byTenant((int) $r->tenant_id, CarbonImmutable::parse($r->d)->toDateString(), 'activated', 1);
        }

        // Paying restaurants and MRR from the paid periods; revenue on the day paid.
        $currency = (string) PlatformSettings::get('default_currency');
        $paidDays = [];
        foreach (DB::table('payments')->where('period_end', '>=', $a)->where('period_start', '<=', $b)
            ->get(['tenant_id', 'period_start', 'period_end', 'amount_minor', 'currency']) as $p) {
            $start = CarbonImmutable::parse($p->period_start);
            $end = CarbonImmutable::parse($p->period_end);
            $months = max(1, (int) round($start->diffInDays($end) / 30.4375));
            $monthly = $p->currency === $currency ? intdiv((int) $p->amount_minor, $months) : 0;
            $first = max(0, (int) $from->diffInDays($start, false));
            $stop = min($last, (int) $from->diffInDays($end, false));
            for ($i = $first; $i < $stop; $i++) {
                $key = $days[$i];
                $paidDays[$p->tenant_id][$key] = true;
                if ($monthly > 0) {
                    $byTenant((int) $p->tenant_id, $key, 'mrr_minor', $monthly);
                }
            }
        }
        foreach ($paidDays as $tenant => $set) {
            foreach (array_keys($set) as $day) {
                $byTenant((int) $tenant, $day, 'paying', 1);
            }
        }
        foreach (DB::table('payments')->whereBetween('paid_at', [$a, $b])->where('currency', $currency)
            ->groupBy('tenant_id', 'paid_at')->selectRaw('tenant_id, paid_at, SUM(amount_minor) AS total')->get() as $r) {
            $byTenant((int) $r->tenant_id, CarbonImmutable::parse($r->paid_at)->toDateString(), 'revenue_minor', (int) $r->total);
        }

        // Menu counters, per plan, summed in SQL.
        $fields = ['menu_view' => 'menu_views', 'qr_scan' => 'qr_scans', 'item_view' => 'dish_views', 'order_add' => 'order_adds', 'order_shown' => 'order_lists'];
        $demoStats = $isDemoSql('stats_daily.tenant_id');
        foreach (DB::table('stats_daily')->join('tenants', 'tenants.id', '=', 'stats_daily.tenant_id')
            ->whereBetween('stats_daily.date', [$a, $b])->whereIn('stats_daily.metric', array_keys($fields))
            ->groupByRaw("stats_daily.date, tenants.plan_id, stats_daily.metric, {$demoStats}")
            ->selectRaw("stats_daily.date AS d, tenants.plan_id AS plan, stats_daily.metric AS m, {$demoStats} AS demo, SUM(stats_daily.count) AS n")->get() as $r) {
            $add((int) $r->plan, CarbonImmutable::parse($r->d)->toDateString(), $fields[$r->m], (int) $r->n, (bool) $r->demo);
        }

        // Team changes per day: all of them, new dishes, and changes to the menu itself.
        $fromTs = $from->toDateTimeString();
        $toTs = $to->endOfDay()->toDateTimeString();
        $team = fn () => DB::table('activity_log')->whereNotNull('activity_log.tenant_id')->whereNotNull('activity_log.actor_id')
            ->where('activity_log.action', 'not like', 'platform.%')->whereNull('activity_log.impersonator_id')
            ->whereBetween('activity_log.created_at', [$fromTs, $toTs]);
        $menuSql = implode(' OR ', array_fill(0, count(self::MENU_ACTIONS), 'activity_log.action LIKE ?'));
        $demoLog = $isDemoSql('activity_log.tenant_id');
        foreach ($team()->join('tenants', 'tenants.id', '=', 'activity_log.tenant_id')
            ->groupByRaw("DATE(activity_log.created_at), tenants.plan_id, {$demoLog}")
            ->selectRaw("DATE(activity_log.created_at) AS d, tenants.plan_id AS plan, {$demoLog} AS demo, COUNT(*) AS n,
                SUM(activity_log.action IN ('item.created', 'item.duplicated')) AS dishes, SUM({$menuSql}) AS menu", self::MENU_ACTIONS)
            ->get() as $r) {
            $day = CarbonImmutable::parse($r->d)->toDateString();
            $add((int) $r->plan, $day, 'edits', (int) $r->n, (bool) $r->demo);
            $add((int) $r->plan, $day, 'dishes_added', (int) $r->dishes, (bool) $r->demo);
            $add((int) $r->plan, $day, 'menu_changes', (int) $r->menu, (bool) $r->demo);
        }

        // Plan changes: up to a dearer plan, down to a cheaper paid one, or cancelled (to a free plan).
        $price = DB::table('plans')->pluck('price_minor', 'id')->map(fn ($p) => (int) $p)->all();
        // The restaurant is the log's subject (Super Admin changes are logged outside any restaurant).
        foreach (DB::table('activity_log')->whereIn('action', self::PLAN_ACTIONS)->where('subject_type', 'Tenant')
            ->whereBetween('created_at', [$fromTs, $toTs])->whereNotNull('changes')->get(['subject_id', 'changes', 'created_at']) as $r) {
            $change = (array) (json_decode((string) $r->changes, true)['plan_id'] ?? []);
            if (! array_key_exists('to', $change) || ! isset($planOf[(int) $r->subject_id])) {
                continue;
            }
            $before = $price[(int) ($change['from'] ?? 0)] ?? 0;
            $after = $price[(int) $change['to']] ?? 0;
            if ($before === $after) {
                continue;
            }
            $kind = $after > $before ? 'upgrades' : ($after === 0 ? 'cancellations' : 'downgrades');
            $byTenant((int) $r->subject_id, CarbonImmutable::parse($r->created_at)->toDateString(), $kind, 1);
        }

        // Restaurants active each day: menu views or team changes.
        $demoActive = $isDemoSql('x.tenant_id');
        $active = DB::query()->fromSub(
            DB::table('stats_daily')->where('metric', 'menu_view')->whereBetween('date', [$a, $b])->selectRaw('date AS d, tenant_id')
                ->union($team()->selectRaw('DATE(activity_log.created_at) AS d, activity_log.tenant_id')),
            'x')->join('tenants', 'tenants.id', '=', 'x.tenant_id')
            ->groupByRaw("x.d, tenants.plan_id, {$demoActive}")
            ->selectRaw("x.d AS d, tenants.plan_id AS plan, {$demoActive} AS demo, COUNT(DISTINCT x.tenant_id) AS n")->get();
        foreach ($active as $r) {
            $add((int) $r->plan, CarbonImmutable::parse($r->d)->toDateString(), 'active', (int) $r->n, (bool) $r->demo);
        }

        $rows = [];
        foreach ($out as $scope => $perPlan) {
            foreach ($perPlan as $plan => $perDay) {
                foreach ($perDay as $day => $values) {
                    $rows[] = ['demo' => $scope, 'date' => $day, 'plan_id' => $plan, ...$values];
                }
            }
        }
        DB::transaction(function () use ($rows, $a, $b) {
            DB::table('platform_daily')->whereBetween('date', [$a, $b])->delete();
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('platform_daily')->insert($chunk);
            }
        });
    }

    /** Team members, two-factor and active admins, per plan (0 = all), without and with the demo restaurants. */
    public function snapshot(): void
    {
        DB::table('platform_snapshot')->upsert([
            ['key' => 'admins', 'value' => json_encode($this->admins(false)), 'computed_at' => now()],
            ['key' => 'admins_demo', 'value' => json_encode($this->admins(true)), 'computed_at' => now()],
        ], ['key']);
    }

    /** @return array<int, array<string, int>> */
    private function admins(bool $withDemo): array
    {
        $skip = $withDemo ? [] : array_keys(self::demoIds());
        $rows = DB::table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')
            ->join('tenants', 'tenants.id', '=', 'memberships.tenant_id')->whereNull('tenants.deleted_at')
            ->whereNotIn('tenants.id', $skip)
            ->groupBy('tenants.plan_id', 'memberships.role')
            ->selectRaw('tenants.plan_id AS plan, memberships.role AS role, COUNT(*) AS n, SUM(users.two_factor_confirmed_at IS NOT NULL) AS tfa')->get();
        // Team members active in a window: signed in (last sign-in, QUESTIONS #187) or made a change.
        $people = function (int $days) use ($skip): array {
            $since = $this->today->subDays($days - 1);
            $changed = DB::table('activity_log')->join('tenants', 'tenants.id', '=', 'activity_log.tenant_id')->whereNull('tenants.deleted_at')
                ->whereNotIn('tenants.id', $skip)
                ->whereNotNull('activity_log.actor_id')->where('activity_log.action', 'not like', 'platform.%')->whereNull('activity_log.impersonator_id')
                ->where('activity_log.created_at', '>=', $since)->distinct()->get(['tenants.plan_id AS plan', 'activity_log.actor_id AS user']);
            $signedIn = DB::table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')->join('tenants', 'tenants.id', '=', 'memberships.tenant_id')
                ->whereNull('tenants.deleted_at')->whereNotIn('tenants.id', $skip)->where('users.last_login_at', '>=', $since)->distinct()->get(['tenants.plan_id AS plan', 'users.id AS user']);
            $sets = ['active' => [], 'signed_in' => []];
            foreach ([['active', $changed], ['active', $signedIn], ['signed_in', $signedIn]] as [$kind, $list]) {
                foreach ($list as $r) {
                    // A restaurant without a plan counts once, in "all plans".
                    foreach (array_unique([(int) $r->plan, 0]) as $plan) {
                        $sets[$kind][$plan][(int) $r->user] = true;
                    }
                }
            }

            return $sets;
        };
        $w7 = $people(7);
        $w30 = $people(30);
        $value = [];
        $blank = ['members' => 0, 'owners' => 0, 'managers' => 0, 'staff' => 0, 'with_2fa' => 0, 'owners_2fa' => 0,
            'active_7d' => 0, 'active_30d' => 0, 'signed_in_7d' => 0, 'signed_in_30d' => 0];
        foreach ($rows as $r) {
            foreach (array_unique([(int) $r->plan, 0]) as $plan) {
                $value[$plan] ??= $blank;
                $value[$plan]['members'] += (int) $r->n;
                $value[$plan]['with_2fa'] += (int) $r->tfa;
                $key = ['owner' => 'owners', 'manager' => 'managers', 'staff' => 'staff'][$r->role] ?? 'staff';
                $value[$plan][$key] += (int) $r->n;
                if ($r->role === 'owner') {
                    $value[$plan]['owners_2fa'] += (int) $r->tfa;
                }
            }
        }
        foreach ($value as $plan => $_) {
            $value[$plan]['active_7d'] = count($w7['active'][$plan] ?? []);
            $value[$plan]['active_30d'] = count($w30['active'][$plan] ?? []);
            $value[$plan]['signed_in_7d'] = count($w7['signed_in'][$plan] ?? []);
            $value[$plan]['signed_in_30d'] = count($w30['signed_in'][$plan] ?? []);
        }

        return $value ?: [0 => $blank];
    }
}

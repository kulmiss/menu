<?php

namespace App\Support\PlatformStats;

use App\Models\Plan;
use App\Support\PlatformSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Reads the Super Admin dashboard numbers from the summary tables only
 * (platform_daily, tenant_health, platform_snapshot) that `platform:aggregate`
 * fills (QUESTIONS #186). Each widget is cached for five minutes per range and
 * plan; a new aggregate run starts fresh caches.
 */
final class Stats
{
    public const VERSION_KEY = 'platform:stats:version';

    public const TTL = 300;

    public function __construct(
        private readonly CarbonImmutable $from,
        private readonly CarbonImmutable $to,
        private readonly int $plan = 0,
        // With the website demo restaurants ("Include demo data", owner 2026-11-22).
        private readonly bool $demo = false,
    ) {}

    /** @param  callable(): array<string, mixed>  $build
     * @return array<string, mixed> */
    public function cached(string $widget, callable $build): array
    {
        $key = implode(':', ['platform:stats', (string) Cache::get(self::VERSION_KEY, '0'), $widget, $this->from->toDateString(), $this->to->toDateString(), $this->plan, (int) $this->demo]);

        return Cache::remember($key, self::TTL, fn () => [...$build(), 'computed_at' => Cache::get(self::VERSION_KEY)]);
    }

    private function days(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $this->level()->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    /** The day rows of the chosen plan and demo choice. */
    private function level(): Builder
    {
        return DB::table('platform_daily')->where('demo', $this->demo)->where('plan_id', $this->plan);
    }

    private function health(): Builder
    {
        return DB::table('tenant_health')->when($this->plan > 0, fn ($q) => $q->where('plan_id', $this->plan))
            ->when(! $this->demo, fn ($q) => $q->where('is_demo', false));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function previous(): array
    {
        $length = (int) $this->from->diffInDays($this->to) + 1;

        return [$this->from->subDays($length), $this->from->subDay()];
    }

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $flows = ['new_restaurants', 'deleted_restaurants', 'activated', 'revenue_minor', 'menu_views', 'qr_scans', 'dish_views', 'order_adds', 'order_lists', 'edits',
            'dishes_added', 'menu_changes', 'upgrades', 'downgrades', 'cancellations'];
        $sql = implode(', ', array_map(fn (string $f) => "COALESCE(SUM({$f}),0) AS {$f}", $flows));
        $sums = fn (CarbonImmutable $a, CarbonImmutable $b) => (array) $this->days($a, $b)->selectRaw(
            $sql.', COALESCE(ROUND(AVG(active)),0) AS active, COALESCE(ROUND(AVG(NULLIF(paying, 0))),0) AS avg_paying')->first();
        $at = fn (CarbonImmutable $day) => (array) ($this->level()->where('date', '<=', $day->toDateString())
            ->orderByDesc('date')->first(['restaurants', 'paying', 'mrr_minor']) ?? ['restaurants' => 0, 'paying' => 0, 'mrr_minor' => 0]);
        [$pa, $pb] = $this->previous();
        $now = $sums($this->from, $this->to) + $at($this->to);
        $before = $sums($pa, $pb) + $at($pb);
        $kpi = fn (string $key) => ['value' => (int) $now[$key], 'previous' => (int) $before[$key]];
        // Average per paying restaurant: the period's revenue over the restaurants paying on an average day.
        $arppu = fn (array $x) => (int) $x['avg_paying'] > 0 ? intdiv((int) $x['revenue_minor'], (int) $x['avg_paying']) : 0;
        $paused = $this->health()->whereIn('state', ['read_only', 'unavailable', 'suspended'])->count();

        $series = $this->days($this->from, $this->to)->orderBy('date')
            ->get(['date', 'new_restaurants', 'activated', 'deleted_restaurants', 'menu_views', 'qr_scans', 'revenue_minor', 'active', 'paying', 'restaurants',
                'mrr_minor', 'dishes_added', 'menu_changes', 'order_adds', 'order_lists', 'upgrades', 'downgrades', 'cancellations'])
            ->map(fn ($r) => ['date' => (string) $r->date] + array_map('intval', array_diff_key((array) $r, ['date' => 1])))->all();

        return [
            'currency' => (string) PlatformSettings::get('default_currency'),
            // "No payments yet" instead of zeros that look like data.
            'has_payments' => DB::table('payments')->when(! $this->demo, fn ($q) => $q->whereNotIn('tenant_id', array_keys(Aggregator::demoIds())))->exists(),
            'kpis' => [
                'restaurants' => $kpi('restaurants'),
                'deleted_restaurants' => $kpi('deleted_restaurants'),
                'activated' => $kpi('activated'),
                // Now only (no history): menus switched off or read only.
                'paused' => ['value' => $paused, 'previous' => null],
                'upgrades' => $kpi('upgrades'),
                'downgrades' => $kpi('downgrades'),
                'cancellations' => $kpi('cancellations'),
                'arppu_minor' => ['value' => $arppu($now), 'previous' => $arppu($before)],
                'dishes_added' => $kpi('dishes_added'),
                'menu_changes' => $kpi('menu_changes'),
                'order_adds' => $kpi('order_adds'),
                'order_lists' => $kpi('order_lists'),
                'dish_views' => $kpi('dish_views'),
                'active' => $kpi('active'),
                'new_restaurants' => $kpi('new_restaurants'),
                'paying' => $kpi('paying'),
                'free' => ['value' => (int) $now['restaurants'] - (int) $now['paying'], 'previous' => (int) $before['restaurants'] - (int) $before['paying']],
                'mrr_minor' => $kpi('mrr_minor'),
                'revenue_minor' => $kpi('revenue_minor'),
                'menu_views' => $kpi('menu_views'),
                'qr_scans' => $kpi('qr_scans'),
            ],
            'series' => $this->bucket($series),
        ];
    }

    /** @return array<string, mixed> */
    public function growth(): array
    {
        $a = $this->from->toDateString();
        $b = $this->to->toDateString();
        $weeks = [];
        foreach ($this->days($this->from, $this->to)->orderBy('date')->get(['date', 'new_restaurants']) as $r) {
            $week = CarbonImmutable::parse($r->date)->startOfWeek()->toDateString();
            $weeks[$week] = ($weeks[$week] ?? 0) + (int) $r->new_restaurants;
        }
        $joined = fn () => $this->health()->whereBetween('created_on', [$a, $b]);
        $f = (array) $joined()->selectRaw('COUNT(*) AS signed_up, COALESCE(SUM(confirmed),0) AS confirmed, COALESCE(SUM(dishes >= 5),0) AS dishes,
            COALESCE(SUM(uses_print),0) AS qr, COALESCE(SUM(last_view_on IS NOT NULL),0) AS views, COALESCE(SUM(first_paid_on IS NOT NULL),0) AS paid')->first();
        $first = max(1, (int) $f['signed_up']);
        $funnel = array_map(fn (string $k) => ['key' => $k, 'count' => (int) $f[$k], 'percent' => (int) round((int) $f[$k] * 100 / $first)],
            ['signed_up', 'confirmed', 'dishes', 'qr', 'views', 'paid']);

        // Churn: paid periods that ended in the range and were not renewed.
        $payingAtStart = (int) ($this->level()->where('date', '<=', $a)->orderByDesc('date')->value('paying') ?? 0);
        $churned = $this->health()->whereBetween('paid_until', [$a, min($b, CarbonImmutable::today()->subDay()->toDateString())])->count();

        $cohorts = $this->health()->where('created_on', '>=', CarbonImmutable::today()->startOfMonth()->subMonths(11)->toDateString())
            ->groupByRaw("DATE_FORMAT(created_on, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(created_on, '%Y-%m') AS month, COUNT(*) AS signed_up, SUM(band <> 'inactive') AS active, SUM(paid_until >= CURDATE()) AS paying")
            ->orderBy('month')->get()
            ->map(fn ($r) => ['month' => $r->month, 'signed_up' => (int) $r->signed_up, 'active' => (int) $r->active, 'paying' => (int) $r->paying])->all();

        return [
            'weeks' => array_map(fn ($w, $n) => ['week' => $w, 'count' => $n], array_keys($weeks), array_values($weeks)),
            'funnel' => $funnel,
            'conversion' => ['signed_up' => (int) $f['signed_up'], 'paid' => (int) $f['paid'], 'percent' => (int) $f['signed_up'] > 0 ? round((int) $f['paid'] * 100 / (int) $f['signed_up'], 1) : null],
            'churn' => ['paying_at_start' => $payingAtStart, 'churned' => $churned, 'percent' => $payingAtStart > 0 ? round($churned * 100 / $payingAtStart, 1) : null],
            'cohorts' => $cohorts,
        ];
    }

    /** @return array<string, mixed> */
    public function engagement(): array
    {
        $h = (array) $this->health()->selectRaw("COUNT(*) AS total, COALESCE(SUM(edits_7d > 0),0) AS edited_7d, COALESCE(SUM(edits_30d > 0),0) AS edited_30d,
            COALESCE(SUM(last_sign_in_at >= ?),0) AS signed_in_7d, COALESCE(SUM(last_sign_in_at >= ?),0) AS signed_in_30d,
            COALESCE(SUM(views_7d > 0 OR edits_7d > 0 OR last_sign_in_at >= ?),0) AS active_7d,
            COALESCE(SUM(views_30d > 0 OR edits_30d > 0 OR last_sign_in_at >= ?),0) AS active_30d, MAX(last_sign_in_at) AS last_sign_in,
            COALESCE(ROUND(AVG(setup_pct)),0) AS setup, COALESCE(SUM(setup_pct = 100),0) AS setup_done, MAX(last_edit_at) AS last_edit,
            COALESCE(SUM(band = 'healthy'),0) AS healthy, COALESCE(SUM(band = 'at_risk'),0) AS at_risk, COALESCE(SUM(band = 'inactive'),0) AS inactive", [$d7 = CarbonImmutable::today()->subDays(6)->toDateTimeString(), $d30 = CarbonImmutable::today()->subDays(29)->toDateTimeString(), $d7, $d30])->first();
        $weeks = max(1, ((int) $this->from->diffInDays($this->to) + 1) / 7);
        $edits = (int) $this->days($this->from, $this->to)->sum('edits');
        $list = fn (string $band, string $order) => $this->health()->join('tenants', 'tenants.id', '=', 'tenant_health.tenant_id')->whereNull('tenants.deleted_at')
            ->where('band', $band)->orderBy('score', $order)->orderBy('tenant_id')->limit(5)
            ->get(['tenant_id AS id', 'tenants.name', 'tenants.slug', 'score', 'setup_pct', 'views_30d', 'edits_30d', 'last_edit_at', 'last_view_on'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'slug' => $r->slug, 'score' => (int) $r->score, 'setup_pct' => (int) $r->setup_pct,
                'views_30d' => (int) $r->views_30d, 'edits_30d' => (int) $r->edits_30d, 'last_edit_at' => $r->last_edit_at ? CarbonImmutable::parse($r->last_edit_at)->toIso8601String() : null,
                'last_view_on' => $r->last_view_on])->all();

        return [
            'total' => (int) $h['total'],
            'edited_7d' => (int) $h['edited_7d'],
            'edited_30d' => (int) $h['edited_30d'],
            'signed_in_7d' => (int) $h['signed_in_7d'],
            'signed_in_30d' => (int) $h['signed_in_30d'],
            'last_sign_in_at' => $h['last_sign_in'] ? CarbonImmutable::parse($h['last_sign_in'])->toIso8601String() : null,
            'active_7d' => (int) $h['active_7d'],
            'active_30d' => (int) $h['active_30d'],
            'last_edit_at' => $h['last_edit'] ? CarbonImmutable::parse($h['last_edit'])->toIso8601String() : null,
            'edits_per_week' => round($edits / $weeks, 1),
            'setup_avg' => (int) $h['setup'],
            'setup_done' => (int) $h['setup_done'],
            'bands' => ['healthy' => (int) $h['healthy'], 'at_risk' => (int) $h['at_risk'], 'inactive' => (int) $h['inactive']],
            'lists' => ['healthy' => $list('healthy', 'desc'), 'at_risk' => $list('at_risk', 'asc'), 'inactive' => $list('inactive', 'asc')],
        ];
    }

    /** @return array<string, mixed> */
    public function usage(): array
    {
        $u = (array) $this->health()->selectRaw('COUNT(*) AS total, COALESCE(SUM(uses_price_periods),0) AS price_periods, COALESCE(SUM(uses_promotions),0) AS promotions,
            COALESCE(SUM(uses_order_list),0) AS order_list, COALESCE(SUM(uses_print),0) AS print,
            COALESCE(SUM(uses_photos),0) AS photos, COALESCE(SUM(uses_pdf),0) AS pdf, COALESCE(SUM(uses_languages),0) AS languages,
            COALESCE(SUM(photos_limit IS NOT NULL AND photos >= photos_limit),0) AS limit_photos,
            COALESCE(SUM(branches_limit IS NOT NULL AND branches >= branches_limit),0) AS limit_branches,
            COALESCE(SUM(photos_limit IS NOT NULL AND photos >= photos_limit * 0.8 AND photos < photos_limit),0) AS near_photos')->first();
        $styles = $this->health()->groupBy('style')->selectRaw("COALESCE(style, 'editorial') AS style, COUNT(*) AS n")->orderByDesc('n')->pluck('n', 'style')
            ->map(fn ($n) => (int) $n)->all();

        return [
            'total' => (int) $u['total'],
            'features' => array_map(fn (string $k) => ['key' => $k, 'count' => (int) $u[$k]], ['photos', 'order_list', 'print', 'languages', 'pdf', 'promotions', 'price_periods']),
            'styles' => array_map(fn ($s, $n) => ['style' => $s, 'count' => $n], array_keys($styles), array_values($styles)),
            'limits' => [
                ['key' => 'limit_photos', 'count' => (int) $u['limit_photos']],
                ['key' => 'near_photos', 'count' => (int) $u['near_photos']],
                ['key' => 'limit_branches', 'count' => (int) $u['limit_branches']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function top(): array
    {
        // Rankings use the window nearest to the chosen range (QUESTIONS #186).
        $length = (int) $this->from->diffInDays($this->to) + 1;
        $window = $length <= 7 ? '7d' : ($length <= 30 ? '30d' : '90d');
        $scanWindow = $length <= 30 ? '30d' : '90d';
        $revenueWindow = $length <= 30 ? '30d' : ($length <= 90 ? '90d' : '365d');
        $rank = fn (string $expr) => $this->health()->join('tenants', 'tenants.id', '=', 'tenant_health.tenant_id')->whereNull('tenants.deleted_at')
            ->selectRaw("tenant_id AS id, tenants.name, tenants.slug, {$expr} AS total")->havingRaw('total > 0')->orderByDesc('total')->orderBy('tenant_id')->limit(10)
            ->get()->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'slug' => $r->slug, 'total' => (int) $r->total])->all();

        // Menu views per day of the top 10 over the chosen range (at most 400 days), for the small line.
        $views = $rank("views_{$window}");
        $ids = array_column($views, 'id');
        $daily = $ids === [] ? collect() : DB::table('stats_daily')->whereIn('tenant_id', $ids)->where('metric', 'menu_view')
            ->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
            ->groupBy('tenant_id', 'date')->selectRaw('tenant_id, date, SUM(count) AS n')->get()->groupBy('tenant_id');
        $length = (int) $this->from->diffInDays($this->to) + 1;
        foreach ($views as $i => $row) {
            $byDay = $daily->get($row['id'], collect())->mapWithKeys(fn ($r) => [CarbonImmutable::parse($r->date)->toDateString() => (int) $r->n]);
            $points = [];
            for ($d = 0; $d < $length; $d++) {
                $points[] = (int) ($byDay[$this->from->addDays($d)->toDateString()] ?? 0);
            }
            $views[$i]['spark'] = self::squeeze($points, 30);
        }

        return [
            'windows' => ['views' => $window, 'scans' => $scanWindow, 'revenue' => $revenueWindow],
            'currency' => (string) PlatformSettings::get('default_currency'),
            'views' => $views,
            'scans' => $rank("scans_{$scanWindow}"),
            'growth' => $rank('CAST(views_30d AS SIGNED) - CAST(views_prev_30d AS SIGNED)'),
            'revenue' => $rank("revenue_{$revenueWindow}_minor"),
        ];
    }

    /**
     * Restaurants per plan now, and plan changes per week in the range.
     *
     * @return array<string, mixed>
     */
    public function mix(): array
    {
        $names = Plan::query()->get(['id', 'name', 'price_minor'])->keyBy('id');
        $now = $this->health()->groupBy('plan_id')->selectRaw('plan_id, COUNT(*) AS n')->pluck('n', 'plan_id');
        $per = [];
        foreach ($now as $plan => $n) {
            $p = $names->get((int) $plan);
            $per[] = ['id' => (int) $plan, 'name' => $p ? (object) $p->name : null, 'paid' => (int) ($p->price_minor ?? 0) > 0, 'count' => (int) $n];
        }
        usort($per, fn ($a, $b) => $b['count'] <=> $a['count'] ?: $a['id'] <=> $b['id']);
        $weeks = [];
        foreach ($this->days($this->from, $this->to)->orderBy('date')->get(['date', 'upgrades', 'downgrades', 'cancellations']) as $r) {
            $week = CarbonImmutable::parse($r->date)->startOfWeek()->toDateString();
            $weeks[$week] ??= ['week' => $week, 'upgrades' => 0, 'downgrades' => 0, 'cancellations' => 0];
            foreach (['upgrades', 'downgrades', 'cancellations'] as $k) {
                $weeks[$week][$k] += (int) $r->{$k};
            }
        }

        return ['plans' => $per, 'changes' => array_values($weeks)];
    }

    /**
     * Menu styles, menu languages and places (from each restaurant's time zone), now.
     *
     * @return array<string, mixed>
     */
    public function reach(): array
    {
        $group = fn (string $column, int $limit) => $this->health()->whereNotNull($column)->groupBy($column)
            ->selectRaw("{$column} AS k, COUNT(*) AS n")->orderByDesc('n')->orderBy($column)->limit($limit)->get()
            ->map(fn ($r) => ['key' => (string) $r->k, 'count' => (int) $r->n])->all();

        return [
            'total' => $this->health()->count(),
            'styles' => $group('style', 30),
            'languages' => $group('locale', 10),
            'places' => $group('place', 10),
        ];
    }

    /**
     * Restaurants that signed up in the range, and how far they got: added a dish, chose a
     * menu style (saved Appearance), published (a first guest view), and 100 guest views.
     *
     * @return array<string, mixed>
     */
    public function activation(): array
    {
        $f = (array) $this->health()->whereBetween('created_on', [$this->from->toDateString(), $this->to->toDateString()])
            ->selectRaw('COUNT(*) AS signed_up, COALESCE(SUM(dishes > 0),0) AS dishes, COALESCE(SUM(dishes > 0 AND chose_style),0) AS style,
                COALESCE(SUM(dishes > 0 AND chose_style AND last_view_on IS NOT NULL),0) AS published,
                COALESCE(SUM(dishes > 0 AND chose_style AND views_total >= 100),0) AS views_100')->first();
        $first = max(1, (int) $f['signed_up']);

        return ['steps' => array_map(fn (string $k) => ['key' => $k, 'count' => (int) $f[$k], 'percent' => (int) round((int) $f[$k] * 100 / $first)],
            ['signed_up', 'dishes', 'style', 'published', 'views_100'])];
    }

    /** @return array<string, mixed> */
    public function admins(): array
    {
        $row = DB::table('platform_snapshot')->where('key', $this->demo ? 'admins_demo' : 'admins')->value('value');
        $all = $row ? (array) json_decode((string) $row, true) : [];
        $v = $all[(string) $this->plan] ?? $all[$this->plan] ?? null;
        $v = (is_array($v) ? $v : []) + ['members' => 0, 'owners' => 0, 'managers' => 0, 'staff' => 0, 'with_2fa' => 0, 'owners_2fa' => 0,
            'active_7d' => 0, 'active_30d' => 0, 'signed_in_7d' => 0, 'signed_in_30d' => 0];
        $pct = fn (int $a, int $b) => $b > 0 ? (int) round($a * 100 / $b) : null;

        return $v + [
            'percent_2fa' => $pct((int) $v['with_2fa'], (int) $v['members']),
            'owners_percent_2fa' => $pct((int) $v['owners_2fa'], (int) $v['owners']),
            'logins_recorded' => true,
        ];
    }

    /** @return list<array{id: int, name: mixed}> */
    public static function plans(): array
    {
        return Plan::query()->orderBy('sort')->orderBy('price_minor')->get(['id', 'name'])
            ->map(fn (Plan $p) => ['id' => $p->id, 'name' => (object) $p->name])->all();
    }

    /**
     * At most $max points (sums of equal runs of days), for a small line.
     *
     * @param  list<int>  $points
     * @return list<int>
     */
    private static function squeeze(array $points, int $max): array
    {
        $size = (int) ceil(count($points) / $max);
        if ($size <= 1) {
            return $points;
        }

        return array_map('array_sum', array_chunk($points, $size));
    }

    /**
     * Long ranges are drawn per week so a chart never has more than ~100 points.
     *
     * @param  list<array<string, int|string>>  $series
     * @return list<array<string, int|string>>
     */
    private function bucket(array $series): array
    {
        if (count($series) <= 100) {
            return $series;
        }
        $levels = ['restaurants', 'paying', 'mrr_minor', 'active'];
        $out = [];
        foreach ($series as $day) {
            $week = CarbonImmutable::parse((string) $day['date'])->startOfWeek()->toDateString();
            if (! isset($out[$week])) {
                $out[$week] = ['date' => $week] + array_fill_keys(array_keys(array_diff_key($day, ['date' => 1])), 0);
            }
            foreach ($day as $k => $n) {
                if ($k === 'date') {
                    continue;
                }
                // Levels show the last day of the week; flows are added up.
                $out[$week][$k] = in_array($k, $levels, true) ? (int) $n : (int) $out[$week][$k] + (int) $n;
            }
        }

        return array_values($out);
    }
}

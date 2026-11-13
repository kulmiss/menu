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
    ) {}

    /** @param  callable(): array<string, mixed>  $build
     * @return array<string, mixed> */
    public function cached(string $widget, callable $build): array
    {
        $key = implode(':', ['platform:stats', (string) Cache::get(self::VERSION_KEY, '0'), $widget, $this->from->toDateString(), $this->to->toDateString(), $this->plan]);

        return Cache::remember($key, self::TTL, fn () => [...$build(), 'computed_at' => Cache::get(self::VERSION_KEY)]);
    }

    private function days(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('platform_daily')->where('plan_id', $this->plan)->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    private function health(): Builder
    {
        return DB::table('tenant_health')->when($this->plan > 0, fn ($q) => $q->where('plan_id', $this->plan));
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
        $sums = fn (CarbonImmutable $a, CarbonImmutable $b) => (array) $this->days($a, $b)->selectRaw(
            'COALESCE(SUM(new_restaurants),0) AS new_restaurants, COALESCE(SUM(revenue_minor),0) AS revenue_minor,
             COALESCE(SUM(menu_views),0) AS menu_views, COALESCE(SUM(qr_scans),0) AS qr_scans, COALESCE(SUM(edits),0) AS edits,
             COALESCE(ROUND(AVG(active)),0) AS active')->first();
        $at = fn (CarbonImmutable $day) => (array) (DB::table('platform_daily')->where('plan_id', $this->plan)->where('date', '<=', $day->toDateString())
            ->orderByDesc('date')->first(['restaurants', 'paying', 'mrr_minor']) ?? ['restaurants' => 0, 'paying' => 0, 'mrr_minor' => 0]);
        [$pa, $pb] = $this->previous();
        $now = $sums($this->from, $this->to) + $at($this->to);
        $before = $sums($pa, $pb) + $at($pb);
        $kpi = fn (string $key) => ['value' => (int) $now[$key], 'previous' => (int) $before[$key]];

        $series = $this->days($this->from, $this->to)->orderBy('date')
            ->get(['date', 'new_restaurants', 'menu_views', 'qr_scans', 'revenue_minor', 'active', 'paying', 'restaurants', 'mrr_minor'])
            ->map(fn ($r) => ['date' => (string) $r->date] + array_map('intval', array_diff_key((array) $r, ['date' => 1])))->all();

        return [
            'currency' => (string) PlatformSettings::get('default_currency'),
            'kpis' => [
                'restaurants' => $kpi('restaurants'),
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
        $payingAtStart = (int) (DB::table('platform_daily')->where('plan_id', $this->plan)->where('date', '<=', $a)->orderByDesc('date')->value('paying') ?? 0);
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
            COALESCE(SUM(views_7d > 0 OR edits_7d > 0),0) AS active_7d, COALESCE(SUM(views_30d > 0 OR edits_30d > 0),0) AS active_30d,
            COALESCE(ROUND(AVG(setup_pct)),0) AS setup, COALESCE(SUM(setup_pct = 100),0) AS setup_done, MAX(last_edit_at) AS last_edit,
            COALESCE(SUM(band = 'healthy'),0) AS healthy, COALESCE(SUM(band = 'at_risk'),0) AS at_risk, COALESCE(SUM(band = 'inactive'),0) AS inactive")->first();
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
            COALESCE(SUM(photos_limit IS NOT NULL AND photos >= photos_limit),0) AS limit_photos,
            COALESCE(SUM(branches_limit IS NOT NULL AND branches >= branches_limit),0) AS limit_branches,
            COALESCE(SUM(photos_limit IS NOT NULL AND photos >= photos_limit * 0.8 AND photos < photos_limit),0) AS near_photos')->first();
        $styles = $this->health()->groupBy('style')->selectRaw("COALESCE(style, 'editorial') AS style, COUNT(*) AS n")->orderByDesc('n')->pluck('n', 'style')
            ->map(fn ($n) => (int) $n)->all();

        return [
            'total' => (int) $u['total'],
            'features' => array_map(fn (string $k) => ['key' => $k, 'count' => (int) $u[$k]], ['price_periods', 'promotions', 'order_list', 'print']),
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
            ->selectRaw("tenant_id AS id, tenants.name, tenants.slug, {$expr} AS total")->havingRaw('total > 0')->orderByDesc('total')->orderBy('tenant_id')->limit(5)
            ->get()->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'slug' => $r->slug, 'total' => (int) $r->total])->all();

        return [
            'windows' => ['views' => $window, 'scans' => $scanWindow, 'revenue' => $revenueWindow],
            'currency' => (string) PlatformSettings::get('default_currency'),
            'views' => $rank("views_{$window}"),
            'scans' => $rank("scans_{$scanWindow}"),
            'growth' => $rank('CAST(views_30d AS SIGNED) - CAST(views_prev_30d AS SIGNED)'),
            'revenue' => $rank("revenue_{$revenueWindow}_minor"),
        ];
    }

    /** @return array<string, mixed> */
    public function admins(): array
    {
        $row = DB::table('platform_snapshot')->where('key', 'admins')->value('value');
        $all = $row ? (array) json_decode((string) $row, true) : [];
        $v = $all[(string) $this->plan] ?? $all[$this->plan] ?? null;
        $v = is_array($v) ? $v : ['members' => 0, 'owners' => 0, 'managers' => 0, 'staff' => 0, 'with_2fa' => 0, 'owners_2fa' => 0, 'active_7d' => 0, 'active_30d' => 0];
        $pct = fn (int $a, int $b) => $b > 0 ? (int) round($a * 100 / $b) : null;

        return $v + [
            'percent_2fa' => $pct((int) $v['with_2fa'], (int) $v['members']),
            'owners_percent_2fa' => $pct((int) $v['owners_2fa'], (int) $v['owners']),
            'logins_recorded' => false,
        ];
    }

    /** @return list<array{id: int, name: mixed}> */
    public static function plans(): array
    {
        return Plan::query()->orderBy('sort')->orderBy('price_minor')->get(['id', 'name'])
            ->map(fn (Plan $p) => ['id' => $p->id, 'name' => (object) $p->name])->all();
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

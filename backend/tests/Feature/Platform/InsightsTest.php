<?php

use App\Enums\Role;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\PlatformStats\Aggregator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => Cache::flush());

function counter(Tenant $tenant, string $metric, int $daysAgo, int $count): void
{
    DB::table('stats_daily')->insert(['tenant_id' => $tenant->id, 'date' => now()->subDays($daysAgo)->toDateString(), 'metric' => $metric, 'count' => $count]);
}

function edit(Tenant $tenant, int $userId, int $daysAgo, string $action = 'item.updated', ?int $impersonator = null): void
{
    DB::table('activity_log')->insert(['tenant_id' => $tenant->id, 'actor_id' => $userId, 'impersonator_id' => $impersonator, 'action' => $action,
        'created_at' => now()->subDays($daysAgo)]);
}

function paid(Tenant $tenant, int $amount, int $daysAgo, int $months = 1): void
{
    $start = now()->subDays($daysAgo)->startOfDay();
    DB::table('payments')->insert(['tenant_id' => $tenant->id, 'amount_minor' => $amount, 'currency' => 'ETB', 'method' => 'cash',
        'paid_at' => $start, 'period_start' => $start, 'period_end' => $start->copy()->addMonthsNoOverflow($months), 'created_at' => now(), 'updated_at' => now()]);
}

/** Two plans, three restaurants with known activity. */
function insightsWorld(): array
{
    $free = Plan::create(['name' => ['en' => 'Free test'], 'slug' => 'free-test', 'price_minor' => 0, 'interval' => 'month', 'limits' => ['photos' => 2]]);
    $pro = Plan::create(['name' => ['en' => 'Pro test'], 'slug' => 'pro-test', 'price_minor' => 49_000, 'interval' => 'month', 'limits' => []]);
    [$owner, $a] = member(Role::Owner);
    $a->forceFill(['plan_id' => $pro->id, 'trial_ends_at' => null, 'paid_until' => now()->addDays(20), 'created_at' => now()->subDays(100)])->save();
    $b = Tenant::factory()->create(['plan_id' => $free->id, 'trial_ends_at' => null, 'created_at' => now()->subDays(10)]);
    $c = Tenant::factory()->create(['plan_id' => $free->id, 'trial_ends_at' => null, 'created_at' => now()->subDays(3)]);

    counter($a, 'menu_view', 0, 300);
    counter($a, 'menu_view', 5, 50);
    counter($a, 'menu_view', 40, 30); // previous 30 days
    counter($a, 'qr_scan', 1, 12);
    counter($b, 'menu_view', 2, 7);
    counter($b, 'order_add', 2, 1);
    edit($a, $owner->id, 1);
    edit($a, $owner->id, 2);
    edit($a, $owner->id, 20);
    edit($a, $owner->id, 1, 'platform.restaurant_updated'); // Super Admin action: not an edit
    edit($a, $owner->id, 1, 'item.updated', superAdmin()->id); // while impersonating: not an edit
    paid($a, 49_000, 10);
    paid($a, 49_000, 45);
    DB::table('media')->insert(array_fill(0, 2, ['tenant_id' => $b->id, 'disk' => 'public', 'base_path' => 'x', 'mime' => 'image/webp', 'width' => 1, 'height' => 1,
        'bytes' => 1, 'variants' => '[]', 'created_at' => now(), 'updated_at' => now()]));

    app(Aggregator::class)->run(400);

    return compact('free', 'pro', 'a', 'b', 'c', 'owner');
}

it('builds summary numbers that match the raw data', function () {
    ['a' => $a, 'b' => $b, 'c' => $c, 'pro' => $pro] = insightsWorld();

    $h = DB::table('tenant_health')->where('tenant_id', $a->id)->first();
    expect($h->views_7d)->toBe(350)->and($h->views_30d)->toBe(350)->and($h->views_prev_30d)->toBe(30)
        ->and($h->scans_30d)->toBe(12)->and($h->edits_7d)->toBe(2)->and($h->edits_30d)->toBe(3)
        ->and($h->revenue_30d_minor)->toBe(49_000)->and($h->revenue_90d_minor)->toBe(98_000)
        ->and($h->band)->toBe('healthy');
    expect(DB::table('tenant_health')->where('tenant_id', $c->id)->value('band'))->toBe('inactive');
    expect((bool) DB::table('tenant_health')->where('tenant_id', $b->id)->value('uses_order_list'))->toBeTrue();

    // Raw vs summary for the whole platform, last 30 days.
    $since = now()->subDays(29)->toDateString();
    $raw = fn (string $metric) => (int) DB::table('stats_daily')->where('metric', $metric)->where('date', '>=', $since)->sum('count');
    $o = $this->actingAs(superAdmin())->getJson(p('insights/overview?days=30'))->assertOk()->json('data.kpis');
    expect($o['menu_views']['value'])->toBe($raw('menu_view'))
        ->and($o['menu_views']['previous'])->toBe(30)
        ->and($o['qr_scans']['value'])->toBe(12)
        ->and($o['restaurants']['value'])->toBe(Tenant::count())
        ->and($o['new_restaurants']['value'])->toBe(Tenant::where('created_at', '>=', $since)->count())
        ->and($o['paying']['value'])->toBe(1)
        ->and($o['free']['value'])->toBe(2)
        ->and($o['mrr_minor']['value'])->toBe(49_000)
        ->and($o['revenue_minor']['value'])->toBe((int) DB::table('payments')->where('paid_at', '>=', $since)->sum('amount_minor'))
        ->and($o['revenue_minor']['previous'])->toBe(49_000);

    // Plan filter: only Pro's restaurant.
    $pp = $this->getJson(p("insights/overview?days=30&plan={$pro->id}"))->assertOk()->json('data.kpis');
    expect($pp['restaurants']['value'])->toBe(1)->and($pp['menu_views']['value'])->toBe(350);
});

it('answers growth, engagement, usage, top and team widgets', function () {
    ['a' => $a, 'b' => $b, 'free' => $free] = insightsWorld();
    $this->actingAs(superAdmin());

    $g = $this->getJson(p('insights/growth?days=30'))->assertOk()->json('data');
    expect(collect($g['funnel'])->firstWhere('key', 'signed_up')['count'])->toBe(2)
        ->and(collect($g['funnel'])->firstWhere('key', 'views')['count'])->toBe(1)
        ->and(array_sum(array_column($g['weeks'], 'count')))->toBe(2)
        ->and($g['cohorts'])->not->toBeEmpty();

    $e = $this->getJson(p('insights/engagement?days=30'))->assertOk()->json('data');
    expect($e['edited_7d'])->toBe(1)->and($e['bands']['healthy'])->toBe(1)
        ->and($e['lists']['healthy'][0]['id'])->toBe($a->id);

    $u = $this->getJson(p('insights/usage'))->assertOk()->json('data');
    expect(collect($u['features'])->firstWhere('key', 'order_list')['count'])->toBe(1)
        ->and(collect($u['limits'])->firstWhere('key', 'limit_photos')['count'])->toBe(1);

    $top = $this->getJson(p('insights/top?days=30'))->assertOk()->json('data');
    expect($top['views'][0]['id'])->toBe($a->id)->and($top['views'][0]['total'])->toBe(350)
        ->and($top['revenue'][0]['total'])->toBe(49_000)
        ->and($top['growth'][0]['total'])->toBe(320);

    $t = $this->getJson(p('insights/admins'))->assertOk()->json('data');
    expect($t['members'])->toBe(1)->and($t['active_7d'])->toBe(1)->and($t['logins_recorded'])->toBeFalse();
    $this->getJson(p("insights/admins?plan={$free->id}"))->assertOk()->assertJsonPath('data.active_7d', 0);

    $this->getJson(p('insights/overview?from=2026-01-01&to=2026-01-31'))->assertOk()->assertJsonPath('data.from', '2026-01-01');
    $this->getJson(p('insights/overview?days=12'))->assertUnprocessable();
    $this->getJson(p('insights/nope'))->assertNotFound();
    $this->getJson(p('insights'))->assertOk()->assertJsonPath('data.computed_at', fn ($v) => $v !== null);
});

it('filters the restaurants table by health band and usage for click-throughs', function () {
    ['a' => $a, 'b' => $b, 'c' => $c] = insightsWorld();
    $this->actingAs(superAdmin());
    $ids = fn (string $q) => collect($this->getJson(p("restaurants?page=1&{$q}"))->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('health=healthy'))->toBe([$a->id])
        ->and($ids('health=inactive'))->toContain($c->id)
        ->and($ids('usage=order_list'))->toBe([$b->id])
        ->and($ids('usage=limit_photos'))->toBe([$b->id])
        ->and($ids('usage=paying'))->toBe([$a->id])
        ->and($ids('usage=not_paying&sort=-views'))->toBe([$b->id, $c->id]);
    $row = collect($this->getJson(p('restaurants?page=1&sort=-health'))->json('data'))->first();
    expect($row['id'])->toBe($a->id)->and($row['health']['band'])->toBe('healthy')->and($row['views_30d'])->toBe(350);
});

it('is for Super Admins only', function () {
    [$owner] = member(Role::Owner);
    $this->actingAs($owner)->getJson(p('insights/overview'))->assertNotFound();
    $this->actingAs($owner)->getJson(p('restaurants?page=1&health=healthy'))->assertNotFound();
    $this->actingAs(superAdmin(twoFactor: false))->getJson(p('insights/overview'))->assertForbidden();
});

it('caches each widget until the next aggregate run', function () {
    config(['cache.default' => 'file']); // a real, serializing store
    Cache::flush();
    insightsWorld();
    $this->actingAs(superAdmin());
    $before = $this->getJson(p('insights/overview?days=7'))->json('data.kpis.menu_views.value');
    DB::table('platform_daily')->update(['menu_views' => 0]);
    expect($this->getJson(p('insights/overview?days=7'))->json('data.kpis.menu_views.value'))->toBe($before);
    $this->artisan('platform:aggregate', ['--days' => 7])->assertSuccessful();
    expect($this->getJson(p('insights/overview?days=7'))->json('data.kpis.menu_views.value'))->toBe($before);
});

it('stays fast with 1,500 restaurants and a year of data', function () {
    $plans = collect([0, 49_000, 99_000])->map(fn ($price, $i) => Plan::create(['name' => ['en' => "P{$i}"], 'slug' => "perf-{$i}", 'price_minor' => $price, 'interval' => 'month', 'limits' => ['photos' => 100]]));
    $now = now();
    foreach (array_chunk(range(1, 1500), 500) as $chunk) {
        DB::table('tenants')->insert(array_map(fn ($i) => ['name' => "R{$i}", 'slug' => "perf-r{$i}", 'status' => 'active', 'plan_id' => $plans[$i % 3]->id,
            'timezone' => 'Africa/Addis_Ababa', 'default_locale' => 'en', 'currency_code' => 'ETB', 'cache_version' => 1,
            'created_at' => $now->copy()->subDays($i % 365), 'updated_at' => $now], $chunk));
    }
    // A year of menu views and scans per restaurant (~1.1 million rows), built in SQL.
    foreach (['menu_view' => 40, 'qr_scan' => 9] as $metric => $mod) {
        DB::statement("INSERT INTO stats_daily (tenant_id, date, metric, count)
            WITH RECURSIVE seq (n) AS (SELECT 0 UNION ALL SELECT n + 1 FROM seq WHERE n < 364)
            SELECT t.id, DATE_SUB(CURDATE(), INTERVAL seq.n DAY), '{$metric}', (t.id * 7 + seq.n) % {$mod}
            FROM tenants t CROSS JOIN seq");
    }
    $actor = superAdmin()->id;
    DB::statement("INSERT INTO activity_log (tenant_id, actor_id, action, created_at)
        WITH RECURSIVE seq (n) AS (SELECT 0 UNION ALL SELECT n + 1 FROM seq WHERE n < 51)
        SELECT t.id, {$actor}, 'item.updated', DATE_SUB(NOW(), INTERVAL seq.n * 7 + (t.id % 7) DAY) FROM tenants t CROSS JOIN seq");
    DB::statement("INSERT INTO payments (tenant_id, amount_minor, currency, method, paid_at, period_start, period_end, created_at, updated_at)
        WITH RECURSIVE seq (n) AS (SELECT 0 UNION ALL SELECT n + 1 FROM seq WHERE n < 11)
        SELECT t.id, 49000, 'ETB', 'cash', DATE_SUB(CURDATE(), INTERVAL seq.n MONTH), DATE_SUB(CURDATE(), INTERVAL seq.n MONTH),
            DATE_ADD(DATE_SUB(CURDATE(), INTERVAL seq.n MONTH), INTERVAL 1 MONTH), NOW(), NOW() FROM tenants t CROSS JOIN seq WHERE t.id % 3 = 1");

    $started = microtime(true);
    $steps = app(Aggregator::class)->run(400);
    $built = microtime(true) - $started;
    expect(DB::table('tenant_health')->count())->toBe(1500);

    $this->actingAs(superAdmin());
    $total = 0.0;
    foreach (['overview?days=30', 'overview?days=365', 'growth?days=90', 'engagement?days=30', 'usage', 'top?days=90', 'admins', "overview?days=90&plan={$plans[1]->id}"] as $widget) {
        Cache::flush(); // measure without the five-minute cache
        $t = microtime(true);
        $this->getJson(p("insights/{$widget}"))->assertOk();
        $ms = (microtime(true) - $t) * 1000;
        $total += $ms;
        expect($ms)->toBeLessThan(200, "{$widget} took {$ms} ms");
    }
    expect($total)->toBeLessThan(1000);
    fwrite(STDERR, sprintf("\n  dashboard: aggregate %.1fs (%s), all widgets %.0f ms uncached\n", $built, json_encode($steps), $total));
})->group('slow');

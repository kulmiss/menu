<?php

use App\Enums\Role;
use App\Models\Media;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Models\User;
use App\Support\PlatformStats\Aggregator;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * Super Admin dashboard, more numbers (owner, 2026-11-22): every number checked against a
 * small world with known answers, with and without the website demo restaurants.
 */

beforeEach(fn () => Cache::flush());

function dashStat(Tenant $tenant, string $metric, int $daysAgo, int $count): void
{
    DB::table('stats_daily')->insert(['tenant_id' => $tenant->id, 'date' => now()->subDays($daysAgo)->toDateString(), 'metric' => $metric, 'count' => $count]);
}

function dashDid(Tenant $tenant, int $userId, string $action, int $daysAgo): void
{
    DB::table('activity_log')->insert(['tenant_id' => $tenant->id, 'actor_id' => $userId, 'action' => $action, 'created_at' => now()->subDays($daysAgo)]);
}

/** A plan move as the Super Admin's restaurant screen logs it (no restaurant on the log row). */
function dashPlanMoved(Tenant $tenant, ?Plan $from, Plan $to, int $daysAgo, string $action = 'platform.restaurant_updated'): void
{
    DB::table('activity_log')->insert(['tenant_id' => null, 'actor_id' => null, 'action' => $action, 'subject_type' => 'Tenant', 'subject_id' => $tenant->id,
        'changes' => json_encode(['plan_id' => ['from' => $from?->id, 'to' => $to->id]]), 'created_at' => now()->subDays($daysAgo)]);
}

/** Four real restaurants (one deleted, one without a plan) and the website café demo. */
function dashboardWorld(): array
{
    $free = Plan::create(['name' => ['en' => 'Free d'], 'slug' => 'free-d', 'price_minor' => 0, 'interval' => 'month', 'limits' => []]);
    $plus = Plan::create(['name' => ['en' => 'Plus d'], 'slug' => 'plus-d', 'price_minor' => 20_000, 'interval' => 'month', 'limits' => []]);
    $pro = Plan::create(['name' => ['en' => 'Pro d'], 'slug' => 'pro-d', 'price_minor' => 49_000, 'interval' => 'month', 'limits' => []]);
    [$owner, $a] = member(Role::Owner);
    $owner->forceFill(['email' => 'owner-a@real.test'])->save();
    $a->forceFill(['plan_id' => $pro->id, 'trial_ends_at' => null, 'paid_until' => now()->addDays(20), 'created_at' => now()->subDays(100),
        'timezone' => 'Africa/Addis_Ababa', 'default_locale' => 'am'])->save();
    $b = Tenant::factory()->create(['plan_id' => $free->id, 'trial_ends_at' => null, 'created_at' => now()->subDays(10), 'timezone' => 'Africa/Nairobi', 'default_locale' => 'en']);
    $gone = Tenant::factory()->create(['plan_id' => $free->id, 'trial_ends_at' => null, 'created_at' => now()->subDays(50)]);
    $gone->delete();
    DB::table('tenants')->where('id', $gone->id)->update(['deleted_at' => now()->subDays(5)]);
    $noPlan = Tenant::factory()->create(['plan_id' => null, 'trial_ends_at' => null, 'created_at' => now()->subDays(3), 'timezone' => 'UTC', 'default_locale' => 'en']);

    // The website café demo: at a demo address, with only an @example.com login.
    $demo = Tenant::factory()->create(['slug' => 'demo', 'plan_id' => $plus->id, 'trial_ends_at' => null, 'created_at' => now()->subDays(2), 'timezone' => 'Africa/Addis_Ababa']);
    $demoUser = User::factory()->create(['email' => 'demo@example.com']);
    Membership::create(['tenant_id' => $demo->id, 'user_id' => $demoUser->id, 'role' => Role::Owner->value]);

    dashStat($a, 'menu_view', 0, 100);
    dashStat($a, 'menu_view', 40, 50); // A's first guest view: 40 days ago
    dashStat($b, 'menu_view', 2, 7); // B's first: 2 days ago
    dashStat($b, 'order_add', 2, 3);
    dashStat($b, 'order_shown', 2, 2);
    dashStat($demo, 'order_shown', 1, 9);
    dashStat($demo, 'menu_view', 1, 1000);
    dashStat($demo, 'qr_scan', 1, 40);

    dashDid($a, $owner->id, 'item.created', 1);
    dashDid($a, $owner->id, 'item.duplicated', 1);
    dashDid($a, $owner->id, 'category.updated', 2);
    dashDid($a, $owner->id, 'appearance.updated', 2); // a change, but not to the menu itself
    dashDid($demo, $demoUser->id, 'item.created', 1);

    dashPlanMoved($b, $free, $plus, 6); // up
    dashPlanMoved($b, $plus, $free, 4, 'platform.plan_changed'); // cancelled
    dashPlanMoved($a, $plus, $pro, 3, 'platform.plan_moved'); // up
    dashPlanMoved($a, $pro, $plus, 40); // down, before the 30 days
    dashPlanMoved($demo, $free, $plus, 1); // up, demo
    dashPlanMoved($noPlan, $free, $free, 1); // no change

    DB::table('payments')->insert(['tenant_id' => $a->id, 'amount_minor' => 49_000, 'currency' => 'ETB', 'method' => 'cash',
        'paid_at' => now()->subDays(10)->startOfDay(), 'period_start' => now()->subDays(10)->startOfDay(), 'period_end' => now()->subDays(10)->startOfDay()->addMonth(),
        'created_at' => now(), 'updated_at' => now()]);

    // A's dishes: one with a photo and two languages; A shows the PDF menu link.
    asTenant($a, function () {
        [, , $item] = sampleMenu(Tenant::find(app(CurrentTenant::class)->id()));
        $media = Media::create(['base_path' => 'dish/1', 'mime' => 'image/jpeg', 'width' => 8, 'height' => 6, 'bytes' => 1, 'variants' => []]);
        $item->forceFill(['media_id' => $media->id, 'name' => ['en' => 'Macchiato', 'am' => 'ማኪያቶ']])->save();
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => 'coffee', 'show_pdf_link' => true]);
    });
    sampleMenu($b);

    app(Aggregator::class)->run(400);

    return compact('free', 'plus', 'pro', 'a', 'b', 'gone', 'noPlan', 'demo', 'owner');
}

function dashWidget(string $name, string $query = 'days=30'): array
{
    return test()->actingAs(superAdmin())->getJson(p("insights/{$name}?{$query}"))->assertOk()->json('data');
}

it('counts restaurants, plan changes, money, guests and team changes without the demo', function () {
    dashboardWorld();
    $k = dashWidget('overview')['kpis'];

    // A, B and the one without a plan (counted once, not twice as before); the deleted one is gone.
    expect($k['restaurants']['value'])->toBe(3)
        ->and($k['new_restaurants']['value'])->toBe(2) // B and the one without a plan
        ->and($k['deleted_restaurants']['value'])->toBe(1)
        ->and($k['activated']['value'])->toBe(1) // B; A's first view was 40 days ago
        ->and($k['paused']['value'])->toBe(0)
        ->and($k['upgrades']['value'])->toBe(2)
        ->and($k['cancellations']['value'])->toBe(1)
        ->and($k['downgrades']['value'])->toBe(0)
        ->and($k['downgrades']['previous'])->toBe(1)
        ->and($k['menu_views']['value'])->toBe(107)
        ->and($k['qr_scans']['value'])->toBe(0)
        ->and($k['order_adds']['value'])->toBe(3)
        ->and($k['order_lists']['value'])->toBe(2)
        ->and($k['dishes_added']['value'])->toBe(2)
        ->and($k['menu_changes']['value'])->toBe(3)
        ->and($k['edits']['value'] ?? null)->toBeNull()
        ->and($k['revenue_minor']['value'])->toBe(49_000)
        // One paying restaurant on the days it paid: 49,000 per paying restaurant.
        ->and($k['arppu_minor']['value'])->toBe(49_000)
        ->and(dashWidget('overview')['has_payments'])->toBeTrue()
        ->and(dashWidget('overview')['demo'])->toBeFalse();
});

it('adds the demo restaurants only with "Include demo data", and says so', function () {
    dashboardWorld();
    $o = dashWidget('overview', 'days=30&demo=1');
    expect($o['demo'])->toBeTrue()
        ->and($o['kpis']['restaurants']['value'])->toBe(4)
        ->and($o['kpis']['menu_views']['value'])->toBe(1107)
        ->and($o['kpis']['qr_scans']['value'])->toBe(40)
        ->and($o['kpis']['dishes_added']['value'])->toBe(3)
        ->and($o['kpis']['upgrades']['value'])->toBe(3)
        ->and($o['kpis']['activated']['value'])->toBe(2)
        ->and($o['kpis']['order_lists']['value'])->toBe(11);
    // The day rows add up the same way.
    expect(array_sum(array_column($o['series'], 'menu_views')))->toBe(1107)
        ->and(array_sum(array_column(dashWidget('overview')['series'], 'menu_views')))->toBe(107);

    expect(collect(dashWidget('top', 'days=30&demo=1')['views'])->pluck('name')->first())->toBe(Tenant::where('slug', 'demo')->value('name'))
        ->and(collect(dashWidget('top')['views'])->pluck('slug'))->not->toContain('demo');
});

it('shows "No payments yet" instead of zeros when nobody has paid', function () {
    dashboardWorld();
    DB::table('payments')->delete();
    app(Aggregator::class)->run(400);
    Cache::flush();
    expect(dashWidget('overview')['has_payments'])->toBeFalse();
});

it('lists restaurants per plan and the plan changes per week', function () {
    ['free' => $free, 'plus' => $plus, 'pro' => $pro] = dashboardWorld();
    $m = dashWidget('mix');
    $counts = collect($m['plans'])->mapWithKeys(fn ($p) => [$p['id'] => $p['count']])->all();
    expect($counts)->toBe([0 => 1, $free->id => 1, $pro->id => 1])
        ->and(collect($m['plans'])->firstWhere('id', $pro->id)['paid'])->toBeTrue()
        ->and(array_sum(array_column($m['changes'], 'upgrades')))->toBe(2)
        ->and(array_sum(array_column($m['changes'], 'cancellations')))->toBe(1)
        ->and(collect(dashWidget('mix', 'days=30&demo=1')['plans'])->firstWhere('id', $plus->id)['count'])->toBe(1);
});

it('counts styles, menu languages and places, and the activation steps', function () {
    dashboardWorld();
    $r = dashWidget('reach');
    expect($r['total'])->toBe(3)
        ->and($r['styles'])->toBe([['key' => 'coffee', 'count' => 1]])
        ->and($r['languages'])->toBe([['key' => 'en', 'count' => 2], ['key' => 'am', 'count' => 1]])
        // From the time zone; UTC has no city.
        ->and($r['places'])->toBe([['key' => 'Addis Ababa', 'count' => 1], ['key' => 'Nairobi', 'count' => 1]]);

    // Signed up in the range: B and the one without a plan. B added dishes but never saved a style.
    $steps = collect(dashWidget('activation')['steps'])->pluck('count', 'key')->all();
    expect($steps)->toBe(['signed_up' => 2, 'dishes' => 1, 'style' => 0, 'published' => 0, 'views_100' => 0]);
    // Over 365 days A is in too: dishes, a style, published and more than 100 views.
    $year = collect(dashWidget('activation', 'days=365')['steps'])->pluck('count', 'key')->all();
    expect($year)->toBe(['signed_up' => 3, 'dishes' => 2, 'style' => 1, 'published' => 1, 'views_100' => 1]);
});

it('shows feature use and the top restaurants with a small line', function () {
    ['a' => $a] = dashboardWorld();
    $features = collect(dashWidget('usage')['features'])->pluck('count', 'key')->all();
    expect($features)->toMatchArray(['photos' => 1, 'pdf' => 1, 'languages' => 1, 'order_list' => 1]);

    $top = dashWidget('top', 'days=7')['views'];
    expect($top[0]['id'])->toBe($a->id)
        ->and($top[0]['spark'])->toHaveCount(7)
        ->and(array_sum($top[0]['spark']))->toBe(100)
        ->and(end($top[0]['spark']))->toBe(100);
    // A long range is squeezed to at most 30 points, adding up to the same total.
    $long = dashWidget('top', 'days=90')['views'][0]['spark'];
    expect(count($long))->toBeLessThanOrEqual(30)->and(array_sum($long))->toBe(150);
});

it('logs a plan change made with a payment, for the upgrade count', function () {
    ['b' => $b, 'pro' => $pro] = dashboardWorld();
    $this->actingAs(superAdmin())->postJson(p("restaurants/{$b->id}/payments"), ['amount_minor' => 49_000, 'currency' => 'ETB', 'method' => 'cash',
        'paid_at' => now()->toDateString(), 'months' => 1, 'plan_id' => $pro->id], idem())->assertCreated();
    // Compared decoded and in any key order: MySQL stores JSON in its own order and spacing.
    expect(json_decode((string) DB::table('activity_log')->where('action', 'platform.plan_changed')->where('subject_id', $b->id)->latest('id')->value('changes'), true))
        ->toEqual(['plan_id' => ['from' => $b->plan_id, 'to' => $pro->id]]);
});

it('keeps the new widgets for Super Admins only', function () {
    [$owner] = member(Role::Owner);
    foreach (['mix', 'reach', 'activation'] as $w) {
        $this->actingAs($owner)->getJson(p("insights/{$w}"))->assertNotFound();
    }
    $this->actingAs(superAdmin())->getJson(p('insights/overview?demo=maybe'))->assertInvalid('demo');
});

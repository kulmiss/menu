<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Stats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Cache::flush());

function payment(Tenant $tenant, int $amount, string $paidAt): void
{
    DB::table('payments')->insert(['tenant_id' => $tenant->id, 'amount_minor' => $amount, 'currency' => 'ETB', 'method' => 'cash',
        'paid_at' => $paidAt, 'period_start' => $paidAt, 'period_end' => $paidAt, 'created_at' => now(), 'updated_at' => now()]);
}

it('shows revenue this month vs last month, MRR and 12 months', function () {
    $monthly = Plan::create(['name' => ['en' => 'Pro'], 'slug' => 'pro', 'price_minor' => 50000, 'interval' => 'month', 'limits' => []]);
    $yearly = Plan::create(['name' => ['en' => 'Pro year'], 'slug' => 'pro-year', 'price_minor' => 1200000, 'interval' => 'year', 'limits' => []]);
    $a = Tenant::factory()->create(['plan_id' => $monthly->id, 'paid_until' => now()->addMonth()]);
    Tenant::factory()->create(['plan_id' => $yearly->id, 'paid_until' => now()->addYear()]);
    Tenant::factory()->create(['plan_id' => $monthly->id, 'paid_until' => now()->subDay()]); // lapsed: not in MRR
    payment($a, 50000, now()->startOfMonth()->addDay()->toDateTimeString());
    payment($a, 30000, now()->subMonthNoOverflow()->startOfMonth()->addDay()->toDateTimeString());

    $this->actingAs(superAdmin())->getJson(p('widgets/revenue'))->assertOk()
        ->assertJsonPath('data.this_month', 50000)
        ->assertJsonPath('data.last_month', 30000)
        ->assertJsonPath('data.mrr', 150000)
        ->assertJsonCount(12, 'data.months');
});

it('lists trials and subscriptions ending soon and e-mails the owner, logged', function () {
    Mail::fake();
    [, $tenant] = member(Role::Owner);
    $tenant->forceFill(['trial_ends_at' => now()->addDays(5), 'paid_until' => null])->save();
    Tenant::factory()->create(['trial_ends_at' => now()->addDays(40)]);
    $admin = superAdmin();

    $this->actingAs($admin)->getJson(p('widgets/expiring'))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'trial')->assertJsonPath('data.0.days_left', 5);

    $this->actingAs($admin)->postJson(p("widgets/email/{$tenant->id}"), [
        'subject' => 'Your trial ends soon', 'message' => 'Hi! Your trial ends on Monday.', 'reason' => 'expiring',
    ], idem())->assertOk()->assertJsonPath('message', 'E-mail sent to the owner.');
    expect(ActivityLog::where('action', 'platform.owner_emailed')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('finds restaurants with no views and no edits for 14 days', function () {
    $quiet = Tenant::factory()->create(['created_at' => now()->subDays(60)]);
    $busy = Tenant::factory()->create(['created_at' => now()->subDays(60)]);
    Tenant::factory()->create(['created_at' => now()->subDays(3)]); // too new
    Stats::hit($busy->id, now()->toDateString(), 0, 'menu_view');
    Stats::hit($quiet->id, now()->subDays(20)->toDateString(), 0, 'menu_view');

    $this->actingAs(superAdmin())->getJson(p('widgets/inactive'))->assertOk()
        ->assertJsonPath('data.0.id', $quiet->id)->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.last_activity', now()->subDays(20)->toDateString());
});

it('shows the growth funnel with conversion between steps', function () {
    foreach (range(1, 4) as $n) {
        $t = Tenant::factory()->create(['created_at' => now()->subDays(5)]);
        if ($n <= 2) {
            [, $category] = sampleMenu($t);
            asTenant($t, fn () => collect(range(1, 4))->each(fn ($i) => Item::create(['category_id' => $category->id, 'name' => ['en' => 'D'.$i], 'price_minor' => 1])));
        }
        if ($n === 1) {
            Stats::hit($t->id, now()->toDateString(), 0, 'qr_scan', 1);
            payment($t, 100, now()->toDateTimeString());
        }
    }

    $this->actingAs(superAdmin())->getJson(p('widgets/funnel?days=30'))->assertOk()
        ->assertJsonPath('data.steps', [
            ['key' => 'signups', 'count' => 4, 'percent' => null],
            ['key' => 'menu', 'count' => 2, 'percent' => 50],
            ['key' => 'scanned', 'count' => 1, 'percent' => 50],
            ['key' => 'paying', 'count' => 1, 'percent' => 100],
        ]);
});

it('shows top restaurants, new sign-ups with setup progress, plan mix and health', function () {
    $big = Plan::create(['name' => ['en' => 'Big'], 'slug' => 'big', 'price_minor' => 90000, 'interval' => 'month', 'limits' => []]);
    $small = Plan::create(['name' => ['en' => 'Small'], 'slug' => 'small', 'price_minor' => 10000, 'interval' => 'month', 'limits' => []]);
    $star = Tenant::factory()->create(['name' => 'Star', 'plan_id' => $big->id, 'paid_until' => now()->addMonth()]);
    Stats::hit($star->id, now()->toDateString(), 0, 'menu_view', 0, 500);
    ActivityLog::create(['tenant_id' => $star->id, 'action' => 'platform.restaurant_updated', 'changes' => ['plan_id' => ['from' => $small->id, 'to' => $big->id]]]);
    Cache::forever('cron:last_run', now()->subMinutes(30)->toIso8601String());
    $admin = superAdmin();

    $this->actingAs($admin)->getJson(p('widgets/top'))->assertOk()
        ->assertJsonPath('data.views.0', ['id' => $star->id, 'name' => 'Star', 'total' => 500])
        ->assertJsonPath('data.plans.0.plan', ['en' => 'Big']);
    $this->actingAs($admin)->getJson(p('widgets/signups'))->assertOk()
        ->assertJsonPath('data.0.id', $star->id)->assertJsonPath('data.0.percent', 0)
        ->assertJsonPath('data.0.steps.logo', false);
    $this->actingAs($admin)->getJson(p('widgets/plan-mix'))->assertOk()
        ->assertJsonPath('data.upgrades', 1)->assertJsonPath('data.downgrades', 0);
    $this->actingAs($admin)->getJson(p('widgets/health'))->assertOk()
        ->assertJsonPath('data.cron_minutes_ago', 30)
        ->assertJsonPath('data.php', PHP_VERSION)
        ->assertJson(fn ($json) => $json->where('data.warnings', fn ($w) => collect($w)->contains('cron'))->etc());
});

it('is only for Super Admins with two-factor', function () {
    [$owner] = member(Role::Owner);
    $this->actingAs($owner)->getJson(p('widgets/revenue'))->assertNotFound();
    $this->actingAs(superAdmin(twoFactor: false))->getJson(p('widgets/revenue'))->assertStatus(403);
});

it('stays fast with 200 restaurants and 6 months of statistics', function () {
    $plan = Plan::create(['name' => ['en' => 'Pro'], 'slug' => 'pro', 'price_minor' => 50000, 'interval' => 'month', 'limits' => []]);
    $now = now();
    $tenants = [];
    foreach (range(1, 200) as $n) {
        $tenants[] = ['slug' => 'r'.$n, 'name' => 'Restaurant '.$n, 'plan_id' => $n % 3 ? $plan->id : null, 'paid_until' => $n % 3 ? $now->copy()->addDays($n % 40) : null,
            'trial_ends_at' => $now->copy()->addDays($n % 20), 'timezone' => 'Africa/Addis_Ababa', 'default_locale' => 'en', 'currency_code' => 'ETB',
            'created_at' => $now->copy()->subDays($n), 'updated_at' => $now];
    }
    DB::table('tenants')->insert($tenants);
    $ids = DB::table('tenants')->pluck('id')->all();
    $rows = [];
    foreach ($ids as $id) {
        for ($d = 0; $d < 180; $d += 2) {
            $rows[] = ['tenant_id' => $id, 'date' => $now->copy()->subDays($d)->toDateString(), 'branch_id' => 0, 'metric' => 'menu_view', 'subject_id' => 0, 'count' => 10 + $d % 7];
            $rows[] = ['tenant_id' => $id, 'date' => $now->copy()->subDays($d)->toDateString(), 'branch_id' => 0, 'metric' => 'qr_scan', 'subject_id' => 1, 'count' => 3];
        }
        if (count($rows) > 4000) {
            DB::table('stats_daily')->insert($rows);
            $rows = [];
        }
    }
    DB::table('stats_daily')->insert($rows);
    $admin = superAdmin();

    foreach (['revenue', 'expiring', 'inactive', 'funnel?days=90', 'top', 'signups', 'health', 'plan-mix'] as $widget) {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $started = microtime(true);
        $this->actingAs($admin)->getJson(p('widgets/'.$widget))->assertOk();
        $ms = (microtime(true) - $started) * 1000;
        expect(count(DB::getQueryLog()))->toBeLessThan(15, $widget)
            ->and($ms)->toBeLessThan(3000, $widget); // generous for slow CI; locally each answers in tens of ms
        DB::disableQueryLog();
    }
});

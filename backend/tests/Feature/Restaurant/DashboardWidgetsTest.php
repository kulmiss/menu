<?php

use App\Enums\Role;
use App\Models\Item;
use App\Models\Media;
use App\Models\OpeningHour;
use App\Models\Plan;
use App\Models\TenantAppearance;
use App\Models\TenantProfile;
use App\Support\Features;
use App\Support\PlatformSettings;
use App\Support\Stats;
use Illuminate\Support\Facades\DB;

function widgetMedia(): Media
{
    return Media::create(['base_path' => 'w/'.random_int(1, 99999), 'mime' => 'image/jpeg', 'width' => 10, 'height' => 10, 'bytes' => 1,
        'variants' => ['320' => '320.webp', '640' => '640.webp', '1280' => '1280.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg']]);
}

it('shows the setup checklist with links until everything is done, and can be hidden', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, $category] = sampleMenu($tenant);

    $this->actingAs($owner)->getJson(t($tenant, 'widgets/setup'))->assertOk()
        ->assertJsonPath('data.percent', 0)->assertJsonPath('data.complete', false)
        ->assertJsonPath('data.steps.0', ['key' => 'logo', 'done' => false, 'link' => '/admin/appearance'])
        ->assertJsonPath('data.steps.6.key', 'qr');

    asTenant($tenant, function () use ($category) {
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'logo_media_id' => widgetMedia()->id, 'cover_media_id' => widgetMedia()->id]);
        foreach (range(1, 5) as $n) {
            Item::create(['category_id' => $category->id, 'name' => ['en' => 'D'.$n], 'price_minor' => 100, 'media_id' => widgetMedia()->id]);
        }
        OpeningHour::create(['branch_id' => null, 'weekday' => 1, 'opens_at' => '08:00', 'closes_at' => '20:00']);
        TenantProfile::create(['address' => ['en' => 'Bole Road']]);
    });
    $qr = asTenant($tenant, fn () => DB::table('qr_codes')->where('tenant_id', $tenant->id)->value('id'));
    $this->actingAs($owner)->get(t($tenant, "qr-codes/{$qr}.svg"))->assertOk();

    $this->actingAs($owner)->getJson(t($tenant, 'widgets/setup'))->assertJsonPath('data.percent', 100)->assertJsonPath('data.complete', true);
    $this->actingAs($owner)->postJson(t($tenant, 'widgets/setup/hide'), [], idem())->assertOk();
    $this->actingAs($owner)->getJson(t($tenant, 'widgets/setup'))->assertJsonPath('data.hidden', true);
});

it('gives staff the visit trend but never plan, usage or money', function () {
    [$staff, $tenant] = member(Role::Staff);
    $today = now($tenant->timezone)->toDateString();
    $yesterday = now($tenant->timezone)->subDay()->toDateString();
    Stats::hit($tenant->id, $today, 0, 'menu_view', 0, 12);
    Stats::hit($tenant->id, $yesterday, 0, 'menu_view', 0, 8);
    Stats::hit($tenant->id, $today, 0, 'qr_scan', 3, 4);

    $this->actingAs($staff)->getJson(t($tenant, 'widgets/trend'))->assertOk()
        ->assertJsonPath('data.today', ['date' => $today, 'views' => 12, 'scans' => 4])
        ->assertJsonPath('data.yesterday.views', 8)
        ->assertJsonCount(7, 'data.days');
    foreach (['setup', 'plan', 'orders', 'missing-photos'] as $widget) {
        $this->actingAs($staff)->getJson(t($tenant, 'widgets/'.$widget))->assertForbidden();
    }
});

it('lists dishes without photos, most viewed first, only when the menu shows photos', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, $category, $macchiato] = sampleMenu($tenant);
    $popular = asTenant($tenant, function () use ($category) {
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'With photo'], 'price_minor' => 100, 'media_id' => widgetMedia()->id]);

        return Item::create(['category_id' => $category->id, 'name' => ['en' => 'Popular'], 'price_minor' => 100]);
    });
    Stats::hit($tenant->id, now($tenant->timezone)->toDateString(), 0, 'item_view', $popular->id, 30);

    $this->actingAs($owner)->getJson(t($tenant, 'widgets/missing-photos'))->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.items.0.id', $popular->id)->assertJsonPath('data.items.0.views', 30)
        ->assertJsonPath('data.items.1.id', $macchiato->id);

    asTenant($tenant, fn () => TenantAppearance::create([...TenantAppearance::DEFAULTS, 'show_photos' => false]));
    $this->actingAs($owner)->getJson(t($tenant, 'widgets/missing-photos'))->assertJsonPath('data.enabled', false);
});

it('shows the most-added dishes and the average list value', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    $today = now($tenant->timezone)->toDateString();
    Stats::hit($tenant->id, $today, 0, 'order_add', $item->id, 6);
    Stats::hit($tenant->id, $today, 0, 'order_shown', 0, 3);
    Stats::hit($tenant->id, $today, 0, 'order_value', 0, 90000);

    $this->actingAs($owner)->getJson(t($tenant, 'widgets/orders'))->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.periods.7.top.0', ['id' => $item->id, 'name' => ['en' => 'Macchiato'], 'added' => 6])
        ->assertJsonPath('data.periods.30.lists_shown', 3)
        ->assertJsonPath('data.periods.7.average_minor', 30000);

    asTenant($tenant, fn () => TenantAppearance::create([...TenantAppearance::DEFAULTS, 'show_order_list' => false]));
    $this->actingAs($owner)->getJson(t($tenant, 'widgets/orders'))->assertJsonPath('data.enabled', false);
});

it('shows the plan, days left and usage against the limits', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $plan = Plan::create(['name' => ['en' => 'Basic'], 'slug' => 'basic', 'limits' => ['items' => 10, 'photos' => 50]]);
    $tenant->forceFill(['plan_id' => $plan->id, 'paid_until' => now()->addDays(12)])->save();

    $this->actingAs($owner)->getJson(t($tenant, 'widgets/plan'))->assertOk()
        ->assertJsonPath('data.plan', ['en' => 'Basic'])
        ->assertJsonPath('data.days_left', 12)
        ->assertJsonPath('data.usage.0', ['key' => 'items', 'used' => 1, 'limit' => 10])
        ->assertJsonPath('data.usage.1', ['key' => 'photos', 'used' => 0, 'limit' => 50])
        ->assertJsonPath('data.usage.2', ['key' => 'branches', 'used' => 1, 'limit' => null]);
});

it('suggests the next plan up the ladder and what it adds (QUESTIONS #184)', function () {
    [$owner, $tenant] = member(Role::Owner);
    $tenant->forceFill(['plan_id' => Plan::where('slug', 'free')->value('id')])->save();
    PlatformSettings::save(['upgrade_whatsapp' => '+251 911 000 000']);

    $data = $this->actingAs($owner)->getJson(t($tenant, 'widgets/plan'))->assertOk()->json('data');
    expect($data['usage'][0])->toBe(['key' => 'items', 'used' => 0, 'limit' => null])
        ->and($data['usage'][1]['limit'])->toBe(100)
        ->and($data['next']['name'])->toBe(['en' => 'Plus'])
        ->and($data['next']['adds'])->toBe(['All 4 menu styles', 'Specials and announcements', 'Insights and monthly report', 'Up to 500 photos'])
        ->and($data['upgrade']['whatsapp'])->toBe('https://wa.me/251911000000');

    $tenant->forceFill(['plan_id' => Plan::where('slug', 'business')->value('id')])->save();
    Features::forget();
    expect($this->actingAs($owner)->getJson(t($tenant, 'widgets/plan'))->json('data.next'))->toBeNull();
});

it('answers each widget in a few queries', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, $category] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category) {
        foreach (range(1, 40) as $n) {
            Item::create(['category_id' => $category->id, 'name' => ['en' => 'D'.$n], 'price_minor' => 100]);
        }
    });
    foreach (['setup', 'trend', 'missing-photos', 'orders', 'plan'] as $widget) {
        $this->actingAs($owner)->getJson(t($tenant, 'widgets/'.$widget))->assertOk(); // warm-up (features, caches)
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($owner)->getJson(t($tenant, 'widgets/'.$widget))->assertOk();
        expect(count(DB::getQueryLog()))->toBeLessThan(16, $widget);
        DB::disableQueryLog();
    }
});

it('reads the cached order counts back from a real (serializing) cache store', function () {
    config(['cache.default' => 'file']);
    Cache::flush();
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    Stats::hit($tenant->id, now($tenant->timezone)->toDateString(), 0, 'order_add', $item->id, 2);

    foreach (['orders', 'trend'] as $widget) {
        $first = $this->actingAs($owner)->getJson(t($tenant, 'widgets/'.$widget))->assertOk()->json();
        $this->actingAs($owner)->getJson(t($tenant, 'widgets/'.$widget))->assertOk()->assertExactJson($first);
    }
    $this->actingAs($owner)->getJson(t($tenant, 'widgets/orders'))->assertJsonPath('data.periods.7.top.0.name', ['en' => 'Macchiato']);
    Cache::flush();
});

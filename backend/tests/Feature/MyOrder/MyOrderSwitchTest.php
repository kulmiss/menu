<?php

use App\Enums\Role;
use App\Jobs\RebuildAllMenuPages;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Notifications\MonthlyInsightsNotification;
use App\Support\Features;
use App\Support\MyOrder;
use App\Support\PlatformSettings;
use App\Support\PublicMenu\InlineAssets;
use App\Support\PublicMenu\MenuPageCache;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

/*
 * "My order" fully switchable (owner, 2026-11-19, QUESTIONS #236): guests see the "+"
 * buttons and the list only when the platform, the plan (or the Super Admin's override)
 * AND the restaurant's own switch are on; every place follows the same rule.
 */

/** A restaurant on its own plan, with a menu and an appearance row. */
function myOrderRestaurant(array $appearance = []): array
{
    $plan = Plan::create(['name' => ['en' => 'Test plan'], 'slug' => 'test-'.uniqid(), 'limits' => []]);
    [$owner, $tenant] = member(Role::Owner);
    $tenant->forceFill(['plan_id' => $plan->id])->save();
    sampleMenu($tenant);
    asTenant($tenant, fn () => TenantAppearance::create([...TenantAppearance::DEFAULTS, ...$appearance]));
    Features::forget();

    return [$owner, $tenant->fresh(), $plan];
}

/** A logged change, keys sorted (MySQL's JSON column may store keys in another order). */
function sortedChanges(?string $json): array
{
    $sort = function (array $a) use (&$sort): array {
        ksort($a);

        return array_map(fn ($v) => is_array($v) ? $sort($v) : $v, $a);
    };

    return $sort((array) json_decode((string) $json, true));
}

function setLevels(Tenant $tenant, Plan $plan, bool $platform, bool $planOn, bool $restaurant): void
{
    PlatformSettings::save(['features_global' => $platform ? [] : ['order_list' => false]]);
    DB::table('plan_features')->updateOrInsert(['plan_id' => $plan->id, 'feature_key' => 'order_list'], ['enabled' => $planOn]);
    asTenant($tenant, fn () => TenantAppearance::query()->update(['show_order_list' => $restaurant]));
    Features::forget();
    MenuPageCache::forget($tenant);
}

dataset('levels', function () {
    foreach ([true, false] as $platform) {
        foreach ([true, false] as $plan) {
            foreach ([true, false] as $restaurant) {
                yield sprintf('platform %s, plan %s, restaurant %s', $platform ? 'on' : 'off', $plan ? 'on' : 'off', $restaurant ? 'on' : 'off') => [$platform, $plan, $restaurant];
            }
        }
    }
});

it('follows platform × plan × restaurant everywhere: guest menu, owner screen, API, Super Admin, stats', function (bool $platform, bool $plan, bool $restaurant) {
    [$owner, $tenant, $p] = myOrderRestaurant();
    setLevels($tenant, $p, $platform, $plan, $restaurant);
    $on = $platform && $plan && $restaurant;
    $reason = ! $platform ? 'platform' : (! $plan ? 'plan' : (! $restaurant ? 'restaurant' : null));

    // Guest menu: "+", the data for the list, the list script and its CSS.
    $html = $this->get('/'.$tenant->slug)->assertOk()->getContent();
    expect(str_contains($html, 'data-x="A"'))->toBe($on)
        ->and(str_contains($html, 'class="it np ao"'))->toBe($on)
        ->and(str_contains($html, InlineAssets::sheetUrl(true)))->toBe($on)
        ->and(str_contains($html, InlineAssets::sheetUrl(false)))->toBe(! $on)
        ->and(str_contains($html, '.np>.ad{'))->toBe($on)
        ->and(str_contains($html, '"o":null'))->toBe(! $on);

    // Owner's screen and the restaurant's settings (new field only).
    $state = ['enabled' => $on, 'available' => $platform && $plan, 'restaurant' => $restaurant, 'reason' => $reason];
    $this->actingAs($owner)->getJson(t($tenant, 'appearance'))->assertOk()->assertJsonPath('data.my_order', $state)
        ->assertJsonPath('data.show_order_list', $restaurant);
    $this->getJson(t($tenant))->assertOk()->assertJsonPath('data.my_order', $state)
        ->assertJsonPath('data.features.order_list', $platform && $plan);
    $this->getJson(t($tenant, 'widgets/orders'))->assertOk()->assertJsonPath('data.enabled', $on);

    // Super Admin: the restaurants table shows the effective value, and filters on it.
    $admin = superAdmin();
    $row = collect($this->actingAs($admin)->getJson(p('restaurants').'?page=1&per_page=50')->assertOk()->json('data'))->firstWhere('id', $tenant->id);
    expect($row['my_order'])->toBe($on);
    $ids = collect($this->getJson(p('restaurants').'?page=1&per_page=50&my_order='.($on ? 'on' : 'off'))->json('data'))->pluck('id');
    expect($ids)->toContain($tenant->id);

    // Guests' "added to my order" counts only while it is on (an old saved page may still send them).
    $this->call('POST', '/api/v1/beacon', [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone)'],
        json_encode(['t' => $tenant->id, 'c' => [(int) DB::table('items')->where('tenant_id', $tenant->id)->value('id'), 1, 1000]]))->assertNoContent();
    expect(DB::table('stats_daily')->where('tenant_id', $tenant->id)->where('metric', 'order_add')->exists())->toBe($on);
    expect(MyOrder::state($tenant->fresh()))->toBe($state);
})->with('levels');

it('says the Super Admin turned it off for this restaurant when the override is off, even if the plan has it', function () {
    [$owner, $tenant, $plan] = myOrderRestaurant();
    DB::table('tenant_feature_overrides')->insert(['tenant_id' => $tenant->id, 'feature_key' => 'order_list', 'enabled' => false]);
    Features::forget();
    expect(MyOrder::state($tenant))->toBe(['enabled' => false, 'available' => false, 'restaurant' => true, 'reason' => 'platform']);

    // And an override ON beats a plan without it.
    DB::table('tenant_feature_overrides')->where('tenant_id', $tenant->id)->update(['enabled' => true]);
    DB::table('plan_features')->insert(['plan_id' => $plan->id, 'feature_key' => 'order_list', 'enabled' => false]);
    Features::forget();
    expect(MyOrder::state($tenant)['enabled'])->toBeTrue();
});

it('leaves nothing of My order on the guest menu of every style when it is off, and everything when on', function (string $style) {
    [, $tenant] = myOrderRestaurant(['style' => $style, 'show_order_list' => false]);
    $off = $this->get('/'.$tenant->slug)->assertOk()->getContent();
    expect($off)->not->toContain('data-x="A"')->not->toMatch('/class="it[^"]*\bao\b/')
        ->not->toContain('sheet-o.')->not->toContain('.ad{')->not->toContain('"q":{')
        ->not->toContain('My order')->not->toContain('Add to my order')
        ->toContain('"o":null');
    // None of the style's own "+" rules either.
    preg_match('#<style>(.*?)</style>#s', $off, $css);
    expect($css[1])->not->toMatch('/\.a[do](?![\w-])/');
    expect(strlen((string) gzencode($off, 9)))->toBeLessThan(30 * 1024);

    asTenant($tenant, fn () => TenantAppearance::query()->update(['show_order_list' => true]));
    MenuPageCache::forget($tenant);
    $on = $this->get('/'.$tenant->slug)->assertOk()->getContent();
    expect($on)->toContain('data-x="A"')->toContain(InlineAssets::sheetUrl(true))->toContain('.ad{');
    // Off is the smaller page.
    expect(strlen($off))->toBeLessThan(strlen($on));
})->with(TenantAppearance::STYLES);

it('right-to-left (style H) with My order off: still right-to-left, still no "+"', function () {
    [, $tenant] = myOrderRestaurant(['style' => 'arabic', 'menu_direction' => 'rtl', 'show_order_list' => false]);
    expect($this->get('/'.$tenant->slug)->assertOk()->getContent())->toContain('<html lang="en" dir="rtl">')->not->toContain('data-x="A"');
});

it('takes effect at once: the owner switch drops the saved page, the platform switch every saved page', function () {
    Queue::fake();
    $public = sys_get_temp_dir().'/menu-public-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($public);
    app()->usePublicPath($public);
    config(['menu.static_pages' => true]);
    [$owner, $tenant] = myOrderRestaurant();
    $this->get('/'.$tenant->slug)->assertOk();
    expect(File::exists(MenuPageCache::path($tenant->slug)))->toBeTrue();

    $body = ['primary_color' => '#b4432a', 'font' => 'system', 'style' => 'grid', 'show_prices' => true, 'show_photos' => true,
        'show_descriptions' => true, 'show_calories' => true, 'show_order_list' => false];
    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), $body, idem())->assertOk()->assertJsonPath('data.my_order.reason', 'restaurant');
    expect(File::exists(MenuPageCache::path($tenant->slug)))->toBeFalse();
    $this->assertDatabaseHas('activity_log', ['tenant_id' => $tenant->id, 'action' => 'appearance.updated']);
    $log = DB::table('activity_log')->where('tenant_id', $tenant->id)->where('action', 'appearance.updated')->latest('id')->first();
    expect(sortedChanges($log->changes)['show_order_list'])->toBe(['from' => true, 'to' => false])
        ->and((int) $log->actor_id)->toBe($owner->id);
    expect($this->get('/'.$tenant->slug)->getContent())->not->toContain('data-x="A"');

    // Platform switch: every saved page goes at once; the versions move on in a queued job.
    [, $other] = myOrderRestaurant();
    $this->get('/'.$other->slug)->assertOk();
    expect(File::exists(MenuPageCache::path($other->slug)))->toBeTrue();
    $admin = superAdmin();
    $this->actingAs($admin)->putJson(p('settings/features'), ['features' => ['order_list' => false]], idem())->assertOk();
    expect(File::exists(MenuPageCache::path($other->slug)))->toBeFalse();
    Queue::assertPushed(RebuildAllMenuPages::class);
    $this->assertDatabaseHas('activity_log', ['action' => 'platform.features_global_changed', 'actor_id' => $admin->id]);
    $version = $other->fresh()->cache_version;
    (new RebuildAllMenuPages)->handle();
    expect($other->fresh()->cache_version)->toBe($version + 1);
    Features::forget();
    expect($this->get('/'.$other->slug)->getContent())->not->toContain('data-x="A"');
    File::deleteDirectory($public);
});

it('lets only owners and managers change the restaurant switch, and only Super Admins the platform one', function () {
    [$owner, $tenant] = myOrderRestaurant();
    [$manager] = member(Role::Manager, $tenant);
    [$staff] = member(Role::Staff, $tenant);
    $body = ['primary_color' => '#b4432a', 'font' => 'system', 'style' => 'grid', 'show_prices' => true, 'show_photos' => true,
        'show_descriptions' => true, 'show_calories' => true, 'show_order_list' => false];

    $this->actingAs($staff)->putJson(t($tenant, 'appearance'), $body, idem())->assertForbidden();
    $this->actingAs($manager)->putJson(t($tenant, 'appearance'), $body, idem())->assertOk();
    $this->actingAs($owner)->putJson(p('settings/features'), ['features' => ['order_list' => false]], idem())->assertNotFound();
    $this->actingAs($owner)->getJson(p('settings/features/order_list/impact'))->assertNotFound();
    expect(Features::globallyOn('order_list'))->toBeTrue();
});

it('tells the Super Admin how many restaurants a change reaches before saving', function () {
    [, $a] = myOrderRestaurant();                              // has it, shows it
    [, $b] = myOrderRestaurant(['show_order_list' => false]);  // has it, own switch off
    [, $c, $planC] = myOrderRestaurant();
    DB::table('plan_features')->insert(['plan_id' => $planC->id, 'feature_key' => 'order_list', 'enabled' => false]); // plan without it
    $before = (int) DB::table('tenants')->whereNull('deleted_at')->count();

    $data = $this->actingAs(superAdmin())->getJson(p('settings/features/order_list/impact'))->assertOk()->json('data');
    expect($data['enabled'])->toBeTrue()
        ->and($data['restaurants'])->toBe($before - 1)          // all but $c (other restaurants have the default: on)
        ->and($data['guests_see'])->toBe($before - 2);          // and $b's own switch is off
    $this->getJson(p('settings/features/promotions/impact'))->assertOk()->assertJsonMissingPath('data.guests_see');
    $this->getJson(p('settings/features/not_a_feature/impact'))->assertNotFound();
});

it('keeps every field the current app reads, and only adds new ones', function () {
    [$owner, $tenant] = myOrderRestaurant();
    $this->actingAs($owner);
    // Exactly what Menu Admin 1.x reads today (lib/core/auth/models.dart, appearance_screen.dart, mobile config).
    $this->getJson('/api/v1/me')->assertOk()->assertJsonStructure(['data' => ['id', 'name', 'email', 'two_factor_enabled', 'is_super_admin', 'tenants' => [['id', 'slug', 'name', 'timezone', 'default_locale', 'currency_code', 'subscription' => ['state', 'allows_writes', 'shows_banner', 'stage_ends_at'], 'features' => ['order_list', 'sold_out', 'promotions'], 'upgrade', 'membership' => ['id', 'role', 'can_edit_prices'], 'my_order' => ['enabled', 'available', 'restaurant', 'reason']]]]]);
    $this->getJson(t($tenant, 'appearance'))->assertOk()->assertJsonStructure(['data' => ['primary_color', 'font', 'style', 'styles', 'banner_text', 'menu_direction', 'show_prices', 'show_photos', 'show_descriptions', 'show_calories', 'show_share', 'show_pdf_link', 'show_order_list', 'show_other_branches', 'show_logo', 'show_banner', 'logo', 'cover', 'my_order']]);
    expect($this->getJson(t($tenant, 'appearance'))->json('data.show_order_list'))->toBeBool();
    // Turned off: the same shapes, never an error the app doesn't know.
    asTenant($tenant, fn () => TenantAppearance::query()->update(['show_order_list' => false]));
    $this->getJson(t($tenant, 'widgets/orders'))->assertOk()->assertExactJson(['data' => ['enabled' => false]]);
    $this->getJson('/api/v1/mobile/config')->assertOk()->assertJsonPath('data.features.order_list', true)->assertJsonPath('data.my_order', true)
        ->assertJsonStructure(['data' => ['name', 'min_version', 'latest_version', 'flags', 'versions', 'sign_in', 'maintenance', 'features', 'app_features', 'links']]);
    // The app still saves Appearance the way it does today (without the new field).
    $this->putJson(t($tenant, 'appearance'), ['primary_color' => '#b4432a', 'font' => 'system', 'style' => 'grid', 'show_prices' => true, 'show_photos' => true,
        'show_descriptions' => true, 'show_calories' => true, 'show_order_list' => true], idem())->assertOk()->assertJsonPath('data.my_order.enabled', true);
});

it('hides the My order help when the feature is off, but keeps the article about the switch', function () {
    [$owner, $tenant] = myOrderRestaurant();
    $slugs = fn () => collect($this->actingAs($owner)->getJson(t($tenant, 'help'))->assertOk()->json('data.articles'))->pluck('slug');
    expect($slugs())->toContain('my-order')->toContain('my-order-switch');
    PlatformSettings::save(['features_global' => ['order_list' => false]]);
    Features::forget();
    expect($slugs())->not->toContain('my-order')->toContain('my-order-switch');
});

it('logs the plan and the per-restaurant switch from old to new, and moves the restaurant\'s menu version on', function () {
    [, $tenant, $plan] = myOrderRestaurant();
    DB::table('plan_features')->insert(['plan_id' => $plan->id, 'feature_key' => 'order_list', 'enabled' => true]);
    $admin = superAdmin();
    $version = $tenant->cache_version;

    $this->actingAs($admin)->patchJson(p("plans/{$plan->id}"), ['features' => ['order_list' => false, 'sold_out' => true]], idem())->assertOk();
    $log = DB::table('activity_log')->where('action', 'platform.plan_updated')->latest('id')->first();
    expect((int) $log->actor_id)->toBe($admin->id)
        ->and(sortedChanges($log->changes))->toBe(['features' => ['from' => ['order_list' => true, 'sold_out' => null], 'to' => ['order_list' => false, 'sold_out' => true]]])
        ->and($tenant->fresh()->cache_version)->toBe($version + 1);
    Features::forget();
    expect(MyOrder::state($tenant->fresh())['reason'])->toBe('plan');

    $this->putJson(p("restaurants/{$tenant->id}/features"), ['features' => ['order_list' => true]], idem())->assertOk();
    $this->putJson(p("restaurants/{$tenant->id}/features"), ['features' => ['order_list' => null]], idem())->assertOk();
    $logs = DB::table('activity_log')->where('action', 'platform.features_changed')->where('subject_id', $tenant->id)->orderBy('id')->pluck('changes')
        ->map(fn ($c) => sortedChanges($c))->all();
    expect($logs)->toBe([
        ['features' => ['from' => ['order_list' => null], 'to' => ['order_list' => true]]],
        ['features' => ['from' => ['order_list' => true], 'to' => ['order_list' => null]]],
    ])->and($tenant->fresh()->cache_version)->toBe($version + 3);
});

it('leaves My order out of the monthly report e-mail when the restaurant has it off', function () {
    [$owner, $tenant] = myOrderRestaurant();
    $mail = fn () => implode(' ', (new MonthlyInsightsNotification($tenant->id, now()->subMonth()->format('Y-m')))->toMail($owner)->introLines);
    expect($mail())->toContain('“My order”');
    asTenant($tenant, fn () => TenantAppearance::query()->update(['show_order_list' => false]));
    expect($mail())->not->toContain('My order')->toContain('QR scans');
});

it('takes only the "+" selectors out of a style sheet', function () {
    $css = 'a{x:1}.ad,b{y:2}li>.it.ao .nm,c:is(.x,.y){z:3}@media (min-width:40rem){.ao{p:1}d{q:1}}@media(x){.ad:before{r}}'
        .'@font-face{font-family:F;src:url("a{b}.woff2")}.add{k:1}.aox{k:2}';
    expect(InlineAssets::withoutSelectors($css, '/\\.a[do](?![\\w-])/'))->toBe(
        'a{x:1}b{y:2}c:is(.x,.y){z:3}@media (min-width:40rem){d{q:1}}@font-face{font-family:F;src:url("a{b}.woff2")}.add{k:1}.aox{k:2}'
    );
    // The page with My order on keeps all of it.
    expect(InlineAssets::css('mono', true))->toContain('.np>.ad{')
        ->and(InlineAssets::css('mono', false))->not->toMatch('/\\.a[do](?![\\w-])/')->toContain('li>.it .im{');
});

it('lets CI seed the demo café with My order off', function () {
    config(['menu.demo_options' => ['no-my-order']]);
    $this->seed(DemoSeeder::class);
    $tenant = Tenant::where('slug', 'demo')->firstOrFail();
    expect(MyOrder::state($tenant)['reason'])->toBe('restaurant')
        ->and($this->get('/demo')->assertOk()->getContent())->not->toContain('data-x="A"');
});

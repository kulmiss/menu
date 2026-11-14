<?php

use App\Enums\Role;
use App\Support\Features;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;

/** Settings → Features: platform-wide switches (owner, 2026-11-14, QUESTIONS #192). */
it('lists every feature, on by default, with how many plans and restaurants have it off', function () {
    [, $tenant] = member(Role::Owner);
    DB::table('tenant_feature_overrides')->insert(['tenant_id' => $tenant->id, 'feature_key' => 'promotions', 'enabled' => false]);
    $rows = collect($this->actingAs(superAdmin())->getJson(p('settings/features'))->assertOk()->json('data'))->keyBy('key');

    expect($rows->keys()->all())->toEqualCanonicalizing([...array_keys(Features::DEFAULTS), 'signup'])
        ->and($rows->every(fn ($r) => $r['enabled']))->toBeTrue()
        ->and($rows['promotions']['restaurants_off'])->toBe(1)
        ->and($rows['signup']['plan_feature'])->toBeFalse()
        ->and($rows['sold_out']['label'])->toBe('“Sold out” marks');
});

it('switches a feature off for everyone, over the plan and the restaurant, and back', function () {
    [$owner, $tenant] = member(Role::Owner);
    DB::table('tenant_feature_overrides')->insert(['tenant_id' => $tenant->id, 'feature_key' => 'promotions', 'enabled' => true]);
    $version = $tenant->fresh()->cache_version;

    $this->actingAs(superAdmin())->putJson(p('settings/features'), ['features' => ['promotions' => false, 'share' => false]], idem())->assertOk()
        ->assertJsonFragment(['key' => 'promotions', 'enabled' => false]);
    Features::forget();
    expect(Features::enabled($tenant->fresh(), 'promotions'))->toBeFalse()
        ->and($tenant->fresh()->cache_version)->toBeGreaterThan($version) // guest pages rebuild
        ->and(PlatformSettings::get('features_global'))->toBe(['promotions' => false, 'share' => false]);
    $this->assertDatabaseHas('activity_log', ['action' => 'platform.features_global_changed']);

    // The restaurant's own API, its /me and the guest menu follow.
    $this->actingAs($owner)->getJson('/api/v1/me')->assertJsonPath('data.tenants.0.features.promotions', false);
    $this->postJson(t($tenant, 'promotions'), ['kind' => 'announcement', 'title' => ['en' => 'Hi']], idem())->assertStatus(402)->assertJsonPath('code', 'features.promotions');
    sampleMenu($tenant);
    expect($this->get('/'.$tenant->slug)->assertOk()->getContent())->not->toContain('data-x="H"');

    $this->actingAs(superAdmin())->putJson(p('settings/features'), ['features' => ['promotions' => true, 'share' => true]], idem())->assertOk();
    Features::forget();
    expect(Features::enabled($tenant->fresh(), 'promotions'))->toBeTrue() // the restaurant's own "on" is back
        ->and(PlatformSettings::get('features_global'))->toBe([]);
    $tenant->bumpCacheVersion();
    expect($this->get('/'.$tenant->slug)->getContent())->toContain('data-x="H"');
});

it('blocks print materials and sold out through the API when off for everyone', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    PlatformSettings::save(['features_global' => ['print_materials' => false, 'sold_out' => false]]);
    Features::forget();
    $this->actingAs($owner);
    $this->getJson(t($tenant, 'print-materials'))->assertStatus(402)->assertJsonPath('code', 'features.print_materials');
    $this->patchJson(t($tenant, "items/{$item->id}/sold-out"), ['is_sold_out' => true], idem())->assertStatus(402);
});

it('opens and closes sign-up from the same list', function () {
    $this->actingAs(superAdmin())->putJson(p('settings/features'), ['features' => ['signup' => false]], idem())->assertOk();
    expect(PlatformSettings::get('signup_open'))->toBeFalse();
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/signup/config')->assertOk()->assertJsonPath('data.open', false);
});

it('keeps at least one menu style, refuses unknown keys, and is for Super Admins only', function () {
    [$owner] = member(Role::Owner);
    $this->actingAs(superAdmin())->putJson(p('settings/features'), ['features' => ['style_editorial' => false, 'style_grid' => false, 'style_bistro' => false, 'style_minimal' => false]], idem())
        ->assertUnprocessable()->assertJsonValidationErrors('features');
    $this->putJson(p('settings/features'), ['features' => ['nope' => false]], idem())->assertUnprocessable();
    $this->actingAs($owner)->getJson(p('settings/features'))->assertNotFound();
});

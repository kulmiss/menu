<?php

use App\Enums\Role;
use App\Models\Plan;
use App\Support\Features;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;

/*
 * Team can be switched off (owner, 2026-11-24): for everyone (Settings → Features),
 * per plan, or per restaurant. Off: the Team page and inviting are blocked (the admin
 * hides the menu item too); members already on the restaurant keep working, because
 * their access is their membership, not these routes.
 */

it('is on by default, as before', function () {
    [$owner, $tenant] = member(Role::Owner);
    expect(Features::DEFAULTS['team'])->toBeTrue();
    $this->actingAs($owner)->getJson(t($tenant, 'members'))->assertOk();
    $this->actingAs($owner)->getJson(t($tenant, 'invitations'))->assertOk();
});

it('switched off for everyone: the Team page and inviting are blocked', function () {
    [$owner, $tenant] = member(Role::Owner);
    $this->actingAs(superAdmin())->putJson(p('settings/features'), ['features' => ['team' => false]], idem())->assertOk();
    Features::forget();

    $this->actingAs($owner);
    $this->getJson(t($tenant, 'members'))->assertStatus(402)->assertJsonPath('code', 'features.team');
    $this->getJson(t($tenant, 'invitations'))->assertStatus(402);
    $this->postJson(t($tenant, 'invitations'), ['email' => 'dawit@example.com', 'role' => 'staff'], idem())->assertStatus(402);

    // On again: everything is back.
    $this->actingAs(superAdmin())->putJson(p('settings/features'), ['features' => ['team' => true]], idem())->assertOk();
    Features::forget();
    $this->actingAs($owner)->getJson(t($tenant, 'members'))->assertOk();
});

it('leaves existing members working when team is off', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$manager] = member(Role::Manager, $tenant);
    [$staff] = member(Role::Staff, $tenant);

    $this->actingAs(superAdmin())->putJson(p('settings/features'), ['features' => ['team' => false]], idem())->assertOk();
    Features::forget();

    // Their access is their membership, not the team routes: they can still use the restaurant.
    $this->actingAs($owner)->getJson(t($tenant, '/'))->assertOk();
    $this->actingAs($manager)->getJson(t($tenant, '/'))->assertOk();
    $this->actingAs($staff)->getJson(t($tenant, '/'))->assertOk();

    // Nobody was removed.
    expect($tenant->memberships()->count())->toBe(3);
});

it('can be switched off for one plan or one restaurant only', function () {
    [$owner, $tenant] = member(Role::Owner);
    $plan = Plan::create(['name' => ['en' => 'No team'], 'slug' => 'no-team', 'limits' => []]);
    DB::table('plan_features')->insert(['plan_id' => $plan->id, 'feature_key' => 'team', 'enabled' => false]);
    $tenant->forceFill(['plan_id' => $plan->id])->save();
    Features::forget();
    $this->actingAs($owner)->getJson(t($tenant, 'members'))->assertStatus(402);

    // The restaurant's own switch wins over its plan.
    DB::table('tenant_feature_overrides')->insert(['tenant_id' => $tenant->id, 'feature_key' => 'team', 'enabled' => true]);
    Features::forget();
    $this->actingAs($owner)->getJson(t($tenant, 'members'))->assertOk();
    expect(PlatformSettings::get('features_global'))->not->toHaveKey('team');
});

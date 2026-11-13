<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Impersonation;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OwnerWelcomeNotification;
use Illuminate\Support\Facades\Notification;

it('is only for Super Admins with two-factor sign-in', function () {
    [$owner] = member(Role::Owner);

    $this->actingAs($owner)->getJson(p('dashboard'))->assertNotFound();
    $this->actingAs(superAdmin(twoFactor: false))->getJson(p('dashboard'))->assertForbidden()->assertJsonPath('code', 'platform.two_factor_required');
    $this->actingAs(superAdmin())->getJson(p('dashboard'))->assertOk()->assertJsonStructure(['data' => ['states', 'signups_30d', 'views_30d', 'per_plan', 'expiring']]);
});

it('creates a restaurant with a new owner who gets a set-password e-mail', function () {
    Notification::fake();
    $admin = superAdmin();

    $id = $this->actingAs($admin)->postJson(p('restaurants'), [
        'name' => 'Sahan Grill', 'slug' => 'sahan-grill', 'owner_name' => 'Ayaan Warsame', 'owner_email' => 'ayaan@example.com', 'trial_days' => 30,
    ], idem())->assertCreated()->assertJsonPath('data.state', 'trial')->json('data.id');

    $owner = User::where('email', 'ayaan@example.com')->firstOrFail();
    expect($owner->membershipFor(Tenant::find($id))?->role)->toBe(Role::Owner);
    Notification::assertSentTo($owner, OwnerWelcomeNotification::class); // details: OwnerWelcomeTest

    $this->actingAs($admin)->postJson(p('restaurants'), ['name' => 'X', 'slug' => 'sahan-grill', 'owner_name' => 'X', 'owner_email' => 'x@example.com'], idem())->assertInvalid('slug');
    $this->actingAs($admin)->getJson(p('restaurants?search=sahan'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.owner.email', 'ayaan@example.com');
    $this->actingAs($admin)->getJson(p('restaurants?state=trial'))->assertJsonPath('data.0.id', $id);
});

it('suspends a restaurant (menu unavailable) and brings it back', function () {
    $admin = superAdmin();
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);

    $this->actingAs($admin)->postJson(p("restaurants/{$tenant->id}/suspend"), ['reason' => 'Unpaid invoice'], idem())->assertOk()->assertJsonPath('data.state', 'suspended');
    $this->get('/'.$tenant->slug)->assertStatus(503);

    $this->actingAs($admin)->postJson(p("restaurants/{$tenant->id}/unsuspend"), [], idem())->assertOk()->assertJsonPath('data.state', 'trial');
    $this->get('/'.$tenant->slug)->assertOk();
});

it('deletes softly and can restore within 30 days', function () {
    $admin = superAdmin();
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)->deleteJson(p("restaurants/{$tenant->id}"), [], idem())->assertNoContent();
    $this->get('/'.$tenant->slug)->assertNotFound();
    $this->actingAs($admin)->getJson(p('restaurants?deleted=1'))->assertJsonPath('data.0.id', $tenant->id);

    $this->actingAs($admin)->postJson(p("restaurants/{$tenant->id}/restore"), [], idem())->assertOk();
    expect(Tenant::find($tenant->id))->not->toBeNull();
});

it('records a payment that extends the paid period, and exports payments', function () {
    $admin = superAdmin();
    $tenant = Tenant::factory()->expired(3)->create();
    expect($tenant->subscriptionState()->value)->toBe('grace');

    $this->actingAs($admin)->postJson(p("restaurants/{$tenant->id}/payments"), [
        'amount_minor' => 150000, 'method' => 'mobile_money', 'reference' => 'TB-123', 'paid_at' => now()->toDateString(), 'months' => 3,
    ], idem())->assertCreated()->assertJsonPath('data.period_end', now()->addMonthsNoOverflow(3)->toDateString());

    expect($tenant->fresh()->subscriptionState()->value)->toBe('active')
        ->and($tenant->fresh()->paid_until->toDateString())->toBe(now()->addMonthsNoOverflow(3)->toDateString());

    // A second payment continues from the end of the first.
    $this->actingAs($admin)->postJson(p("restaurants/{$tenant->id}/payments"), ['amount_minor' => 50000, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'months' => 1], idem())
        ->assertJsonPath('data.period_start', now()->addMonthsNoOverflow(3)->toDateString());

    $csv = (string) $this->actingAs($admin)->get(p('payments.csv'))->assertOk()->getContent();
    expect($csv)->toContain('TB-123')->toContain('1500.00')->toContain($tenant->name);
});

it('turns plan features on and off, per plan and per restaurant', function () {
    $admin = superAdmin();
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $planId = $this->actingAs($admin)->postJson(p('plans'), [
        'name' => ['en' => 'Basic'], 'slug' => 'basic', 'price_minor' => 50000, 'limits' => ['menus' => 1, 'branches' => 1],
        'features' => ['promotions' => false, 'remove_branding' => false],
    ], idem())->assertCreated()->json('data.id');
    $tenant->forceFill(['plan_id' => $planId])->save();
    asTenant($tenant, fn () => Promotion::create(['type' => 'announcement', 'title' => ['en' => 'Happy hour']]));

    $this->actingAs($owner)->postJson(t($tenant, 'promotions'), ['type' => 'special', 'title' => ['en' => 'X']], idem())
        ->assertStatus(402)->assertJsonPath('code', 'features.promotions');
    $this->get('/'.$tenant->slug)->assertDontSee('Happy hour')->assertSee('Menu by');
    $this->actingAs($owner)->getJson('/api/v1/me')->assertJsonPath('data.tenants.0.features.promotions', false);

    $this->actingAs($admin)->putJson(p("restaurants/{$tenant->id}/features"), ['features' => ['promotions' => true, 'remove_branding' => true]], idem())
        ->assertOk()->assertJsonPath('data.features.promotions', true);
    $this->get('/'.$tenant->slug)->assertSee('Happy hour')->assertDontSee('Menu by');

    $plans = collect($this->actingAs($admin)->getJson(p('plans'))->json('data'));
    expect($plans->firstWhere('slug', 'basic')['features']['promotions'])->toBeFalse();
});

it('shows platform announcements to the restaurants they are meant for', function () {
    $admin = superAdmin();
    [$owner, $tenant] = member(Role::Owner);
    $plan = Plan::create(['name' => ['en' => 'Pro'], 'slug' => 'pro', 'limits' => []]);

    $all = $this->actingAs($admin)->postJson(p('announcements'), ['title' => ['en' => 'New feature: QR table cards']], idem())->assertCreated()->json('data.id');
    $this->actingAs($admin)->postJson(p('announcements'), ['title' => ['en' => 'Pro only'], 'plan_ids' => [$plan->id]], idem())->assertCreated();
    $this->actingAs($admin)->postJson(p('announcements'), ['title' => ['en' => 'Later'], 'starts_at' => now()->addDay()->toIso8601String()], idem())->assertCreated();

    $this->actingAs($owner)->getJson(t($tenant, 'announcements'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.title.en', 'New feature: QR table cards');
    $this->actingAs($owner)->postJson(t($tenant, "announcements/{$all}/read"), [], idem())->assertNoContent();
    $this->actingAs($owner)->getJson(t($tenant, 'announcements'))->assertJsonCount(0, 'data');
});

it('keeps settings, themes and the activity log', function () {
    $admin = superAdmin();
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);

    $this->actingAs($admin)->putJson(p('settings'), ['name' => 'MenuHub', 'support_email' => 'help@example.com'], idem())->assertOk()->assertJsonPath('data.name', 'MenuHub');
    $this->get('/'.$tenant->slug)->assertSee('Menu by MenuHub');

    $this->actingAs($admin)->postJson(p('themes'), ['name' => ['en' => 'Coffee'], 'primary' => '#6b3e26', 'font' => 'serif', 'style' => 'bistro'], idem())->assertCreated();
    $this->actingAs($admin)->getJson(p('themes'))->assertJsonPath('data.5.primary', '#6b3e26');

    $this->actingAs($admin)->getJson(p('activity?platform_only=1'))->assertOk()->assertJsonPath('data.0.action', 'platform.theme_created');
});

/** Starts "log in as" and returns the session the browser would now carry. */
function impersonate(User $admin, Tenant $tenant): array
{
    browser();
    // One click: no reason needed (owner, 2026-11-13).
    $response = test()->actingAs($admin)->postJson(p("restaurants/{$tenant->id}/impersonate"), [], idem())->assertOk();
    $response->assertSessionHas('impersonator_id', $admin->id)->assertSessionHas('impersonation_id');

    return [
        'impersonation_id' => session('impersonation_id'),
        'impersonator_id' => $admin->id,
        'impersonation_ends_at' => session('impersonation_ends_at'),
    ];
}

it('logs in as the owner in one click, records it silently and keeps the safety blocks', function () {
    $admin = superAdmin();
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);

    $session = impersonate($admin, $tenant);
    // Who, which restaurant, when.
    $record = Impersonation::sole();
    expect([$record->super_admin_id, $record->tenant_id, $record->user_id, $record->ended_at])->toBe([$admin->id, $tenant->id, $owner->id, null])
        ->and($record->started_at)->not->toBeNull();
    $start = ActivityLog::where('action', 'platform.impersonation_started')->sole();
    expect([$start->actor_id, $start->tenant_id])->toBe([$admin->id, $tenant->id]);

    $this->actingAs($owner)->withSession($session);
    $this->getJson('/api/v1/me')->assertJsonPath('data.id', $owner->id)
        ->assertJsonPath('data.impersonation.tenant_id', $tenant->id)
        ->assertJsonPath('data.impersonation.tenant_name', $tenant->name);
    $this->getJson(p('dashboard'))->assertNotFound();

    // Every change carries the Super Admin's id.
    $this->patchJson(t($tenant, "items/{$item->id}"), ['name' => ['en' => 'Cortado']], idem())->assertOk();
    $log = ActivityLog::where('action', 'item.updated')->latest('id')->first();
    expect([$log->actor_id, $log->impersonator_id])->toBe([$owner->id, $admin->id]);

    // Password, e-mail, two-factor and team stay blocked.
    $this->putJson('/api/v1/auth/user/password', ['current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'], idem())
        ->assertForbidden()->assertJsonPath('code', 'impersonation.blocked');
    $this->putJson('/api/v1/auth/user/profile-information', ['name' => 'X', 'email' => 'x@example.com'], idem())->assertForbidden();
    $this->postJson('/api/v1/auth/user/two-factor-authentication', [], idem())->assertForbidden();
    $this->patchJson(t($tenant, 'account/slug'), ['slug' => 'other-name'], idem())->assertForbidden();
    expect(Hash::check('password', $owner->fresh()->password))->toBeTrue();

    $this->postJson('/api/v1/impersonation/stop', [], idem())->assertOk()->assertJsonPath('data.redirect', '/platform')->assertSessionMissing('impersonation_id');
    expect(Impersonation::whereNull('ended_at')->exists())->toBeFalse();
});

it('ends "log in as" automatically after 2 hours', function () {
    config(['session.lifetime' => 600]);
    $admin = superAdmin();
    [$owner, $tenant] = member(Role::Owner);
    $session = impersonate($admin, $tenant);

    $this->travel(119)->minutes();
    $this->actingAs($owner)->withSession($session)->getJson(t($tenant))->assertOk();
    $this->travel(2)->minutes();
    $this->getJson(t($tenant))->assertStatus(409)
        ->assertJsonPath('code', 'impersonation.expired')->assertSessionMissing('impersonation_id');
    expect(Impersonation::whereNull('ended_at')->exists())->toBeFalse();
});

it('never shows "viewing as" to the restaurant owner in their own session', function () {
    $admin = superAdmin();
    [$owner, $tenant] = member(Role::Owner);
    impersonate($admin, $tenant);

    // The owner's own browser has its own session without the impersonation keys.
    $this->flushSession();
    $this->actingAs($owner)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.impersonation', null);
});

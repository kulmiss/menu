<?php

use App\Enums\Role;
use App\Models\PersonalAccessToken;

it('lets a Super Admin "log in as" the owner from the app for 2 hours', function () {
    [$owner, $tenant] = member(Role::Owner);
    $admin = superAdmin();
    $adminToken = $admin->createToken('Admin phone', ['mobile'])->plainTextToken;

    $res = $this->withHeaders(bearer($adminToken))->postJson(p("restaurants/{$tenant->id}/impersonate-token"), [], idem())->assertOk();
    $token = $res->json('data.token');
    expect($res->json('data.tenant_id'))->toBe($tenant->id)
        ->and(PersonalAccessToken::where('impersonator_id', $admin->id)->sole()->tokenable_id)->toBe($owner->id);

    $this->app['auth']->forgetGuards();
    $this->withHeaders(bearer($token))->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.id', $owner->id)
        ->assertJsonPath('data.impersonation.tenant_id', $tenant->id);
    $this->getJson(t($tenant, 'menus'))->assertOk();

    // Blocked like on the web, and never the platform area.
    $this->putJson('/api/v1/mobile/account/password', ['current_password' => 'x', 'password' => 'another-long-secret', 'password_confirmation' => 'another-long-secret'], idem())
        ->assertForbidden()->assertJsonPath('code', 'impersonation.blocked');
    $this->patchJson(t($tenant, 'account/slug'), ['slug' => 'new-name'], idem())->assertForbidden();
    $this->getJson(p('dashboard'))->assertNotFound();
    // The owner's device list doesn't show the support sign-in.
    expect(collect($this->getJson('/api/v1/mobile/devices')->json('data'))->pluck('name')->all())->not->toContain('Log in as');

    $this->travel(121)->minutes();
    $this->app['auth']->forgetGuards();
    $this->withHeaders(bearer($token))->getJson('/api/v1/me')->assertUnauthorized();
});

it('ends "log in as" from the app by deleting the token', function () {
    [, $tenant] = member(Role::Owner);
    $admin = superAdmin();
    $adminToken = $admin->createToken('Admin phone', ['mobile'])->plainTextToken;
    $token = $this->withHeaders(bearer($adminToken))->postJson(p("restaurants/{$tenant->id}/impersonate-token"), [], idem())->json('data.token');

    $this->app['auth']->forgetGuards();
    $this->withHeaders(bearer($token))->postJson('/api/v1/impersonation/stop', [], idem())->assertOk();
    expect(PersonalAccessToken::whereNotNull('impersonator_id')->count())->toBe(0);

    $this->app['auth']->forgetGuards();
    $this->withHeaders(bearer($adminToken))->getJson(p('dashboard'))->assertOk();
});

it('refuses "log in as" tokens to anyone but a Super Admin with two-factor, and from the web session', function () {
    [, $tenant] = member(Role::Owner);
    [$manager] = member(Role::Manager, $tenant);
    $this->withHeaders(bearer($manager->createToken('x', ['mobile'])->plainTextToken))
        ->postJson(p("restaurants/{$tenant->id}/impersonate-token"), [], idem())->assertNotFound();

    $this->app['auth']->forgetGuards();
    $this->withHeaders(bearer(superAdmin(twoFactor: false)->createToken('x', ['mobile'])->plainTextToken))
        ->postJson(p("restaurants/{$tenant->id}/impersonate-token"), [], idem())->assertForbidden();

    $this->app['auth']->forgetGuards();
    $this->flushHeaders();
    browser();
    $this->actingAs(superAdmin())->postJson(p("restaurants/{$tenant->id}/impersonate-token"), [], idem())->assertStatus(400);
});

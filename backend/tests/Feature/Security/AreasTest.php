<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * Two separate areas (QUESTIONS #215–#220): /api/v1/super for Super Admins,
 * /api/v1/restaurant/{tenant} for restaurant teams. Built from the route list,
 * so a new route is checked here as soon as it exists.
 */
function areaRoutes(string $prefix): array
{
    $out = [];
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), $prefix)) {
            continue;
        }
        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
            $out[] = ['key' => $method.' '.$route->uri(), 'method' => $method, 'uri' => $route->uri(), 'wheres' => $route->wheres];
        }
    }

    return $out;
}

/** Status of every route in the area for the current person (or token headers). */
function areaStatuses(string $prefix, int $tenantId = 1, array $headers = []): array
{
    $out = [];
    foreach (areaRoutes($prefix) as $r) {
        if ($headers !== []) {
            app('auth')->forgetGuards(); // a token request: no user left over from the last one
        }
        $out[$r['key']] = test()->json($r['method'], matrixUrl($r['uri'], $tenantId, $r['wheres']), [], [...idem(), ...$headers])->status();
    }

    return $out;
}

it('has the two areas and nothing left at the old addresses', function () {
    expect(areaRoutes('api/v1/super/'))->not->toBeEmpty()
        ->and(areaRoutes('api/v1/restaurant/{tenant}'))->not->toBeEmpty()
        ->and(areaRoutes('api/v1/platform'))->toBe([])
        ->and(areaRoutes('api/v1/t/'))->toBe([]);

    $this->actingAs(superAdmin())->getJson('/api/v1/platform/dashboard')->assertNotFound();
    [$owner, $tenant] = member(Role::Owner);
    $this->actingAs($owner)->getJson("/api/v1/t/{$tenant->id}/items")->assertNotFound();
});

it('gives every person and token the right area, and 404 for the other', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$manager] = member(Role::Manager, $tenant);
    [$staff] = member(Role::Staff, $tenant);
    $admin = superAdmin();

    // Restaurant people: the Super Admin area doesn't exist for them (404, or 429 from a route limit that runs first).
    foreach (['owner' => $owner, 'manager' => $manager, 'staff' => $staff] as $who => $user) {
        $this->actingAs($user);
        $wrong = array_filter(areaStatuses('api/v1/super/', $tenant->id), fn ($s) => ! in_array($s, [404, 429], true));
        expect($wrong)->toBe([], "$who in the Super Admin area");
    }

    // The Super Admin: never 401/403/404 in their own area…
    $this->actingAs($admin);
    $wrong = array_filter(areaStatuses('api/v1/super/', $tenant->id), fn ($s) => in_array($s, [401, 403], true));
    expect($wrong)->toBe([]);
    $this->getJson(p('dashboard'))->assertOk();
    // …and 404 everywhere in a restaurant's area.
    $wrong = array_filter(areaStatuses('api/v1/restaurant/{tenant}', $tenant->id), fn ($s) => $s !== 404);
    expect($wrong)->toBe([]);
});

it('keeps app tokens to their own area', function () {
    [$owner, $tenant] = member(Role::Owner);
    $admin = superAdmin();
    $ownerToken = $owner->createToken('Phone', PersonalAccessToken::abilitiesFor($owner))->plainTextToken;
    $adminToken = $admin->createToken('Phone', PersonalAccessToken::abilitiesFor($admin))->plainTextToken;
    // A Super Admin's token without the 'super' scope (e.g. made for another purpose) opens nothing there.
    $unscoped = $admin->createToken('Other', ['mobile'])->plainTextToken;

    expect(PersonalAccessToken::abilitiesFor($owner))->toBe(['mobile', 'restaurant'])
        ->and(PersonalAccessToken::abilitiesFor($admin))->toBe(['mobile', 'super']);

    $this->withHeaders(bearer($ownerToken))->getJson(t($tenant, 'menus'))->assertOk();
    $this->app['auth']->forgetGuards();
    $this->withHeaders(bearer($ownerToken))->getJson(p('dashboard'))->assertNotFound();
    $this->app['auth']->forgetGuards();
    $this->withHeaders(bearer($adminToken))->getJson(p('dashboard'))->assertOk();
    $this->app['auth']->forgetGuards();
    $this->withHeaders(bearer($adminToken))->getJson(t($tenant, 'menus'))->assertNotFound();
    $this->app['auth']->forgetGuards();
    $this->withHeaders(bearer($unscoped))->getJson(p('dashboard'))->assertNotFound();

    // Every Super Admin route refuses the restaurant token.
    $wrong = array_filter(areaStatuses('api/v1/super/', $tenant->id, bearer($ownerToken)), fn ($s) => ! in_array($s, [404, 429], true));
    expect($wrong)->toBe([]);
});

it('opens the restaurant area to a Super Admin only through "Log in as", and closes their own meanwhile', function () {
    [$owner, $tenant] = member(Role::Owner);
    $admin = superAdmin();
    browser();
    $this->actingAs($admin)->getJson(t($tenant))->assertNotFound();

    $this->postJson(p("restaurants/{$tenant->id}/impersonate"), [], idem())->assertOk();
    $session = ['impersonation_id' => session('impersonation_id'), 'impersonator_id' => $admin->id, 'impersonation_ends_at' => session('impersonation_ends_at')];
    $this->actingAs($owner)->withSession($session);
    $this->getJson(t($tenant))->assertOk();
    $this->getJson(t($tenant, 'menus'))->assertOk();
    $this->getJson(p('dashboard'))->assertNotFound();

    // The app's "log in as" token is marked and scoped to the restaurant.
    $adminToken = $admin->createToken('Phone', PersonalAccessToken::abilitiesFor($admin))->plainTextToken;
    app('auth')->forgetGuards();
    $this->flushSession();
    $token = $this->withHeaders(bearer($adminToken))->postJson(p("restaurants/{$tenant->id}/impersonate-token"), [], idem())->assertOk()->json('data.token');
    expect(PersonalAccessToken::findToken($token)->abilities)->toBe(['mobile', 'restaurant', 'impersonation']);
});

it('keeps other restaurants out (IDOR) in the restaurant area', function () {
    [, $tenant] = member(Role::Owner);
    [$stranger] = member(Role::Owner);
    $token = $stranger->createToken('Phone', PersonalAccessToken::abilitiesFor($stranger))->plainTextToken;

    $wrong = array_filter(areaStatuses('api/v1/restaurant/{tenant}', $tenant->id, bearer($token)), fn ($s) => ! in_array($s, [403, 404, 429], true));
    expect($wrong)->toBe([]);
});

it('lets only listed addresses reach the Super Admin area when the allow-list is on', function () {
    $admin = superAdmin();
    config(['menu.super_admin.ips' => ['203.0.113.0/24', '2001:db8::1']]);

    $this->actingAs($admin)->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->getJson(p('dashboard'))->assertNotFound();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])->getJson(p('dashboard'))->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::1'])->getJson(p('dashboard'))->assertOk();

    // Off (empty): everyone with a Super Admin account.
    config(['menu.super_admin.ips' => []]);
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->getJson(p('dashboard'))->assertOk();
});

it('still asks a Super Admin to set up two-factor sign-in first', function () {
    $this->actingAs(superAdmin(twoFactor: false))->getJson(p('dashboard'))
        ->assertForbidden()->assertJsonPath('code', 'platform.two_factor_required');
});

it('writes every Super Admin change and download to the activity log, once', function () {
    $admin = superAdmin();
    $this->actingAs($admin);
    $before = ActivityLog::count();

    // A screen that logs nothing itself: one generic entry.
    $this->putJson(p('campaigns/rate'), ['campaign_rate' => 30], idem())->assertSuccessful();
    $entry = ActivityLog::latest('id')->first();
    expect(ActivityLog::count())->toBe($before + 1)
        ->and($entry->action)->toBe('super.request')
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->changes)->toMatchArray(['method' => 'PUT', 'route' => 'api/v1/super/campaigns/rate']);

    // A screen with its own entry: no second one.
    $this->deleteJson(p('settings/mail'), [], idem())->assertSuccessful();
    expect(ActivityLog::latest('id')->value('action'))->toBe('platform.mail_settings_cleared')
        ->and(ActivityLog::where('action', 'super.request')->count())->toBe(1);

    // Downloads count, reading a screen doesn't, failed requests don't.
    $this->get(p('payments.csv'))->assertOk();
    $this->getJson(p('restaurants'))->assertOk();
    $this->deleteJson(p('tags/999999'), [], idem())->assertNotFound();
    expect(ActivityLog::where('action', 'super.request')->get()->map(fn ($l) => $l->changes['route'])->all())
        ->toBe(['api/v1/super/campaigns/rate', 'api/v1/super/payments.csv']);
});

it('does not record starting "Log in as" (owner, QUESTIONS #170)', function () {
    [, $tenant] = member(Role::Owner);
    browser();
    $this->actingAs(superAdmin())->postJson(p("restaurants/{$tenant->id}/impersonate"), [], idem())->assertOk();
    expect(ActivityLog::count())->toBe(0);
});

it('limits Super Admin requests more strictly than restaurant ones', function () {
    $admin = superAdmin();
    [$owner, $tenant] = member(Role::Owner);
    for ($i = 0; $i < 240; $i++) {
        RateLimiter::hit(md5('super'.'super:'.$admin->id));
    }
    $this->actingAs($admin)->getJson(p('dashboard'))->assertTooManyRequests();

    for ($i = 0; $i < 240; $i++) {
        RateLimiter::hit(md5('restaurant'.'restaurant:'.$owner->id));
    }
    $this->actingAs($owner)->getJson(t($tenant))->assertOk();
});

it('never gives a menu the Super Admin address', function () {
    config(['menu.super_admin.path' => 'office-7k2q']);
    [$owner, $tenant] = member(Role::Owner);
    $this->actingAs($owner)->patchJson(t($tenant, 'account/slug'), ['slug' => 'office-7k2q'], idem())
        ->assertUnprocessable()->assertJsonValidationErrors('slug');
    $this->patchJson(t($tenant, 'account/slug'), ['slug' => 'super'], idem())->assertUnprocessable();
});

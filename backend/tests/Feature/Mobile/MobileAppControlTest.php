<?php

use App\Enums\Role;
use App\Jobs\SendPush;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\MobileApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/** Super Admin → Mobile app (owner, 2026-11-14, QUESTIONS #193). */
function appToken(User $user, string $version = '1.0.0'): string
{
    $new = $user->createToken('Pixel 8', ['mobile']);
    $new->accessToken->forceFill(['platform' => 'android', 'app_version' => $version])->save();

    return $new->plainTextToken;
}

function appHeaders(string $token, string $version = '1.0.0'): array
{
    return [...bearer($token), 'X-App-Version' => $version];
}

it('serves the full app config, with safe defaults', function () {
    $this->getJson('/api/v1/mobile/config')->assertOk()
        ->assertJsonPath('data.sign_in', ['restaurant' => true, 'super_admin' => true, 'message' => null])
        ->assertJsonPath('data.maintenance.on', false)
        ->assertJsonPath('data.versions.min', '1.0.0')
        ->assertJsonPath('data.banner', null)
        ->assertJsonPath('data.app_features.push', true)
        ->assertJsonPath('data.features.sold_out', true)
        ->assertJsonPath('data.links.web_admin', url('/admin'));
});

it('lets the Super Admin set everything, and validates it', function () {
    $this->actingAs(superAdmin());
    $this->putJson(p('mobile-app'), [
        'maintenance' => true, 'maintenance_message' => 'Back at 10:00',
        'latest_version' => '1.4.0', 'min_version' => '1.2.0', 'recommended_version' => '1.3.0', 'release_notes' => 'Faster menu',
        'banner' => ['text' => 'New: happy hour', 'link' => '/r/1/promotions', 'starts_at' => now()->subHour()->toIso8601String(), 'ends_at' => now()->addDay()->toIso8601String(), 'audience' => 'restaurant'],
        'app_features' => ['analytics' => false],
        'links' => ['play_store' => 'https://play.google.com/store/apps/details?id=com.kulmiss.menuadmin', 'support_whatsapp' => '+251 911 222 333'],
    ], idem())->assertOk()->assertJsonPath('data.app_features.analytics', false)->assertJsonPath('data.app_features.push', true);

    $this->getJson('/api/v1/mobile/config')->assertOk()
        ->assertJsonPath('data.maintenance', ['on' => true, 'message' => 'Back at 10:00'])
        ->assertJsonPath('data.versions', ['latest' => '1.4.0', 'min' => '1.2.0', 'recommended' => '1.3.0', 'notes' => 'Faster menu'])
        ->assertJsonPath('data.min_version', '1.2.0') // older apps
        ->assertJsonPath('data.banner.text', 'New: happy hour')->assertJsonPath('data.banner.audience', 'restaurant')
        ->assertJsonPath('data.flags.analytics', false)
        ->assertJsonPath('data.links.play_store', 'https://play.google.com/store/apps/details?id=com.kulmiss.menuadmin')
        ->assertJsonPath('data.links.support_whatsapp', 'https://wa.me/251911222333');

    $this->putJson(p('mobile-app'), ['min_version' => '2.0.0'], idem())->assertUnprocessable()->assertJsonValidationErrors('min_version');
    $this->putJson(p('mobile-app'), ['banner' => ['link' => 'javascript:alert(1)']], idem())->assertUnprocessable();
    $this->putJson(p('mobile-app'), ['latest_version' => 'one'], idem())->assertUnprocessable();
    $this->putJson(p('mobile-app'), ['app_features' => ['nope' => true]], idem())->assertUnprocessable();
});

it('shows the banner only between its dates', function () {
    MobileApp::save(['banner' => ['text' => 'Soon', 'starts_at' => now()->addDay()->toIso8601String()]]);
    $this->getJson('/api/v1/mobile/config')->assertJsonPath('data.banner', null);
    MobileApp::save(['banner' => ['text' => 'Over', 'starts_at' => null, 'ends_at' => now()->subMinute()->toIso8601String()]]);
    $this->getJson('/api/v1/mobile/config')->assertJsonPath('data.banner', null);
});

it('forces an update below the minimum version, from the server too', function () {
    [$owner] = member(Role::Owner);
    $token = appToken($owner, '1.0.0');
    MobileApp::save(['latest_version' => '1.3.0', 'min_version' => '1.2.0']);

    $this->app['auth']->forgetGuards(); // a new request: no remembered user
    $this->withHeaders(appHeaders($token, '1.1.9'))->getJson('/api/v1/me')->assertStatus(426)
        ->assertJsonPath('code', 'mobile.update_required')->assertJsonPath('min_version', '1.2.0');
    $this->app['auth']->forgetGuards(); // a new request: no remembered user
    $this->withHeaders(appHeaders($token, '1.2.0+7'))->getJson('/api/v1/me')->assertOk();
    expect(PersonalAccessToken::sole()->app_version)->toBe('1.2.0+7'); // Devices see the new version
    // The web admin (no app header) is never affected; the config always answers.
    $this->withHeaders(bearer($token))->getJson('/api/v1/me')->assertOk();
    $this->withHeaders(['X-App-Version' => '0.1.0'])->getJson('/api/v1/mobile/config')->assertOk();
});

it('puts the app in maintenance for everyone but Super Admins', function () {
    [$owner] = member(Role::Owner);
    $admin = superAdmin();
    MobileApp::save(['maintenance' => true, 'maintenance_message' => 'Back at 10:00']);

    $this->app['auth']->forgetGuards(); // a new request: no remembered user
    $this->withHeaders(appHeaders(appToken($owner)))->getJson('/api/v1/me')->assertStatus(503)
        ->assertJsonPath('code', 'mobile.maintenance')->assertJsonPath('message', 'Back at 10:00')->assertJsonPath('web', url('/admin'));
    $this->app['auth']->forgetGuards(); // a new request: no remembered user
    $this->withHeaders(appHeaders(appToken($admin)))->getJson('/api/v1/me')->assertOk();
    $this->postJson('/api/v1/mobile/auth/login', ['email' => $owner->email, 'password' => 'password', ...device()], ['X-App-Version' => '1.0.0'])
        ->assertStatus(503)->assertJsonPath('code', 'mobile.maintenance');
    expect(PersonalAccessToken::where('tokenable_id', $owner->id)->count())->toBe(1); // no new device
});

it('turns app sign-in off for restaurant users or Super Admins, but signing out still works', function () {
    [$owner] = member(Role::Owner);
    $admin = superAdmin();
    MobileApp::save(['restaurant_sign_in' => false, 'sign_in_message' => 'Use the website for now.']);

    $this->postJson('/api/v1/mobile/auth/login', ['email' => $owner->email, 'password' => 'password', ...device()])
        ->assertForbidden()->assertJsonPath('code', 'mobile.app_off')->assertJsonPath('message', 'Use the website for now.');
    $token = appToken($owner);
    $this->app['auth']->forgetGuards(); // a new request: no remembered user
    $this->withHeaders(appHeaders($token))->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('code', 'mobile.app_off');
    $this->app['auth']->forgetGuards(); // a new request: no remembered user
    $this->withHeaders(appHeaders(appToken($admin)))->getJson('/api/v1/me')->assertOk();
    $this->app['auth']->forgetGuards(); // a new request: no remembered user
    $this->withHeaders(appHeaders($token))->postJson('/api/v1/mobile/auth/logout', [], idem())->assertNoContent();

    MobileApp::save(['restaurant_sign_in' => true, 'super_admin_sign_in' => false]);
    $this->postJson('/api/v1/mobile/auth/login', ['email' => $admin->email, 'password' => 'password', ...device()])->assertForbidden();
    $this->postJson('/api/v1/mobile/auth/login', ['email' => $owner->email, 'password' => 'password', ...device()])->assertOk();
});

it('sends a push to filtered app users, with a history', function () {
    Queue::fake();
    [$owner, $tenant] = member(Role::Owner);
    [$staff] = member(Role::Staff, $tenant);
    [$other] = member(Role::Owner);
    $withApp = appToken($owner);
    appToken($staff);
    appToken($other);
    DB::table('push_tokens')->insert(['user_id' => $owner->id, 'personal_access_token_id' => PersonalAccessToken::where('tokenable_id', $owner->id)->value('id'),
        'token' => 'fcm-1', 'platform' => 'android', 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs(superAdmin());

    $body = ['title' => 'New feature', 'body' => 'Happy hour is here', 'link' => '/r/'.$tenant->id.'/promotions',
        'audience' => ['to' => 'restaurant', 'roles' => ['owner'], 'tenant_ids' => [$tenant->id]]];
    $this->postJson(p('mobile-app/pushes'), [...$body, 'dry_run' => true], idem())->assertOk()->assertJsonPath('data', ['recipients' => 1, 'with_app' => 1]);
    $this->postJson(p('mobile-app/pushes'), $body, idem())->assertCreated()->assertJsonPath('data.recipients', 1);
    $this->assertDatabaseHas('app_notifications', ['user_id' => $owner->id, 'title' => 'New feature', 'link' => '/r/'.$tenant->id.'/promotions']);
    $this->assertDatabaseMissing('app_notifications', ['user_id' => $staff->id]);
    Queue::assertPushed(SendPush::class, 1);
    $this->getJson(p('mobile-app/pushes'))->assertOk()->assertJsonPath('data.0.title', 'New feature')->assertJsonPath('data.0.recipients', 1);

    $this->postJson(p('mobile-app/pushes'), [...$body, 'audience' => ['to' => 'all']], idem())->assertCreated()->assertJsonPath('data.recipients', 3);
    $this->postJson(p('mobile-app/pushes'), [...$body, 'link' => 'http://evil.example'], idem())->assertUnprocessable();
    $this->postJson(p('mobile-app/pushes'), [...$body, 'audience' => ['to' => 'super_admin']], idem())->assertStatus(422); // nobody with the app
});

it('keeps pushes in the inbox only while app pushes are switched off', function () {
    Queue::fake();
    [$owner] = member(Role::Owner);
    appToken($owner);
    DB::table('push_tokens')->insert(['user_id' => $owner->id, 'token' => 'fcm-2', 'platform' => 'android', 'created_at' => now(), 'updated_at' => now()]);
    MobileApp::save(['app_features' => ['push' => false]]);
    $this->actingAs(superAdmin())->postJson(p('mobile-app/pushes'), ['title' => 'Hi', 'body' => 'There', 'audience' => ['to' => 'all']], idem())->assertCreated();
    Queue::assertNothingPushed();
    $this->assertDatabaseHas('app_notifications', ['user_id' => $owner->id, 'title' => 'Hi']);
});

it('lists app devices, signs one or all out, and counts them', function () {
    [$owner, $tenant] = member(Role::Owner);
    $tenant->update(['name' => 'Selam Cafe']);
    $t1 = appToken($owner, '1.1.0');
    appToken(superAdmin(), '1.2.0');
    $owner->createToken('web-ish'); // not an app device
    PersonalAccessToken::query()->update(['last_used_at' => now()]);
    $this->actingAs(superAdmin());

    $rows = $this->getJson(p('mobile-app/devices?page=1'))->assertOk()->json('data');
    expect($rows)->toHaveCount(2);
    $mine = collect($rows)->firstWhere('user.id', $owner->id);
    expect($mine['restaurants'])->toBe('Selam Cafe')->and($mine['app_version'])->toBe('1.1.0')->and($mine['platform'])->toBe('android');
    $this->getJson(p('mobile-app/devices?page=1&kind=super_admin'))->assertJsonCount(1, 'data');

    $stats = $this->getJson(p('mobile-app/stats'))->assertOk()->json('data');
    expect($stats['devices'])->toBe(2)->and($stats['active_30d'])->toBe(2)->and($stats['platforms'][0])->toBe(['key' => 'android', 'count' => 2])
        ->and($stats['push_opt_in'])->toBe(0);

    $this->deleteJson(p('mobile-app/devices/'.$mine['id']), [], idem())->assertOk();
    $this->app['auth']->forgetGuards(); // a new request: no remembered user
    $this->withHeaders(appHeaders($t1, '1.1.0'))->getJson('/api/v1/me')->assertUnauthorized();
    $this->app['auth']->forgetGuards();
    $this->actingAs(superAdmin());
    $this->postJson(p('mobile-app/devices/sign-out-all'), [], idem())->assertUnprocessable();
    $this->postJson(p('mobile-app/devices/sign-out-all'), ['confirm' => true], idem())->assertOk()->assertJsonPath('data.signed_out', 1);
    expect(PersonalAccessToken::where('abilities', 'like', '%mobile%')->count())->toBe(0);
});

it('is for Super Admins only', function () {
    [$owner] = member(Role::Owner);
    foreach (['mobile-app', 'mobile-app/pushes', 'mobile-app/devices?page=1', 'mobile-app/stats'] as $path) {
        $this->actingAs($owner)->getJson(p($path))->assertNotFound();
    }
});

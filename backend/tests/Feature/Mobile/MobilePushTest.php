<?php

use App\Enums\Role;
use App\Jobs\SendPush;
use App\Models\AppNotification;
use App\Models\PushToken;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Push\Push;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function fcmCredentials(): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $path = sys_get_temp_dir().'/fcm-test-'.uniqid().'.json';
    file_put_contents($path, json_encode(['type' => 'service_account', 'project_id' => 'menu-test', 'client_email' => 'push@menu-test.iam.gserviceaccount.com', 'private_key' => $pem]));
    config(['services.fcm.credentials' => $path]);

    return $path;
}

it('registers and removes this phone\'s push token, and moves a reused token to the new user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Phone', ['mobile']);
    $this->withHeaders(bearer($token->plainTextToken))->postJson('/api/v1/mobile/push-tokens', ['token' => 'fcm-abc', 'platform' => 'android'], idem())->assertNoContent();
    expect(PushToken::sole())->user_id->toBe($user->id)->personal_access_token_id->toBe($token->accessToken->id);

    $other = User::factory()->create();
    $this->app['auth']->forgetGuards();
    $this->withHeaders(bearer($other->createToken('Phone', ['mobile'])->plainTextToken))->postJson('/api/v1/mobile/push-tokens', ['token' => 'fcm-abc', 'platform' => 'android'], idem())->assertNoContent();
    expect(PushToken::sole()->user_id)->toBe($other->id);

    $this->deleteJson('/api/v1/mobile/push-tokens', ['token' => 'fcm-abc'], idem())->assertNoContent();
    expect(PushToken::count())->toBe(0);
});

it('removes the push token when the phone signs out', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Phone', ['mobile']);
    $this->withHeaders(bearer($token->plainTextToken))->postJson('/api/v1/mobile/push-tokens', ['token' => 'fcm-abc', 'platform' => 'android'], idem());
    $this->postJson('/api/v1/mobile/auth/logout', [], idem())->assertNoContent();
    expect(PushToken::count())->toBe(0);
});

it('shows the switches for my role and saves only what is off', function () {
    [$owner] = member(Role::Owner);
    $this->withHeaders(bearer($owner->createToken('Phone', ['mobile'])->plainTextToken));

    $types = collect($this->getJson('/api/v1/mobile/notification-settings')->assertOk()->json('data'))->pluck('type')->all();
    expect($types)->toContain('monthly_report')->not->toContain('signup_new');

    $this->putJson('/api/v1/mobile/notification-settings', ['settings' => ['monthly_report' => false]], idem())->assertOk()
        ->assertJsonPath('data.2.enabled', false);
    expect($owner->fresh()->push_settings)->toBe(['monthly_report' => false]);
    $this->putJson('/api/v1/mobile/notification-settings', ['settings' => ['signup_new' => false]], idem())->assertStatus(422);
});

it('keeps an inbox and marks notes read', function () {
    $user = User::factory()->create();
    Push::send($user, 'monthly_report', 'One', 'Body', '/r/1/insights', 1);
    Push::send($user, 'plan_expiring', 'Two', 'Body', '/r/1/billing', 1);
    $this->withHeaders(bearer($user->createToken('Phone', ['mobile'])->plainTextToken));

    $res = $this->getJson('/api/v1/mobile/notifications')->assertOk();
    expect($res->json('data.0.title'))->toBe('Two')->and($res->json('meta.unread'))->toBe(2);

    $this->postJson('/api/v1/mobile/notifications/read', ['ids' => [$res->json('data.0.id')]], idem())->assertNoContent();
    expect($this->getJson('/api/v1/mobile/notifications')->json('meta.unread'))->toBe(1);
    $this->postJson('/api/v1/mobile/notifications/read', ['all' => true], idem())->assertNoContent();
    expect($this->getJson('/api/v1/mobile/notifications')->json('meta.unread'))->toBe(0);
});

it('queues a phone push only for users with a phone and the type switched on', function () {
    Queue::fake();
    $on = User::factory()->create();
    $off = User::factory()->create(['push_settings' => ['monthly_report' => false]]);
    $none = User::factory()->create();
    PushToken::create(['user_id' => $on->id, 'token' => 't-on', 'platform' => 'android']);
    PushToken::create(['user_id' => $off->id, 'token' => 't-off', 'platform' => 'android']);

    Push::send([$on, $off, $none], 'monthly_report', 'Report', 'Ready', '/r/1/insights');

    expect(AppNotification::count())->toBe(3);
    Queue::assertPushed(SendPush::class, 1);
});

it('sends through FCM HTTP v1 and forgets tokens of removed apps', function () {
    $path = fcmCredentials();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::sequence()
            ->push(['name' => 'projects/menu-test/messages/1'])
            ->push(['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.']], 404),
    ]);
    $user = User::factory()->create();
    PushToken::create(['user_id' => $user->id, 'token' => 'good', 'platform' => 'android']);
    PushToken::create(['user_id' => $user->id, 'token' => 'gone', 'platform' => 'android']);

    Push::send($user, 'payment_recorded', 'Payment', 'Br 990', '/p/restaurants/5/payments');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'messages:send') && $r->hasHeader('Authorization', 'Bearer ya29.test')
        && $r['message']['data']['link'] === '/p/restaurants/5/payments' && $r['message']['notification']['title'] === 'Payment');
    Http::assertSent(function ($r) {
        if (! str_contains($r->url(), 'oauth2')) {
            return false;
        }
        [$h, $c] = array_map(fn ($p) => json_decode(base64_decode(strtr($p, '-_', '+/')), true), array_slice(explode('.', $r['assertion']), 0, 2));

        return $h['alg'] === 'RS256' && $c['scope'] === 'https://www.googleapis.com/auth/firebase.messaging';
    });
    expect(PushToken::pluck('token')->all())->toBe(['good']);
    unlink($path);
});

it('does nothing on the phone when Firebase is not set up, but the inbox has it', function () {
    config(['services.fcm.credentials' => null]);
    Http::fake();
    $user = User::factory()->create();
    PushToken::create(['user_id' => $user->id, 'token' => 'x', 'platform' => 'android']);

    Push::send($user, 'monthly_report', 'Report', 'Ready', '/r/1/insights');

    Http::assertNothingSent();
    expect(AppNotification::count())->toBe(1);
});

it('tells the other Super Admins about a recorded payment', function () {
    $admin = superAdmin();
    $other = superAdmin();
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)->postJson(p("restaurants/{$tenant->id}/payments"), ['amount_minor' => 99000, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'months' => 1], idem())->assertCreated();

    expect(AppNotification::where('type', 'payment_recorded')->pluck('user_id')->all())->toBe([$other->id])
        ->and(AppNotification::first()->link)->toBe("/p/restaurants/{$tenant->id}/payments");
});

it('reminds owners 7, 3 and 1 day before the plan ends and the day after', function () {
    [$owner, $t7] = member(Role::Owner, Tenant::factory()->create(['paid_until' => now()->addDays(7)->setTime(23, 59), 'trial_ends_at' => null]));
    [$owner2, $t5] = member(Role::Owner, Tenant::factory()->create(['paid_until' => now()->addDays(5), 'trial_ends_at' => null]));
    [$owner3, $tGone] = member(Role::Owner, Tenant::factory()->create(['paid_until' => now()->subDay(), 'trial_ends_at' => null]));

    Artisan::call('push:plan-reminders');

    expect(AppNotification::where('user_id', $owner->id)->value('type'))->toBe('plan_expiring')
        ->and(AppNotification::where('user_id', $owner2->id)->exists())->toBeFalse()
        ->and(AppNotification::where('user_id', $owner3->id)->value('type'))->toBe('plan_expired');
});

it('pushes follow-ups due and health problems to Super Admins, health at most every 6 hours', function () {
    $admin = superAdmin();
    Tenant::factory()->create(['follow_up_on' => now()->toDateString()]);
    Artisan::call('push:followups');
    expect(AppNotification::where('type', 'followups_due')->value('title'))->toBe('1 follow-up(s) due today');

    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subHour()->getTimestamp(), 'created_at' => now()->subHour()->getTimestamp()]);
    Artisan::call('push:health', ['--disk-free-percent' => 0]);
    Artisan::call('push:health', ['--disk-free-percent' => 0]);
    expect(AppNotification::where('user_id', $admin->id)->where('type', 'health_warning')->count())->toBe(1);
});

it('sends a new platform announcement to owners and managers of the restaurants it shows to', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$staff] = member(Role::Staff, $tenant);
    $this->actingAs(superAdmin())->postJson(p('announcements'), ['title' => ['en' => 'New styles'], 'body' => ['en' => 'Try style D.']], idem())->assertCreated();

    expect(AppNotification::where('type', 'platform_announcement')->pluck('user_id')->all())->toBe([$owner->id]);
});

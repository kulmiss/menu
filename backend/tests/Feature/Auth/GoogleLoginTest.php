<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SignupNotification;
use App\Support\PlatformSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use PragmaRX\Google2FA\Google2FA;

// "Continue with Google" on the website (owner, 2026-11-17).

function googleOn(): void
{
    config(['services.google.client_id' => 'web-client.apps.googleusercontent.com', 'services.google.client_secret' => 'secret']);
    PlatformSettings::save(['google_login' => true]);
}

/** Google answers the callback with this account (one mock per test, the answer can change). */
function googleReturns(array $raw): void
{
    $raw += ['sub' => 'g-123', 'email' => 'selam@example.com', 'email_verified' => true, 'name' => 'Selam Tesfaye'];
    $GLOBALS['googleRaw'] = $raw;
    if (! isset($GLOBALS['googleMocked'])) {
        $GLOBALS['googleMocked'] = true;
        Socialite::shouldReceive('driver->user')->andReturnUsing(function () {
            $raw = $GLOBALS['googleRaw'];

            return (new GoogleUser)->setRaw($raw)->map(['id' => $raw['sub'], 'email' => $raw['email'], 'name' => $raw['name']]);
        });
    }
}

function callback(): TestResponse
{
    return test()->withSession(['google.intent' => 'signin'])->get('/auth/google/callback?code=abc&state=xyz');
}

beforeEach(function () {
    unset($GLOBALS['googleRaw'], $GLOBALS['googleMocked']);
    Notification::fake();
    // The admin's own requests (cookie session, like the SPA).
    browser();
});

it('is off by default and when the keys are missing: no buttons, routes answer 404', function () {
    $this->get('/auth/google')->assertNotFound();
    $this->get('/auth/google/callback')->assertNotFound();
    $this->getJson('/api/v1/auth/google/pending')->assertNotFound();
    $this->getJson('/api/v1/signup/config')->assertJsonPath('data.google', false);
    $this->get('/')->assertDontSee('Continue with Google');

    // Switched on without GOOGLE_CLIENT_ID / SECRET: still off.
    PlatformSettings::save(['google_login' => true]);
    $this->get('/auth/google')->assertNotFound();

    googleOn();
    $this->getJson('/api/v1/signup/config')->assertJsonPath('data.google', true);
    $this->get('/')->assertSee('href="/auth/google" data-track="google"', false);
    // Sign-up off: the landing page shows "Contact us", not Google.
    PlatformSettings::save(['signup_open' => false]);
    $this->get('/')->assertDontSee('Continue with Google');
});

it('sends people to Google with a one-time state (CSRF) and only the basic scopes', function () {
    googleOn();
    $res = $this->get('/auth/google')->assertRedirect();
    $url = $res->headers->get('Location');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

    expect($url)->toStartWith('https://accounts.google.com/o/oauth2/auth?')
        ->and($q['client_id'])->toBe('web-client.apps.googleusercontent.com')
        ->and($q['redirect_uri'])->toBe(url('/auth/google/callback'))
        ->and(explode(' ', $q['scope']))->toEqualCanonicalizing(['openid', 'email', 'profile'])
        ->and($q['state'])->toHaveLength(40)
        ->and(session('state'))->toBe($q['state']);
});

it('refuses a callback whose state does not match', function () {
    googleOn();
    Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

    callback()->assertRedirect('/signin?google=failed');
    $this->assertGuest();
});

it('creates a new owner after the restaurant name and the Terms: Free plan, e-mail verified, no password', function () {
    googleOn();
    googleReturns([]);

    callback()->assertRedirect('/auth/google/finish');
    $this->assertGuest();
    expect(User::where('email', 'selam@example.com')->exists())->toBeFalse();

    $this->getJson('/api/v1/auth/google/pending')->assertOk()->assertJsonPath('data.email', 'selam@example.com')->assertJsonPath('data.name', 'Selam Tesfaye');
    $this->postJson('/api/v1/auth/google/finish', ['restaurant_name' => 'Buna & Bread'], idem())->assertUnprocessable()->assertJsonValidationErrors('terms');
    $this->postJson('/api/v1/auth/google/finish', ['restaurant_name' => 'Buna & Bread', 'terms' => true], idem())
        ->assertCreated()->assertJsonPath('data.redirect', '/admin');

    $user = User::where('email', 'selam@example.com')->firstOrFail();
    $tenant = Tenant::where('name', 'Buna & Bread')->firstOrFail();
    expect($user)->google_id->toBe('g-123')->google_email->toBe('selam@example.com')->password->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($tenant)->slug->toBe('buna-bread')->signup_state->toBeNull()
        ->and($tenant->plan?->slug)->toBe('free');
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, SignupNotification::class);
    $this->getJson('/api/v1/me')->assertJsonPath('data.has_password', false)->assertJsonPath('data.google.linked', true);
    // The waiting profile is used once.
    $this->getJson('/api/v1/auth/google/pending')->assertNotFound();
});

it('keeps a Google sign-up waiting for approval when the Super Admin approves sign-ups', function () {
    googleOn();
    PlatformSettings::save(['signup_approval' => true]);
    googleReturns([]);
    callback();
    $this->postJson('/api/v1/auth/google/finish', ['restaurant_name' => 'Kaldi', 'terms' => true], idem())->assertCreated();

    expect(Tenant::where('name', 'Kaldi')->value('signup_state'))->toBe(Tenant::SIGNUP_PENDING);
});

it('does not create accounts while sign-up is off', function () {
    googleOn();
    PlatformSettings::save(['signup_open' => false]);
    googleReturns([]);

    callback()->assertRedirect('/auth/sign-up?google=closed');
    $this->postJson('/api/v1/auth/google/finish', ['restaurant_name' => 'Kaldi', 'terms' => true], idem())->assertForbidden();
    expect(User::where('email', 'selam@example.com')->exists())->toBeFalse();
});

it('forgets a waiting Google sign-up after 15 minutes', function () {
    googleOn();
    googleReturns([]);
    callback();
    $this->travel(16)->minutes();

    $this->postJson('/api/v1/auth/google/finish', ['restaurant_name' => 'Kaldi', 'terms' => true], idem())
        ->assertStatus(410)->assertJsonPath('code', 'google.expired');
});

it('links an existing account with the same verified e-mail and signs it in', function () {
    googleOn();
    [$owner] = member(Role::Owner);
    googleReturns(['email' => strtoupper($owner->email)]);

    callback()->assertRedirect('/admin?google=linked');
    $this->assertAuthenticatedAs($owner);
    expect($owner->fresh())->google_id->toBe('g-123')
        ->and(ActivityLog::where('action', 'account.google_linked')->where('subject_id', $owner->id)->exists())->toBeTrue();

    // Next time: signed in by the Google account, without "linked".
    auth()->logout();
    googleReturns(['email' => 'changed-at-google@example.com']);
    callback()->assertRedirect('/admin');
    $this->assertAuthenticatedAs($owner);
});

it('confirms the e-mail of a fresh sign-up that continues with Google', function () {
    googleOn();
    $this->postJson('/api/v1/signup', ['name' => 'Selam', 'restaurant_name' => 'Kaldi', 'email' => 'selam@example.com', 'password' => 'correct-horse-battery', 'terms' => true], idem())->assertCreated();
    auth()->guard('web')->logout();
    googleReturns([]);

    callback()->assertRedirect('/admin?google=linked');
    expect(User::where('email', 'selam@example.com')->first()->hasVerifiedEmail())->toBeTrue()
        ->and(Tenant::where('name', 'Kaldi')->value('signup_state'))->toBeNull();
});

it('never links or signs in with an e-mail Google has not verified', function () {
    googleOn();
    [$owner] = member(Role::Owner);
    googleReturns(['email' => $owner->email, 'email_verified' => false]);

    callback()->assertRedirect('/signin?google=unverified');
    $this->assertGuest();
    expect($owner->fresh()->google_id)->toBeNull();
});

it('still asks two-factor users for their code', function () {
    googleOn();
    $secret = (new Google2FA)->generateSecretKey();
    [$owner] = member(Role::Owner);
    $owner->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_confirmed_at' => now(), 'google_id' => 'g-123'])->save();
    googleReturns([]);

    callback()->assertRedirect('/signin/two-factor')->assertSessionHas('url.intended', '/admin');
    $this->assertGuest();
    expect(session('login.id'))->toBe($owner->id);

    browser();
    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)], idem())->assertSuccessful();
    $this->assertAuthenticatedAs($owner);
});

it('never lets a Super Admin use Google', function () {
    googleOn();
    $admin = superAdmin();
    googleReturns(['email' => $admin->email]);

    callback()->assertRedirect('/signin?google=super_admin');
    $this->assertGuest();
    expect($admin->fresh()->google_id)->toBeNull();
    $this->actingAs($admin)->getJson('/api/v1/me')->assertJsonPath('data.google.available', false);
});

it('links and unlinks Google from Account → Security; no unlink without a password', function () {
    googleOn();
    [$owner] = member(Role::Owner);
    [$other] = member(Role::Owner);

    $this->actingAs($owner)->get('/auth/google?link=1')->assertRedirect();
    expect(session('google.intent'))->toBe('link');
    // A Google account whose e-mail belongs to someone else stays theirs.
    googleReturns(['email' => $other->email, 'sub' => 'g-other']);
    $this->actingAs($owner)->withSession(['google.intent' => 'link'])->get('/auth/google/callback?code=a&state=b')->assertRedirect('/admin/settings/security?google=taken');
    googleReturns(['email' => 'selam.private@gmail.com', 'sub' => 'g-mine']);
    $this->actingAs($owner)->withSession(['google.intent' => 'link'])->get('/auth/google/callback?code=a&state=b')->assertRedirect('/admin/settings/security?google=linked');
    expect($owner->fresh())->google_id->toBe('g-mine')->google_email->toBe('selam.private@gmail.com');
    $this->actingAs($owner)->getJson('/api/v1/me')->assertJsonPath('data.google', ['available' => true, 'linked' => true, 'email' => 'selam.private@gmail.com']);

    $this->actingAs($owner)->deleteJson('/api/v1/me/google', [], idem())->assertOk()->assertJsonPath('data.linked', false);
    expect($owner->fresh()->google_id)->toBeNull()
        ->and(ActivityLog::where('action', 'account.google_unlinked')->exists())->toBeTrue();
});

it('keeps Google linked on an account without a password (it could not sign in otherwise)', function () {
    googleOn();
    [$owner] = member(Role::Owner);
    $owner->forceFill(['google_id' => 'g-mine', 'password' => null])->save();

    $this->actingAs($owner->fresh())->deleteJson('/api/v1/me/google', [], idem())
        ->assertUnprocessable()->assertJsonValidationErrors(['google' => 'Set a password first']);
    expect($owner->fresh()->google_id)->toBe('g-mine');
});

it('lets the Super Admin switch Google sign-in on and see whether the keys are set', function () {
    $admin = superAdmin();
    $this->actingAs($admin)->getJson(p('settings'))->assertJsonPath('data.google_login', false)->assertJsonPath('data.google_configured', false);
    $this->actingAs($admin)->putJson(p('settings'), ['google_login' => true], idem())->assertOk()->assertJsonPath('data.google_login', true);
});

it('answers a normal "wrong password" for an account made with Google (no password yet), on the website and in the app', function () {
    $user = User::factory()->create(['password' => null, 'google_id' => 'g-1']);

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'anything-at-all'], idem())->assertUnprocessable();
    $this->postJson('/api/v1/mobile/auth/login', ['email' => $user->email, 'password' => 'anything-at-all', ...device()])->assertUnprocessable();
    $this->assertGuest();
});

it('e-mails a "set your password" link to a signed-in account made with Google', function () {
    Notification::fake();
    $user = User::factory()->create(['password' => null, 'google_id' => 'g-1']);
    $this->actingAs($user)->postJson('/api/v1/me/password-link', [], idem())->assertOk()->assertJsonPath('data.sent_to', $user->email);
    Notification::assertSentTo($user, ResetPassword::class);
});

it('sends no "set your password" link to an account that has a password', function () {
    [$owner] = member(Role::Owner);
    $this->actingAs($owner)->postJson('/api/v1/me/password-link', [], idem())->assertStatus(409);
});

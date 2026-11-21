<?php

use App\Enums\Role;
use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\GoogleIdToken;
use App\Support\PlatformSettings;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;

// "Continue with Google" in the Menu Admin app (owner, 2026-11-17).

const WEB_CLIENT = 'web-client.apps.googleusercontent.com';

/** A test signing key standing in for Google's, served at Google's key address. */
function googleKeys(): array
{
    static $key = null;
    if ($key === null) {
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $private);
        $d = openssl_pkey_get_details($res)['rsa'];
        $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $key = ['private' => $private, 'jwks' => ['keys' => [['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'test-kid', 'n' => $b64($d['n']), 'e' => $b64($d['e'])]]]];
    }

    return $key;
}

function idToken(array $claims = [], ?string $private = null): string
{
    return JWT::encode([
        'iss' => 'https://accounts.google.com', 'aud' => WEB_CLIENT, 'azp' => 'android-play.apps.googleusercontent.com',
        'sub' => 'g-123', 'email' => 'selam@example.com', 'email_verified' => true, 'name' => 'Selam Tesfaye',
        'iat' => time(), 'exp' => time() + 3600, ...$claims,
    ], $private ?? googleKeys()['private'], 'RS256', 'test-kid');
}

beforeEach(function () {
    Notification::fake();
    config(['services.google.client_id' => WEB_CLIENT, 'services.google.client_secret' => 'secret', 'services.google.android_client_ids' => []]);
    PlatformSettings::save(['google_login' => true]);
    Http::fake([GoogleIdToken::CERTS => Http::response(googleKeys()['jwks'], 200, ['Cache-Control' => 'public, max-age=3600'])]);
});

it('tells the app whether Google sign-in is on and which web client to ask Google for', function () {
    $this->getJson('/api/v1/mobile/config')->assertOk()
        ->assertJsonPath('data.google', ['enabled' => true, 'client_id' => WEB_CLIENT])
        // Everything the app already reads is still there.
        ->assertJsonStructure(['data' => ['name', 'min_version', 'sign_in', 'maintenance', 'features', 'links']]);

    PlatformSettings::save(['google_login' => false]);
    $this->getJson('/api/v1/mobile/config')->assertJsonPath('data.google', ['enabled' => false, 'client_id' => null]);
    $this->postJson('/api/v1/mobile/auth/google', ['id_token' => idToken(), ...device()])->assertForbidden()->assertJsonPath('code', 'google.off');
});

it('signs in an existing account with Google\'s ID token and links it ("Google is now linked")', function () {
    [$owner] = member(Role::Owner);
    $res = $this->postJson('/api/v1/mobile/auth/google', ['id_token' => idToken(['email' => $owner->email]), ...device()])
        ->assertOk()->assertJsonPath('user.id', $owner->id)->assertJsonPath('google_linked', true);

    expect($owner->fresh()->google_id)->toBe('g-123');
    // A normal app token: the app keeps it in its secure storage as after a password sign-in.
    $this->getJson('/api/v1/me', bearer($res->json('token')))->assertOk()->assertJsonPath('data.id', $owner->id);
    expect(PersonalAccessToken::where('tokenable_id', $owner->id)->value('platform'))->toBe('android');

    // Next time: no "linked".
    $this->postJson('/api/v1/mobile/auth/google', ['id_token' => idToken(), ...device()])->assertOk()->assertJsonMissingPath('google_linked');
    // Google's keys are fetched once and cached.
    Http::assertSentCount(1);
});

it('refuses tokens that are not from Google for this app', function (array $claims, ?string $why) {
    [$owner] = member(Role::Owner);
    $token = $why === 'signature'
        ? idToken(['email' => $owner->email], (function () {
            openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048]), $p);

            return $p;
        })())
        : idToken(['email' => $owner->email, ...$claims]);

    $this->postJson('/api/v1/mobile/auth/google', ['id_token' => $token, ...device()])
        ->assertUnprocessable()->assertJsonPath('code', 'google.invalid');
    expect($owner->fresh()->google_id)->toBeNull();
})->with([
    'other app (audience)' => [['aud' => 'someone-else.apps.googleusercontent.com'], null],
    'not Google (issuer)' => [['iss' => 'https://evil.example'], null],
    'expired' => [['exp' => time() - 3600, 'iat' => time() - 7200], null],
    'forged signature' => [[], 'signature'],
]);

it('accepts only the listed Android clients when they are set', function () {
    config(['services.google.android_client_ids' => ['android-debug.apps.googleusercontent.com']]);
    $this->postJson('/api/v1/mobile/auth/google', ['id_token' => idToken(), ...device()])->assertUnprocessable();
    $this->postJson('/api/v1/mobile/auth/google', ['id_token' => idToken(['azp' => 'android-debug.apps.googleusercontent.com']), ...device()])->assertOk();
});

it('never links an unverified Google e-mail and never signs in a Super Admin', function () {
    [$owner] = member(Role::Owner);
    $this->postJson('/api/v1/mobile/auth/google', ['id_token' => idToken(['email' => $owner->email, 'email_verified' => false]), ...device()])
        ->assertForbidden()->assertJsonPath('code', 'google.unverified');

    $admin = superAdmin();
    $this->postJson('/api/v1/mobile/auth/google', ['id_token' => idToken(['email' => $admin->email, 'sub' => 'g-admin']), ...device()])
        ->assertForbidden()->assertJsonPath('code', 'google.super_admin');
    expect($owner->fresh()->google_id)->toBeNull()->and($admin->fresh()->google_id)->toBeNull();
});

it('still asks two-factor users for their code', function () {
    $secret = (new Google2FA)->generateSecretKey();
    [$owner] = member(Role::Owner);
    $owner->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_confirmed_at' => now(), 'google_id' => 'g-123'])->save();

    $challenge = $this->postJson('/api/v1/mobile/auth/google', ['id_token' => idToken(), ...device()])
        ->assertOk()->assertJsonPath('two_factor', true)->assertJsonMissingPath('token')->json('challenge');
    $this->postJson('/api/v1/mobile/auth/two-factor', ['challenge' => $challenge, 'code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertOk()->assertJsonStructure(['token', 'user']);
});

it('creates a new owner after the restaurant name and the Terms, and returns the app token', function () {
    $ticket = $this->postJson('/api/v1/mobile/auth/google', ['id_token' => idToken(), ...device()])
        ->assertOk()->assertJsonPath('google_signup', true)->assertJsonPath('email', 'selam@example.com')->assertJsonMissingPath('token')->json('ticket');
    expect(User::where('email', 'selam@example.com')->exists())->toBeFalse();

    $this->postJson('/api/v1/mobile/auth/google/finish', ['ticket' => $ticket, 'restaurant_name' => 'Buna & Bread'])
        ->assertUnprocessable()->assertJsonValidationErrors('terms');
    $res = $this->postJson('/api/v1/mobile/auth/google/finish', ['ticket' => $ticket, 'restaurant_name' => 'Buna & Bread', 'terms' => true])
        ->assertOk()->assertJsonStructure(['token', 'user']);

    $user = User::where('email', 'selam@example.com')->firstOrFail();
    expect($user)->google_id->toBe('g-123')->password->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Tenant::where('name', 'Buna & Bread')->value('slug'))->toBe('buna-bread');
    $this->getJson('/api/v1/me', bearer($res->json('token')))->assertOk();
    // A ticket works once.
    $this->postJson('/api/v1/mobile/auth/google/finish', ['ticket' => $ticket, 'restaurant_name' => 'Again', 'terms' => true])
        ->assertStatus(410)->assertJsonPath('code', 'google.expired');
});

it('respects sign-up off and approval for new owners from the app', function () {
    PlatformSettings::save(['signup_open' => false]);
    $this->postJson('/api/v1/mobile/auth/google', ['id_token' => idToken(), ...device()])
        ->assertForbidden()->assertJsonPath('code', 'google.signup_closed');

    PlatformSettings::save(['signup_open' => true, 'signup_approval' => true]);
    $ticket = $this->postJson('/api/v1/mobile/auth/google', ['id_token' => idToken(), ...device()])->json('ticket');
    $this->postJson('/api/v1/mobile/auth/google/finish', ['ticket' => $ticket, 'restaurant_name' => 'Kaldi', 'terms' => true])->assertOk();
    expect(Tenant::where('name', 'Kaldi')->value('signup_state'))->toBe(Tenant::SIGNUP_PENDING);
});

it('forgets a ticket after 15 minutes', function () {
    $ticket = $this->postJson('/api/v1/mobile/auth/google', ['id_token' => idToken(), ...device()])->json('ticket');
    $this->travel(16)->minutes();

    $this->postJson('/api/v1/mobile/auth/google/finish', ['ticket' => $ticket, 'restaurant_name' => 'Kaldi', 'terms' => true])
        ->assertStatus(410);
});

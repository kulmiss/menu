<?php

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use PragmaRX\Google2FA\Google2FA;

function twoFactorUser(): User
{
    $g = new Google2FA;
    $secret = $g->generateSecretKey();

    return User::factory()->create([
        'password' => 'correct-horse-battery',
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['aaaa-bbbb', 'cccc-dddd'])),
        'two_factor_confirmed_at' => now(),
    ])->fresh();
}

it('signs in with e-mail and password and returns a token for this device', function () {
    $user = User::factory()->create(['email' => 'owner@example.com', 'password' => 'correct-horse-battery']);

    $res = $this->postJson('/api/v1/mobile/auth/login', ['email' => 'Owner@Example.com', 'password' => 'correct-horse-battery', ...device()])
        ->assertOk()->assertJsonPath('user.id', $user->id);

    $token = $res->json('token');
    expect($token)->toBeString()->not->toBeEmpty();
    $row = PersonalAccessToken::sole();
    expect($row)->name->toBe('Pixel 8')->platform->toBe('android')->app_version->toBe('1.0.0+1')
        ->and($row->expires_at->isAfter(now()->addDays(360)))->toBeTrue();

    $this->withHeaders(bearer($token))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
});

it('refuses a wrong password with the usual validation error', function () {
    User::factory()->create(['email' => 'owner@example.com', 'password' => 'correct-horse-battery']);

    $this->postJson('/api/v1/mobile/auth/login', ['email' => 'owner@example.com', 'password' => 'nope', ...device()])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    expect(PersonalAccessToken::count())->toBe(0);
});

it('rate-limits sign-in attempts per e-mail', function () {
    User::factory()->create(['email' => 'owner@example.com']);
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/mobile/auth/login', ['email' => 'owner@example.com', 'password' => 'nope', ...device()])->assertUnprocessable();
    }
    $this->postJson('/api/v1/mobile/auth/login', ['email' => 'owner@example.com', 'password' => 'nope', ...device()])->assertTooManyRequests();
});

it('asks for the two-factor code and gives the token only after it', function () {
    $user = twoFactorUser();

    $res = $this->postJson('/api/v1/mobile/auth/login', ['email' => $user->email, 'password' => 'correct-horse-battery', ...device()])
        ->assertOk()->assertJsonPath('two_factor', true)->assertJsonMissingPath('token');
    $challenge = $res->json('challenge');

    $this->postJson('/api/v1/mobile/auth/two-factor', ['challenge' => $challenge, 'code' => '000000'])->assertUnprocessable();
    $code = (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret));
    $token = $this->postJson('/api/v1/mobile/auth/two-factor', ['challenge' => $challenge, 'code' => $code])->assertOk()->json('token');

    expect($token)->toBeString();
    // The challenge works once only.
    $this->postJson('/api/v1/mobile/auth/two-factor', ['challenge' => $challenge, 'code' => $code])->assertStatus(410);
});

it('accepts a recovery code once and drops the challenge after five wrong codes', function () {
    $user = twoFactorUser();
    $login = fn () => $this->postJson('/api/v1/mobile/auth/login', ['email' => $user->email, 'password' => 'correct-horse-battery', ...device()])->json('challenge');

    $this->postJson('/api/v1/mobile/auth/two-factor', ['challenge' => $login(), 'recovery_code' => 'aaaa-bbbb'])->assertOk();
    expect($user->fresh()->recoveryCodes())->not->toContain('aaaa-bbbb');

    $challenge = $login();
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/mobile/auth/two-factor', ['challenge' => $challenge, 'code' => '111111'])->assertUnprocessable();
    }
    $this->travel(2)->minutes(); // past the rate limit, still inside the 5-minute challenge time
    $this->postJson('/api/v1/mobile/auth/two-factor', ['challenge' => $challenge, 'recovery_code' => 'cccc-dddd'])->assertStatus(410);
});

it('signs out this device or every device', function () {
    $user = User::factory()->create();
    $a = $user->createToken('A', ['mobile'])->plainTextToken;
    $b = $user->createToken('B', ['mobile'])->plainTextToken;
    $c = $user->createToken('C', ['mobile'])->plainTextToken;

    $this->withHeaders(bearer($a))->postJson('/api/v1/mobile/auth/logout', [], idem())->assertNoContent();
    expect($user->tokens()->count())->toBe(2);

    $this->app['auth']->forgetGuards();
    $this->withHeaders(bearer($b))->postJson('/api/v1/mobile/auth/logout-all', [], idem())->assertNoContent();
    expect($user->tokens()->count())->toBe(0);

    $this->app['auth']->forgetGuards();
    $this->withHeaders(bearer($c))->getJson('/api/v1/me')->assertUnauthorized();
});

it('stops a token that was not used for too long (60 days, Super Admin 14)', function () {
    $owner = User::factory()->create();
    $token = $owner->createToken('Old phone', ['mobile']);
    $token->accessToken->forceFill(['last_used_at' => now()->subDays(61)])->save();
    $this->withHeaders(bearer($token->plainTextToken))->getJson('/api/v1/me')->assertUnauthorized();

    $this->app['auth']->forgetGuards();
    $admin = superAdmin();
    $t2 = $admin->createToken('Tablet', ['mobile']);
    $t2->accessToken->forceFill(['last_used_at' => now()->subDays(15)])->save();
    $this->withHeaders(bearer($t2->plainTextToken))->getJson('/api/v1/me')->assertUnauthorized();

    $this->app['auth']->forgetGuards();
    $t3 = $owner->createToken('New phone', ['mobile']);
    $t3->accessToken->forceFill(['last_used_at' => now()->subDays(59)])->save();
    $this->withHeaders(bearer($t3->plainTextToken))->getJson('/api/v1/me')->assertOk();

    Artisan::call('mobile:prune-tokens');
    expect(PersonalAccessToken::pluck('name')->all())->toBe(['New phone']);
});

it('lists my devices and revokes one, never someone else\'s', function () {
    $user = User::factory()->create();
    $mine = $user->createToken('Pixel 8', ['mobile']);
    $other = $user->createToken('Galaxy Tab', ['mobile']);
    $stranger = User::factory()->create()->createToken('Not mine', ['mobile']);

    $list = $this->withHeaders(bearer($mine->plainTextToken))->getJson('/api/v1/mobile/devices')->assertOk()->json('data');
    expect(collect($list)->pluck('name')->sort()->values()->all())->toBe(['Galaxy Tab', 'Pixel 8'])
        ->and(collect($list)->firstWhere('name', 'Pixel 8')['current'])->toBeTrue();

    $this->deleteJson('/api/v1/mobile/devices/'.$stranger->accessToken->id, [], idem())->assertNotFound();
    $this->deleteJson('/api/v1/mobile/devices/'.$other->accessToken->id, [], idem())->assertNoContent();
    expect($user->tokens()->pluck('name')->all())->toBe(['Pixel 8']);
});

it('keeps the mobile-only routes away from the web session', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/v1/mobile/devices')->assertStatus(400)->assertJsonPath('code', 'mobile.token_required');
});

it('changes the password with the current one and signs out the other devices', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);
    $mine = $user->createToken('Phone', ['mobile'])->plainTextToken;
    $user->createToken('Tablet', ['mobile']);

    $this->withHeaders(bearer($mine))->putJson('/api/v1/mobile/account/password', ['current_password' => 'wrong', 'password' => 'another-long-secret', 'password_confirmation' => 'another-long-secret'], idem())
        ->assertUnprocessable()->assertJsonValidationErrors('current_password');
    $this->putJson('/api/v1/mobile/account/password', ['current_password' => 'correct-horse-battery', 'password' => 'another-long-secret', 'password_confirmation' => 'another-long-secret'], idem())
        ->assertNoContent();

    expect($user->tokens()->pluck('name')->all())->toBe(['Phone']);
});

it('updates the profile like the web does', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    $token = $user->createToken('Phone', ['mobile'])->plainTextToken;

    $this->withHeaders(bearer($token))->putJson('/api/v1/mobile/account/profile', ['name' => 'Selam T.', 'email' => 'new@example.com'], idem())
        ->assertOk()->assertJsonPath('data.email', 'new@example.com')->assertJsonPath('data.email_verified', false);
});

it('sets up two-factor with the password, a code, and never stores the recovery codes for replay', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);
    $token = $user->createToken('Phone', ['mobile'])->plainTextToken;
    $this->withHeaders(bearer($token));

    $this->postJson('/api/v1/mobile/account/two-factor', ['password' => 'wrong'], idem())->assertUnprocessable();
    $this->postJson('/api/v1/mobile/account/two-factor', ['password' => 'correct-horse-battery'], idem())->assertNoContent();
    $setup = $this->getJson('/api/v1/mobile/account/two-factor')->assertOk()->json('data');
    expect($setup)->toHaveKeys(['svg', 'url', 'secret']);

    $code = (new Google2FA)->getCurrentOtp($setup['secret']);
    $key = idem();
    $codes = $this->postJson('/api/v1/mobile/account/two-factor/confirm', ['code' => $code], $key)->assertOk()->json('data.recovery_codes');
    expect($codes)->toHaveCount(8)
        ->and(DB::table('idempotency_keys')->where('path', 'like', '%two-factor/confirm')->value('response_body'))->toBeNull();
    $this->postJson('/api/v1/mobile/account/two-factor/confirm', ['code' => $code], $key)->assertStatus(409)->assertJsonPath('code', 'idempotency.not_replayable');

    $this->getJson('/api/v1/mobile/account/two-factor')->assertStatus(409);
    $this->getJson('/api/v1/me')->assertJsonPath('data.two_factor_enabled', true);

    $this->deleteJson('/api/v1/mobile/account/two-factor', ['password' => 'correct-horse-battery'], idem())->assertNoContent();
    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

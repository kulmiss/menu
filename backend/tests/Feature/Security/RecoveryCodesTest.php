<?php

use App\Models\User;
use App\Support\Security\RecoveryCodes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/** SECURITY_REPORT.md M5: recovery codes stored as hashes, shown once, single use. */
function userWithTwoFactor(): array
{
    $user = User::factory()->create(['email' => 'twofa@example.com', 'password' => 'correct-horse-battery'])->fresh();
    test()->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    test()->postJson('/api/v1/auth/user/two-factor-authentication', [], idem())->assertOk();
    $code = (new Google2FA)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));
    test()->postJson('/api/v1/auth/user/confirmed-two-factor-authentication', ['code' => $code], idem())->assertOk();
    $codes = test()->getJson('/api/v1/auth/user/two-factor-recovery-codes')->assertOk()->json();
    app('auth')->forgetGuards();
    test()->flushSession();

    return [$user->fresh(), $codes];
}

it('stores only hashes: even with APP_KEY the database holds no usable code', function () {
    [$user, $codes] = userWithTwoFactor();

    expect($codes)->toHaveCount(8);
    $stored = Crypt::decryptString((string) DB::table('users')->where('id', $user->id)->value('two_factor_recovery_codes'));
    foreach ($codes as $c) {
        expect($stored)->not->toContain($c)->toContain(hash('sha256', $c));
    }
    expect(RecoveryCodes::remaining($user))->toBe(8);
});

it('shows the codes only for a few minutes after they are made', function () {
    [$user, $codes] = userWithTwoFactor();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time() + 3600]);
    expect($this->getJson('/api/v1/auth/user/two-factor-recovery-codes')->json())->toBe($codes);

    $this->travel(RecoveryCodes::SHOW_MINUTES + 1)->minutes();
    expect($this->getJson('/api/v1/auth/user/two-factor-recovery-codes')->json())->toBe([]);

    $this->withSession(['auth.password_confirmed_at' => time()])->postJson('/api/v1/auth/user/two-factor-recovery-codes', [], idem())->assertOk();
    $new = $this->getJson('/api/v1/auth/user/two-factor-recovery-codes')->json();
    expect($new)->toHaveCount(8)->not->toContain($codes[0]);
});

it('signs in once per code on the website, then the code is gone', function () {
    [$user, $codes] = userWithTwoFactor();

    $this->postJson('/api/v1/auth/login', ['email' => 'twofa@example.com', 'password' => 'correct-horse-battery'], idem())->assertOk()->assertJsonPath('two_factor', true);
    $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => 'not-a-code'], idem())->assertUnprocessable();
    $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => $codes[2]], idem())->assertSuccessful();
    $this->assertAuthenticatedAs($user);
    expect(RecoveryCodes::remaining($user->fresh()))->toBe(7);

    $this->postJson('/api/v1/auth/logout', [], idem());
    $this->app['auth']->forgetGuards();
    $this->postJson('/api/v1/auth/login', ['email' => 'twofa@example.com', 'password' => 'correct-horse-battery'], idem())->assertOk();
    $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => $codes[2]], idem())->assertUnprocessable();
});

it('works the same in the app', function () {
    [$user, $codes] = userWithTwoFactor();
    $challenge = $this->postJson('/api/v1/mobile/auth/login', ['email' => 'twofa@example.com', 'password' => 'correct-horse-battery', 'device_name' => 'Pixel', 'platform' => 'android'])
        ->assertOk()->json('challenge');

    $this->postJson('/api/v1/mobile/auth/two-factor', ['challenge' => $challenge, 'recovery_code' => $codes[0]])->assertOk()->assertJsonStructure(['token']);
    expect(RecoveryCodes::remaining($user->fresh()))->toBe(7)
        ->and(RecoveryCodes::match($user->fresh(), $codes[0]))->toBeNull()
        ->and(RecoveryCodes::match($user->fresh(), $codes[1]))->toBe($codes[1]);
});

it('turns codes saved before this change into hashes, and they still work', function () {
    $user = User::factory()->create(['email' => 'old@example.com', 'password' => 'correct-horse-battery', 'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()]);
    $legacy = ['aaaaaaaaaa-bbbbbbbbbb', 'cccccccccc-dddddddddd'];
    DB::table('users')->where('id', $user->id)->update(['two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($legacy))]);

    // Even before the migration ran, the old codes are checked by hash.
    expect(RecoveryCodes::match($user->fresh(), 'cccccccccc-dddddddddd'))->toBe('cccccccccc-dddddddddd');

    (require database_path('migrations/2026_11_16_100003_hash_two_factor_recovery_codes.php'))->up();

    $stored = Crypt::decryptString((string) DB::table('users')->where('id', $user->id)->value('two_factor_recovery_codes'));
    expect($stored)->not->toContain('aaaaaaaaaa')
        ->and(RecoveryCodes::match($user->fresh(), 'aaaaaaaaaa-bbbbbbbbbb'))->toBe('aaaaaaaaaa-bbbbbbbbbb')
        ->and(RecoveryCodes::remaining($user->fresh()))->toBe(2);
});

<?php

use App\Models\User;
use App\Rules\NotCommonPassword;

/** SECURITY_REPORT.md M3, owner's answer Q4: new passwords only; old weak ones get a banner. */
it('knows common, breached and look-alike passwords, and lets good ones through', function (string $password, bool $weak) {
    expect(NotCommonPassword::isWeak($password, 'selam.t@example.com'))->toBe($weak);
})->with([
    ['1234567890', true],
    ['qwertyuiop', true],
    ['Password123!', true],
    ['password2026', true],
    ['iloveyou12345', true],
    ['aaaaaaaaaaaa', true],
    ['abcdefghijk', true],
    ['selam.t@example.com', true],
    ['selam.t2026', true],
    ['correct-horse-battery', false],
    ['tef-buna-sunrise-42', false],
    ['M3nu!kulmiss-Bole', false],
]);

it('refuses a common password when choosing or changing one, everywhere', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    $this->actingAs($user)->putJson('/api/v1/auth/user/password', [
        'current_password' => 'correct-horse-battery', 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
    ], idem())->assertUnprocessable()->assertJsonValidationErrors('password');

    $token = $user->createToken('Phone', ['mobile'])->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->putJson('/api/v1/mobile/account/password', [
        'current_password' => 'correct-horse-battery', 'password' => 'qwertyuiop', 'password_confirmation' => 'qwertyuiop',
    ], idem())->assertUnprocessable()->assertJsonValidationErrors('password');

});

it('refuses a common password at sign-up', function () {
    $this->postJson('/api/v1/signup', [
        'name' => 'Selam', 'email' => 'new-owner@example.com', 'password' => '1234567890', 'password_confirmation' => '1234567890',
        'restaurant' => 'Buna', 'slug' => 'buna-x', 'terms' => true,
    ], idem())->assertUnprocessable()->assertJsonValidationErrors('password');
});

it('flags an existing weak password at sign-in (banner only) and clears it after a change', function () {
    // Set before the list existed: still accepted, never forced to change.
    $user = User::factory()->create(['email' => 'old@example.com', 'password' => '1234567890']);

    $this->postJson('/api/v1/auth/login', ['email' => 'old@example.com', 'password' => '1234567890'], idem())->assertOk();
    expect($user->fresh()->password_weak)->toBeTrue();
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.password_weak', true);

    $this->putJson('/api/v1/auth/user/password', [
        'current_password' => '1234567890', 'password' => 'tef-buna-sunrise-42', 'password_confirmation' => 'tef-buna-sunrise-42',
    ], idem())->assertOk();
    expect($user->fresh()->password_weak)->toBeFalse();

    // A good password signing in keeps the flag off; the app sign-in checks too.
    $this->app['auth']->forgetGuards();
    $other = User::factory()->create(['email' => 'app@example.com', 'password' => 'qwertyuiop']);
    $this->postJson('/api/v1/mobile/auth/login', ['email' => 'app@example.com', 'password' => 'qwertyuiop', 'device_name' => 'Pixel', 'platform' => 'android'])->assertOk();
    expect($other->fresh()->password_weak)->toBeTrue();
});

it('keeps the list local and small', function () {
    $file = resource_path('security/common-passwords.txt');
    $words = file($file, FILE_IGNORE_NEW_LINES);
    expect(filesize($file))->toBeLessThan(150_000)
        ->and(count($words))->toBeGreaterThan(9000)
        ->and(collect($words)->every(fn ($w) => strlen($w) >= 10 && $w === strtolower($w)))->toBeTrue();
});

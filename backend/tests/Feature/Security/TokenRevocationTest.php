<?php

use App\Models\PushToken;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/** SECURITY_REPORT.md H2: a new password signs the app out everywhere. */
function appSignIns(User $user): array
{
    $a = $user->createToken('Phone', ['mobile']);
    $b = $user->createToken('Tablet', ['mobile']);
    PushToken::create(['user_id' => $user->id, 'personal_access_token_id' => $a->accessToken->id, 'token' => 'fcm-a', 'platform' => 'android']);

    return [$a, $b];
}

it('revokes every app sign-in and push token after a password reset', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'reset@example.com']);
    $other = User::factory()->create();
    appSignIns($user);
    $kept = $other->createToken('Other user', ['mobile']);

    $token = Password::broker()->createToken($user);
    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token, 'email' => 'reset@example.com', 'password' => 'brand-new-secret-9', 'password_confirmation' => 'brand-new-secret-9',
    ], idem())->assertOk();

    expect($user->tokens()->count())->toBe(0)
        ->and(PushToken::where('user_id', $user->id)->count())->toBe(0)
        ->and($other->tokens()->whereKey($kept->accessToken->id)->exists())->toBeTrue();
});

it('revokes every app sign-in after a password change on the website', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);
    [$a] = appSignIns($user);

    $this->actingAs($user)->putJson('/api/v1/auth/user/password', [
        'current_password' => 'correct-horse-battery', 'password' => 'another-long-secret', 'password_confirmation' => 'another-long-secret',
    ], idem())->assertOk();

    expect($user->tokens()->count())->toBe(0);
    $this->app['auth']->forgetGuards();
    $this->withToken($a->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
});

it('keeps only the current device after a password change in the app', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);
    [$a, $b] = appSignIns($user);

    $this->withToken($a->plainTextToken)->putJson('/api/v1/mobile/account/password', [
        'current_password' => 'correct-horse-battery', 'password' => 'another-long-secret', 'password_confirmation' => 'another-long-secret',
    ], idem())->assertNoContent();

    expect($user->tokens()->pluck('id')->all())->toBe([$a->accessToken->id]);
});

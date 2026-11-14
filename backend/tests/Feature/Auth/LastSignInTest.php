<?php

use App\Enums\Role;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

/** Latest sign-in time (owner, 2026-11-13, QUESTIONS #187). */
it('records the latest sign-in, without touching updated_at', function () {
    [$owner] = member(Role::Owner);
    $owner->forceFill(['updated_at' => now()->subDays(3)])->save();
    browser();

    $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'password'], idem())->assertOk();

    $fresh = $owner->fresh();
    expect($fresh->last_login_at?->diffInSeconds(now()))->toBeLessThan(5)
        ->and($fresh->updated_at->isSameDay(now()->subDays(3)))->toBeTrue();
});

it('does not record a failed sign-in, and records a two-factor sign-in only after the code', function () {
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->create(['two_factor_secret' => encrypt($secret), 'two_factor_confirmed_at' => now()]);
    browser();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'], idem())->assertUnprocessable();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'], idem())->assertOk()->assertJsonPath('two_factor', true);
    expect($user->fresh()->last_login_at)->toBeNull();

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)], idem())->assertSuccessful();
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('does not count "log in as" or switching back as a sign-in', function () {
    [$owner, $tenant] = member(Role::Owner);
    $owner->forceFill(['last_login_at' => now()->subDays(20)])->save();
    $admin = superAdmin();
    $admin->forceFill(['last_login_at' => now()->subDays(2)])->save();

    browser();
    $this->actingAs($admin)->postJson(p("restaurants/{$tenant->id}/impersonate"), [], idem())->assertOk();
    $this->postJson('/api/v1/impersonation/stop', [], idem())->assertOk();

    expect($owner->fresh()->last_login_at->isSameDay(now()->subDays(20)))->toBeTrue()
        ->and($admin->fresh()->last_login_at->isSameDay(now()->subDays(2)))->toBeTrue();
});

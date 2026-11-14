<?php

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

it('updates the name without a password; a new e-mail waits for its link (see EmailChangeTest)', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->actingAs($user)->putJson('/api/v1/auth/user/profile-information', [
        'name' => 'Selam T.', 'email' => 'old@example.com', 'locale' => 'en',
    ], idem())->assertOk();

    expect($user->fresh())->name->toBe('Selam T.')->email->toBe('old@example.com')
        ->and($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('changes the password only with the current one', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    $this->actingAs($user)->putJson('/api/v1/auth/user/password', [
        'current_password' => 'wrong-password-123', 'password' => 'another-long-secret', 'password_confirmation' => 'another-long-secret',
    ], idem())->assertUnprocessable();
    $this->putJson('/api/v1/auth/user/password', [
        'current_password' => 'correct-horse-battery', 'password' => 'another-long-secret', 'password_confirmation' => 'another-long-secret',
    ], idem())->assertOk();
});

it('turns on two-factor sign-in after the password and a valid code', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery'])->fresh();
    $this->actingAs($user);

    $this->postJson('/api/v1/auth/user/two-factor-authentication', [], idem())->assertStatus(423); // password first
    $this->postJson('/api/v1/auth/user/confirm-password', ['password' => 'correct-horse-battery'], idem())->assertCreated();
    $this->postJson('/api/v1/auth/user/two-factor-authentication', [], idem())->assertOk();

    $this->getJson('/api/v1/auth/user/two-factor-qr-code')->assertOk()->assertJsonStructure(['svg', 'url']);
    $secret = $this->getJson('/api/v1/auth/user/two-factor-secret-key')->json('secretKey');
    $code = (new Google2FA)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));
    expect($secret)->not->toBeEmpty();

    $this->postJson('/api/v1/auth/user/confirmed-two-factor-authentication', ['code' => $code], idem())->assertOk();
    $this->getJson('/api/v1/me')->assertJsonPath('data.two_factor_enabled', true);
    expect($this->getJson('/api/v1/auth/user/two-factor-recovery-codes')->json())->toHaveCount(8);
});

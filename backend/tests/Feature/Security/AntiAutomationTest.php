<?php

use App\Enums\Role;
use App\Models\User;
use App\Support\Security\LoginCheck;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/** SECURITY_REPORT.md M1 (no user enumeration) and M2 (limits). */
it('answers "forgot password" the same for known, unknown and recently-sent addresses', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'known@example.com']);

    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@example.com'], idem())->assertOk();
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'], idem())->assertOk();
    $again = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@example.com'], idem())->assertOk();

    expect($unknown->json())->toBe($known->json())->and($again->json())->toBe($known->json())
        ->and($known->json('message'))->toContain('If an account uses this e-mail address');
    Notification::assertSentToTimes($user, ResetPassword::class, 1);
});

it('limits forgot-password per address and per e-mail', function () {
    Notification::fake();
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => "a{$i}@example.com"], idem())->assertOk();
    }
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'a6@example.com'], idem())->assertStatus(429);

    foreach (range(1, 5) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.1.0.$i"])->postJson('/api/v1/auth/forgot-password', ['email' => 'same@example.com'], idem())->assertOk();
    }
    $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.99'])->postJson('/api/v1/auth/forgot-password', ['email' => 'same@example.com'], idem())->assertStatus(429);
    $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.1'])->postJson('/api/v1/auth/reset-password', ['email' => 'x@example.com', 'token' => 'x', 'password' => 'y'], idem())->assertUnprocessable();
});

it('checks a password hash even for an unknown e-mail (web and app), so timing tells nothing', function () {
    User::factory()->create(['email' => 'known@example.com', 'password' => 'correct-horse-battery']);

    $counter = new class(['rounds' => 4]) extends BcryptHasher
    {
        public int $checks = 0;

        public function check(#[SensitiveParameter] $value, $hashedValue, array $options = []): bool
        {
            $this->checks++;

            return parent::check($value, $hashedValue, $options);
        }
    };
    Hash::swap($counter);
    $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'whatever-123'], idem())->assertUnprocessable();
    $this->postJson('/api/v1/mobile/auth/login', ['email' => 'nobody@example.com', 'password' => 'whatever-123', 'device_name' => 'Pixel', 'platform' => 'android'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    expect($counter->checks)->toBe(2);
});

it('limits sign-in per account across addresses (spread-out guessing), on the web and in the app', function () {
    User::factory()->create(['email' => 'target@example.com', 'password' => 'correct-horse-battery']);

    foreach (range(1, LoginCheck::PER_ACCOUNT_HOUR) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.'.intdiv($i, 250).'.'.($i % 250)])
            ->postJson('/api/v1/auth/login', ['email' => 'target@example.com', 'password' => "guess-$i"], idem())->assertUnprocessable();
    }
    $this->withServerVariables(['REMOTE_ADDR' => '10.8.8.8'])
        ->postJson('/api/v1/auth/login', ['email' => 'Target@Example.com', 'password' => 'correct-horse-battery'], idem())->assertStatus(429);
    $this->withServerVariables(['REMOTE_ADDR' => '10.8.8.9'])
        ->postJson('/api/v1/mobile/auth/login', ['email' => 'target@example.com', 'password' => 'correct-horse-battery', 'device_name' => 'Pixel', 'platform' => 'android'])->assertStatus(429);
    // Another account is not affected, and the wait ends within the hour.
    User::factory()->create(['email' => 'other@example.com', 'password' => 'correct-horse-battery']);
    $this->withServerVariables(['REMOTE_ADDR' => '10.8.8.10'])
        ->postJson('/api/v1/auth/login', ['email' => 'other@example.com', 'password' => 'correct-horse-battery'], idem())->assertOk();
    // A right password doesn't count against the account.
    foreach (range(1, 3) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.7.7.'.$i])->postJson('/api/v1/mobile/auth/login', ['email' => 'other@example.com', 'password' => 'correct-horse-battery', 'device_name' => 'Pixel', 'platform' => 'android'])->assertOk();
    }
    $this->travel(61)->minutes();
    $this->withServerVariables(['REMOTE_ADDR' => '10.8.8.11'])
        ->postJson('/api/v1/mobile/auth/login', ['email' => 'target@example.com', 'password' => 'correct-horse-battery', 'device_name' => 'Pixel', 'platform' => 'android'])->assertOk();
});

it('limits heavy CSV and PDF downloads to 30 a minute per user', function () {
    [$owner, $tenant] = member(Role::Owner);
    $this->actingAs($owner);
    foreach (range(1, 30) as $i) {
        expect($this->get(t($tenant, 'print-materials.pdf'))->status())->not->toBe(429);
    }
    $this->get(t($tenant, 'print-materials.pdf'))->assertStatus(429);
});

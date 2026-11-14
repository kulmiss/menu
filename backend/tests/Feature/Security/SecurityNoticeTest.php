<?php

use App\Enums\Role;
use App\Models\User;
use App\Notifications\SecurityNotice;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FA\Google2FA;

/** SECURITY_REPORT.md M4: "was this you?" e-mails, without IP addresses. */
function noticeSent(User $user, string $type): void
{
    Notification::assertSentTo($user, SecurityNotice::class, fn (SecurityNotice $n) => $n->type === $type);
}

it('e-mails after a password change (web and app) and a reset', function () {
    Notification::fake();
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    $this->actingAs($user)->putJson('/api/v1/auth/user/password', [
        'current_password' => 'correct-horse-battery', 'password' => 'another-long-secret', 'password_confirmation' => 'another-long-secret',
    ], idem())->assertOk();
    noticeSent($user, 'password_changed');

    $token = $user->createToken('Phone', ['mobile'])->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->putJson('/api/v1/mobile/account/password', [
        'current_password' => 'another-long-secret', 'password' => 'third-long-secret-7', 'password_confirmation' => 'third-long-secret-7',
    ], idem())->assertNoContent();
    Notification::assertSentToTimes($user, SecurityNotice::class, 2);

    $this->app['auth']->forgetGuards();
    $this->withHeaders(['Authorization' => ''])->postJson('/api/v1/auth/reset-password', [
        'token' => Password::broker()->createToken($user), 'email' => $user->email, 'password' => 'fourth-long-secret', 'password_confirmation' => 'fourth-long-secret',
    ], idem())->assertOk();
    noticeSent($user, 'password_reset');
});

it('e-mails when two-factor goes on or off and when recovery codes are renewed', function () {
    Notification::fake();
    $user = User::factory()->create(['password' => 'correct-horse-battery'])->fresh();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $this->postJson('/api/v1/auth/user/two-factor-authentication', [], idem())->assertOk();
    Notification::assertNothingSent(); // not on until confirmed
    $code = (new Google2FA)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));
    $this->postJson('/api/v1/auth/user/confirmed-two-factor-authentication', ['code' => $code], idem())->assertOk();
    noticeSent($user, 'two_factor_on');

    $this->postJson('/api/v1/auth/user/two-factor-recovery-codes', [], idem())->assertOk();
    noticeSent($user, 'recovery_codes');

    $this->deleteJson('/api/v1/auth/user/two-factor-authentication', [], idem())->assertOk();
    noticeSent($user, 'two_factor_off');
});

it('e-mails the device name (never an IP) for a new app sign-in, but not for "log in as"', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'owner@example.com', 'password' => 'correct-horse-battery']);

    $this->postJson('/api/v1/mobile/auth/login', ['email' => 'owner@example.com', 'password' => 'correct-horse-battery', 'device_name' => 'Pixel 8', 'platform' => 'android'])->assertOk();

    Notification::assertSentTo($user, SecurityNotice::class, function (SecurityNotice $n) use ($user) {
        $mail = $n->toMail($user);
        $text = implode(' ', [$mail->subject, ...$mail->introLines, ...$mail->outroLines]);

        return $n->type === 'new_device' && str_contains($text, 'Pixel 8') && str_contains($text, 'Android')
            && ! str_contains($text, '127.0.0.1');
    });

    [$owner, $tenant] = member(Role::Owner);
    $admin = superAdmin();
    $adminToken = $admin->createToken('Admin phone', ['mobile'])->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($adminToken)->postJson(p("restaurants/{$tenant->id}/impersonate-token"), [], idem())->assertOk();
    Notification::assertNotSentTo($owner, SecurityNotice::class);
});

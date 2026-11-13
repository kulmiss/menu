<?php

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OwnerWelcomeNotification;
use App\Support\PlatformSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/** New owners created by the Super Admin: welcome e-mail and "Resend set-password e-mail" (owner, 2026-11-12). */
function newRestaurant(): array
{
    $id = test()->actingAs(superAdmin())->postJson(p('restaurants'), [
        'name' => 'Sahan Grill', 'slug' => 'sahan-grill', 'owner_name' => 'Ayaan Warsame', 'owner_email' => 'ayaan@example.com',
    ], idem())->assertCreated()->json('data.id');

    // The owner opens the link signed out: forget the Super Admin's login.
    app('auth')->forgetGuards();

    return [Tenant::findOrFail($id), User::where('email', 'ayaan@example.com')->firstOrFail()];
}

it('welcomes a new owner with a friendly e-mail and a set-password link', function () {
    Notification::fake();
    PlatformSettings::save(['name' => 'MenuHub', 'support_email' => 'help@menuhub.example']);
    [$tenant, $owner] = newRestaurant();

    Notification::assertSentTo($owner, OwnerWelcomeNotification::class, function (OwnerWelcomeNotification $n) use ($owner, $tenant) {
        $mail = $n->toMail($owner);
        $text = implode("\n", [...$mail->introLines, ...$mail->outroLines]);
        expect($n->via($owner))->toBe(['mail'])
            ->and($n)->toBeInstanceOf(ShouldQueue::class)
            ->and($mail->subject)->toBe('Welcome to MenuHub — your menu for Sahan Grill is ready')
            ->and($mail->greeting)->toBe('Hello Ayaan Warsame,')
            ->and($mail->actionText)->toBe('Set my password')
            ->and($mail->actionUrl)->toContain('/auth/reset-password?token=')->toContain('email=ayaan%40example.com')->toContain('welcome=1')
            ->and($text)->toContain('/'.$tenant->slug)->toContain('Add your dishes')->toContain('QR codes')
            ->toContain('7 days')->toContain('help@menuhub.example')
            ->and($mail->salutation)->toBe('See you soon, the MenuHub team');

        return true;
    });
});

it('lets the owner set a password with the welcome link, even days later', function () {
    Notification::fake();
    [, $owner] = newRestaurant();
    $token = null;
    Notification::assertSentTo($owner, OwnerWelcomeNotification::class, function ($n) use (&$token) {
        $token = $n->token;

        return true;
    });

    $this->travel(3)->days();
    // A normal reset link would be expired after 60 minutes; without welcome=1 the token isn't even known.
    $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => $owner->email, 'password' => 'a-new-password-1', 'password_confirmation' => 'a-new-password-1'], idem())
        ->assertInvalid('email');
    $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => $owner->email, 'password' => 'a-new-password-1', 'password_confirmation' => 'a-new-password-1', 'welcome' => 1], idem())
        ->assertOk();
    expect(Hash::check('a-new-password-1', $owner->fresh()->password))->toBeTrue();

    $this->travel(8)->days();
    $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => $owner->email, 'password' => 'x-new-password-2', 'password_confirmation' => 'x-new-password-2', 'welcome' => 1], idem())
        ->assertInvalid('email'); // used once, and too old anyway
});

it('resends the set-password e-mail with a fresh link; the old one stops working', function () {
    Notification::fake();
    [$tenant, $owner] = newRestaurant();
    $tokens = [];
    $admin = superAdmin();

    $this->actingAs($admin)->postJson(p("restaurants/{$tenant->id}/resend-welcome"), [], idem())
        ->assertOk()->assertJsonPath('data.sent_to', 'ayaan@example.com')->assertJsonPath('data.kind', 'welcome');

    Notification::assertSentToTimes($owner, OwnerWelcomeNotification::class, 2);
    Notification::assertSentTo($owner, OwnerWelcomeNotification::class, function (OwnerWelcomeNotification $n) use (&$tokens, $owner) {
        $tokens[] = $n->token;
        if ($n->reminder) {
            expect($n->toMail($owner)->subject)->toBe('Your link to set up Sahan Grill on '.PlatformSettings::get('name'));
        }

        return true;
    });
    expect($tokens)->toHaveCount(2)->and($tokens[0])->not->toBe($tokens[1]);

    app('auth')->forgetGuards();
    $reset = fn (string $t) => $this->postJson('/api/v1/auth/reset-password', ['token' => $t, 'email' => $owner->email, 'password' => 'a-new-password-1', 'password_confirmation' => 'a-new-password-1', 'welcome' => 1], idem());
    $reset($tokens[0])->assertInvalid('email');
    $reset($tokens[1])->assertOk();
    $this->assertDatabaseHas('activity_log', ['action' => 'platform.welcome_resent', 'subject_id' => $tenant->id]);
});

it('keeps resending to the Super Admin', function () {
    Notification::fake();
    [$tenant, $owner] = newRestaurant();

    $this->actingAs($owner)->postJson(p("restaurants/{$tenant->id}/resend-welcome"), [], idem())->assertNotFound();
    $empty = Tenant::factory()->create();
    $this->actingAs(superAdmin())->postJson(p("restaurants/{$empty->id}/resend-welcome"), [], idem())->assertStatus(422);
    Notification::assertSentToTimes($owner, OwnerWelcomeNotification::class, 1);
});

it('sends a normal password reset, not "choose your password", to an owner who has signed in before (owner, 2026-11-14)', function () {
    Notification::fake();
    [$tenant, $owner] = newRestaurant();
    $owner->forceFill(['last_login_at' => now()->subDay()])->save();

    $this->actingAs(superAdmin())->postJson(p("restaurants/{$tenant->id}/resend-welcome"), [], idem())
        ->assertOk()->assertJsonPath('data.kind', 'reset')->assertJsonPath('message', fn ($m) => str_contains($m, 'reset your password'));

    Notification::assertSentTo($owner, Illuminate\Auth\Notifications\ResetPassword::class);
    Notification::assertSentToTimes($owner, OwnerWelcomeNotification::class, 1); // only the first welcome, when the restaurant was made
    $this->assertDatabaseHas('activity_log', ['action' => 'platform.password_reset_sent', 'subject_id' => $tenant->id]);

    // A second click within a minute: the reset broker's own throttle.
    $this->postJson(p("restaurants/{$tenant->id}/resend-welcome"), [], idem())->assertStatus(429);
});

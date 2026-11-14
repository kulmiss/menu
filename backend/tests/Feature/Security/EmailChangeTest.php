<?php

use App\Models\User;
use App\Notifications\EmailChangeConfirm;
use App\Notifications\EmailChangeNotice;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/** SECURITY_REPORT.md H3: password, link to the NEW address, notice to the OLD one. */
function changeEmail(User $user, array $extra = [])
{
    return test()->actingAs($user)->putJson('/api/v1/auth/user/profile-information', [
        'name' => $user->name, 'email' => 'new@example.com', 'locale' => 'en', ...$extra,
    ], idem());
}

it('refuses an e-mail change without the right current password', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.com', 'password' => 'correct-horse-battery']);

    changeEmail($user)->assertUnprocessable()->assertJsonValidationErrors('current_password');
    changeEmail($user, ['current_password' => 'wrong-password'])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    expect($user->fresh())->email->toBe('old@example.com')->pending_email->toBeNull();
    Notification::assertNothingSent();
});

it('keeps the old address until the link sent to the new one is clicked, and warns the old one', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.com', 'password' => 'correct-horse-battery']);

    changeEmail($user, ['current_password' => 'correct-horse-battery'])->assertOk();

    expect($user->fresh())->email->toBe('old@example.com')->pending_email->toBe('new@example.com')
        ->and($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Notification::assertSentTo($user, EmailChangeNotice::class, fn ($n) => $n->newEmail === 'new@example.com');
    $link = null;
    Notification::assertSentOnDemand(EmailChangeConfirm::class, function ($n, $channels, AnonymousNotifiable $to) use (&$link) {
        $link = $n->toMail($to)->actionUrl;

        return $to->routes['mail'] === 'new@example.com';
    });
    $this->app['auth']->forgetGuards();
    $this->actingAs($user->fresh())->getJson('/api/v1/me')->assertJsonPath('data.pending_email', 'new@example.com');

    $this->app['auth']->forgetGuards();
    $this->get($link)->assertRedirect('/auth/sign-in?verify=change_done');
    expect($user->fresh())->email->toBe('new@example.com')->pending_email->toBeNull()
        ->and($user->fresh()->hasVerifiedEmail())->toBeTrue();

    // Single use.
    $this->get($link)->assertRedirect('/auth/sign-in?verify=change_invalid');
});

it('rejects expired or tampered links, and a link made stale by a newer change', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.com', 'password' => 'correct-horse-battery']);
    changeEmail($user, ['current_password' => 'correct-horse-battery'])->assertOk();
    $first = EmailChangeConfirm::url($user->id, 'new@example.com');

    $this->get($first.'x')->assertRedirect('/auth/sign-in?verify=change_expired');
    $this->travel(61)->minutes();
    $this->get($first)->assertRedirect('/auth/sign-in?verify=change_expired');
    $this->travelBack();

    $this->actingAs($user)->putJson('/api/v1/auth/user/profile-information', [
        'name' => $user->name, 'email' => 'other@example.com', 'current_password' => 'correct-horse-battery',
    ], idem())->assertOk();
    $this->get(EmailChangeConfirm::url($user->id, 'new@example.com'))->assertRedirect('/auth/sign-in?verify=change_invalid');
    expect($user->fresh()->email)->toBe('old@example.com');
});

it('cancels a pending change when the old address is saved again, and refuses a taken address at confirm time', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.com', 'password' => 'correct-horse-battery']);
    changeEmail($user, ['current_password' => 'correct-horse-battery'])->assertOk();
    $link = EmailChangeConfirm::url($user->id, 'new@example.com');

    User::factory()->create(['email' => 'new@example.com']);
    $this->get($link)->assertRedirect('/auth/sign-in?verify=change_invalid');
    expect($user->fresh())->email->toBe('old@example.com')->pending_email->toBeNull();

    $user->forceFill(['pending_email' => 'later@example.com'])->save();
    $this->actingAs($user)->putJson('/api/v1/auth/user/profile-information', ['name' => 'Selam', 'email' => 'OLD@example.com'], idem())->assertOk();
    expect($user->fresh())->pending_email->toBeNull()->name->toBe('Selam');
});

<?php

use App\Enums\Role;
use App\Models\Item;
use App\Notifications\WelcomeSeriesNotification;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Notification;

/*
 * Welcome e-mails on day 1, 3 and 7 (owner, 2026-11-19, QUESTIONS #235): behind
 * a switch, each once, skipped when already done, never late, with a way out.
 */
function newRestaurant(): array
{
    [$owner, $tenant] = member(Role::Owner);
    $owner->forceFill(['email_verified_at' => now()])->save();
    $tenant->forceFill(['created_at' => now()])->saveQuietly();

    return [$owner, $tenant->fresh()];
}

it('sends nothing while switched off', function () {
    Notification::fake();
    [$owner] = newRestaurant();
    $this->travel(1)->days();
    $this->artisan('onboarding:emails')->expectsOutputToContain('switched off')->assertExitCode(0);
    Notification::assertNothingSent();
});

it('sends day 1, 3 and 7 once each, in order, to confirmed owners', function () {
    Notification::fake();
    PlatformSettings::save(['welcome_series' => true]);
    [$owner, $tenant] = newRestaurant();
    [$manager] = member(Role::Manager, $tenant);

    $this->artisan('onboarding:emails');
    Notification::assertNothingSent();                       // day 0: the normal welcome e-mail only

    foreach ([1 => 1, 3 => 2, 7 => 3] as $day => $step) {
        $this->travelTo($tenant->created_at->copy()->addDays($day)->addHour());
        $this->artisan('onboarding:emails');
        $this->artisan('onboarding:emails');                 // a second run the same day sends nothing more
        Notification::assertSentToTimes($owner, WelcomeSeriesNotification::class, $step);
        Notification::assertSentTo($owner, WelcomeSeriesNotification::class, fn ($n) => $n->step === $step);
    }
    Notification::assertNotSentTo($manager, WelcomeSeriesNotification::class);
    expect($tenant->fresh()->onboarding_step)->toBe(3);

    $this->travel(5)->days();
    $this->artisan('onboarding:emails');
    Notification::assertSentToTimes($owner, WelcomeSeriesNotification::class, 3);
});

it('skips a tip that is already done, and never sends old tips', function () {
    Notification::fake();
    PlatformSettings::save(['welcome_series' => true]);
    [$owner, $tenant] = newRestaurant();
    [, $category] = sampleMenu($tenant);
    asTenant($tenant, fn () => collect(range(1, 4))->each(fn ($n) => Item::create(['category_id' => $category->id, 'name' => ['en' => "D{$n}"], 'price_minor' => 100])));
    $tenant->markQrDownloaded();

    $this->travelTo($tenant->created_at->copy()->addDays(1)->addHour());
    $this->artisan('onboarding:emails');
    $this->travelTo($tenant->created_at->copy()->addDays(3)->addHour());
    $this->artisan('onboarding:emails');
    Notification::assertNothingSent();                       // 5 dishes and a QR code already

    // Switched on late: a restaurant 6 days old gets no day 1 or day 3 tips.
    [$late, $old] = newRestaurant();
    $old->forceFill(['created_at' => now()->subDays(6)])->saveQuietly();
    $this->artisan('onboarding:emails');
    Notification::assertNotSentTo($late, WelcomeSeriesNotification::class);
});

it('respects "no news e-mails" and has a one-click way out', function () {
    PlatformSettings::save(['welcome_series' => true]);
    [$owner, $tenant] = newRestaurant();
    $owner->forceFill(['news_unsubscribed_at' => now()])->save();
    Notification::fake();
    $this->travelTo($tenant->created_at->copy()->addDays(1)->addHour());
    $this->artisan('onboarding:emails');
    Notification::assertNothingSent();

    $mail = (new WelcomeSeriesNotification($tenant, 2))->toMail($owner);
    expect($mail->subject)->toBe("Put {$tenant->name} on the tables")
        ->and($mail->actionUrl)->toBe(url('/admin/print-materials'))
        ->and(implode(' ', $mail->outroLines))->toContain('/news/'.$owner->id.'/unsubscribe');
});

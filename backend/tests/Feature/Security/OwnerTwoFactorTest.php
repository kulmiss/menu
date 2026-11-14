<?php

use App\Enums\Role;
use App\Models\User;
use App\Notifications\TwoFactorReminder;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Notification;

/** Owner's answer to Q1 / SECURITY_REPORT.md M17: encourage; the Super Admin switch can require it (off). */
it('keeps two-factor optional for Owners by default', function () {
    [$owner, $tenant] = member(Role::Owner);

    $this->actingAs($owner)->getJson('/api/v1/me')->assertJsonPath('data.two_factor_required', false);
    $this->getJson(t($tenant, 'items'))->assertOk();
    expect(PlatformSettings::get('owner_two_factor_required'))->toBeFalse();
});

it('lets the Super Admin require it: an Owner without it reaches only the setup screens', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$manager] = member(Role::Manager, $tenant);

    $this->actingAs(superAdmin())->putJson(p('settings'), ['owner_two_factor_required' => true], idem())->assertOk()
        ->assertJsonPath('data.owner_two_factor_required', true);
    $this->app['auth']->forgetGuards();

    $this->actingAs($owner)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.two_factor_required', true);
    $this->getJson(t($tenant, 'items'))->assertForbidden()->assertJsonPath('code', 'two_factor.required');
    // Account routes (where two-factor is set up) stay open.
    $this->putJson('/api/v1/me/news', ['subscribed' => true], idem())->assertSuccessful();

    // Managers and staff stay optional.
    $this->app['auth']->forgetGuards();
    $this->actingAs($manager)->getJson(t($tenant, 'items'))->assertOk();

    // With two-factor on, the Owner is back in.
    $owner->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();
    $this->app['auth']->forgetGuards();
    $this->actingAs($owner->fresh())->getJson(t($tenant, 'items'))->assertOk();
});

it('sends one reminder 7 days after sign-up to Owners without two-factor', function () {
    Notification::fake();
    [$owner] = member(Role::Owner);
    [$owner2] = member(Role::Owner);
    [$staff] = member(Role::Staff);
    $owner->forceFill(['created_at' => now()->subDays(8)])->save();
    $staff->forceFill(['created_at' => now()->subDays(8)])->save();
    $owner2->forceFill(['created_at' => now()->subDays(3)])->save();
    $protected = User::factory()->create(['created_at' => now()->subDays(30), 'two_factor_confirmed_at' => now()]);

    $this->artisan('security:two-factor-reminders')->assertSuccessful();
    $this->artisan('security:two-factor-reminders')->assertSuccessful();

    Notification::assertSentToTimes($owner, TwoFactorReminder::class, 1);
    Notification::assertNotSentTo([$owner2, $staff, $protected], TwoFactorReminder::class);
    $mail = (new TwoFactorReminder)->toMail($owner);
    expect($mail->actionUrl)->toEndWith('/admin/settings/security?setup=1');
});

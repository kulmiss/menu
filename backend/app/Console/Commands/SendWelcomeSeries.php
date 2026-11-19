<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\WelcomeSeriesNotification;
use App\Support\PlatformSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Welcome e-mails after sign-up (owner, 2026-11-19, QUESTIONS #235), behind
 * Super Admin → Settings → Sign-up → "Welcome e-mails". Day 0 is the existing
 * welcome e-mail; then:
 *   day 1 "Add your dishes and photos" (skipped once there are 5 dishes),
 *   day 3 "Put the QR codes on your tables" (skipped once a QR code was downloaded),
 *   day 7 "Make it yours" (styles, Insights, help) — the last one.
 * Each at most once per restaurant, only to owners with a confirmed address who
 * did not switch news e-mails off, and only within 2 days of its day (a missed run
 * or a switch turned on later never sends old tips).
 */
#[Signature('onboarding:emails')]
#[Description('Send the day 1, 3 and 7 welcome e-mails to new restaurants (when switched on)')]
class SendWelcomeSeries extends Command
{
    public const STEPS = [1 => 1, 2 => 3, 3 => 7];

    public function handle(): int
    {
        if (! PlatformSettings::get('welcome_series')) {
            $this->line('Welcome e-mails are switched off.');

            return self::SUCCESS;
        }
        $sent = 0;
        Tenant::query()->whereNull('suspended_at')->where('onboarding_step', '<', count(self::STEPS))
            ->where('created_at', '>=', now()->subDays(max(self::STEPS) + 2))
            ->each(function (Tenant $tenant) use (&$sent) {
                foreach (self::STEPS as $step => $day) {
                    if ($tenant->onboarding_step >= $step) {
                        continue;
                    }
                    $age = $tenant->created_at->diffInHours(now(), true) / 24;
                    if ($age < $day) {
                        return; // not yet
                    }
                    // Moved past this step (sent or skipped) in any case, so it is never sent late.
                    $tenant->forceFill(['onboarding_step' => $step])->saveQuietly();
                    if ($age >= $day + 2 || $this->skip($tenant, $step)) {
                        continue;
                    }
                    foreach ($this->owners($tenant) as $owner) {
                        $owner->notify(new WelcomeSeriesNotification($tenant, $step));
                        $sent++;
                    }

                    return; // one e-mail per run
                }
            });
        $this->info("Sent {$sent} welcome e-mail(s).");

        return self::SUCCESS;
    }

    private function skip(Tenant $tenant, int $step): bool
    {
        return match ($step) {
            1 => Item::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count() >= 5,
            2 => $tenant->qr_downloaded_at !== null,
            default => false,
        };
    }

    /** @return iterable<User> */
    private function owners(Tenant $tenant): iterable
    {
        $ids = DB::table('memberships')->where('tenant_id', $tenant->id)->where('role', Role::Owner->value)->pluck('user_id');

        return User::whereIn('id', $ids)->whereNotNull('email_verified_at')->whereNull('news_unsubscribed_at')->get();
    }
}

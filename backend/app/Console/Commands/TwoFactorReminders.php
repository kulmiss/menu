<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\TwoFactorReminder;
use Illuminate\Console\Command;

/** Daily: one reminder to Owners who still have no two-factor 7 days after sign-up (M17). */
class TwoFactorReminders extends Command
{
    protected $signature = 'security:two-factor-reminders';

    protected $description = 'Remind restaurant owners once to turn on two-factor sign-in';

    public function handle(): int
    {
        $sent = 0;
        User::query()
            ->whereNull('two_factor_confirmed_at')->whereNull('two_factor_reminded_at')
            ->whereNotNull('email_verified_at')->where('is_super_admin', false)
            ->where('created_at', '<=', now()->subDays(7))
            ->whereHas('memberships', fn ($q) => $q->where('role', Role::Owner->value)->whereHas('tenant'))
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$sent) {
                foreach ($users as $user) {
                    $user->notify(new TwoFactorReminder);
                    $user->forceFill(['two_factor_reminded_at' => now()])->saveQuietly();
                    $sent++;
                }
            });
        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }
}

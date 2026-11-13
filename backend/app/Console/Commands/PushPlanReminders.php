<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Push\Push;
use Illuminate\Console\Command;

/**
 * Daily: owners hear on their phones 7, 3 and 1 day(s) before the paid time
 * (or trial) ends, and the day after it ended (QUESTIONS #189).
 */
class PushPlanReminders extends Command
{
    protected $signature = 'push:plan-reminders';

    protected $description = 'Push "plan ending soon / ended" to restaurant owners';

    public function handle(): int
    {
        $sent = 0;
        $today = now()->startOfDay();
        Tenant::query()->whereNull('signup_state')->where('status', Tenant::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNotNull('paid_until')->orWhereNotNull('trial_ends_at'))
            ->chunkById(500, function ($tenants) use ($today, &$sent) {
                foreach ($tenants as $tenant) {
                    $ends = collect([$tenant->paid_until, $tenant->trial_ends_at])->filter()->max();
                    $days = (int) $today->diffInDays($ends->copy()->startOfDay(), false);
                    if (in_array($days, [7, 3, 1], true)) {
                        Push::send(Push::team($tenant->id), 'plan_expiring',
                            __('push.plan_expiring.title', ['days' => $days]), __('push.plan_expiring.body', ['restaurant' => $tenant->name]),
                            "/r/{$tenant->id}/billing", $tenant->id);
                        $sent++;
                    } elseif ($days === -1) {
                        Push::send(Push::team($tenant->id), 'plan_expired',
                            __('push.plan_expired.title'), __('push.plan_expired.body', ['restaurant' => $tenant->name]),
                            "/r/{$tenant->id}/billing", $tenant->id);
                        $sent++;
                    }
                }
            });
        $this->info("Plan reminders: {$sent}");

        return self::SUCCESS;
    }
}

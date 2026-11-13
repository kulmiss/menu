<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MonthlyInsightsNotification;
use App\Support\Features;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Monthly Insights report (QUESTIONS #180). Runs every hour; on the 1st of
 * the month, from 07:00 in the restaurant's own time zone, it e-mails last
 * month's report to the owners of restaurants that switched it on and whose
 * plan includes Insights. Each month is sent once (insights_report_sent),
 * so a missed hour or a second run never sends twice.
 */
class SendMonthlyInsights extends Command
{
    protected $signature = 'insights:monthly';

    protected $description = 'E-mail last month\'s Insights report to owners who asked for it (on the 1st)';

    public function handle(): int
    {
        $sent = 0;
        Tenant::where('insights_report', true)->whereNull('suspended_at')->orderBy('id')
            ->each(function (Tenant $tenant) use (&$sent) {
                $now = CarbonImmutable::now($tenant->timezone);
                $month = $now->subMonthNoOverflow()->format('Y-m');
                // The 1st from 07:00, or any later day of the month if the server missed the 1st.
                if (($now->day === 1 && $now->hour < 7) || $tenant->insights_report_sent === $month || ! Features::enabled($tenant, 'insights')) {
                    return;
                }
                $owners = User::whereIn('id', DB::table('memberships')->where('tenant_id', $tenant->id)
                    ->where('role', Role::Owner->value)->pluck('user_id'))->get();
                $tenant->forceFill(['insights_report_sent' => $month])->save();
                if ($owners->isNotEmpty()) {
                    Notification::send($owners, new MonthlyInsightsNotification($tenant->id, $month));
                    $sent++;
                }
            });
        $this->info("Monthly reports sent: {$sent}");

        return self::SUCCESS;
    }
}

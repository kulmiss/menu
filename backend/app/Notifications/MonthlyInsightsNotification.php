<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Support\Insights\InsightsReport;
use App\Support\Insights\ReportPdf;
use App\Support\PlatformSettings;
use App\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Last month's Insights report as a PDF, e-mailed to the owners on the 1st (QUESTIONS #180). Queued. */
class MonthlyInsightsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $tenantId, public readonly string $month) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $from = CarbonImmutable::createFromFormat('!Y-m', $this->month, $tenant->timezone) ?: CarbonImmutable::now($tenant->timezone)->subMonthNoOverflow()->startOfMonth();
        $to = $from->endOfMonth()->startOfDay();
        [$report, $pdf] = app(CurrentTenant::class)->runAs($tenant, function () use ($tenant, $from, $to) {
            $report = InsightsReport::build($tenant, $from, $to);

            return [$report, ReportPdf::forTenant($tenant, $report)];
        });
        $month = $from->format('F Y');
        $platform = (string) PlatformSettings::get('name');

        return (new MailMessage)
            ->subject(__('insights.mail.subject', ['restaurant' => $tenant->name, 'month' => $month]))
            ->greeting(__('insights.mail.greeting', ['name' => $notifiable->name ?? '']))
            ->line(__('insights.mail.line', [
                'restaurant' => $tenant->name,
                'month' => $month,
                'views' => number_format($report['totals']['views']),
                'scans' => number_format($report['totals']['scans']),
                'adds' => number_format($report['totals']['order_adds']),
            ]))
            ->action(__('insights.mail.action'), url('/admin/insights'))
            ->line(__('insights.mail.off'))
            ->salutation(__('insights.mail.salutation', ['platform' => $platform]))
            ->attachData($pdf, ReportPdf::filename($tenant, $report), ['mime' => 'application/pdf']);
    }
}

<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a restaurant's owners when the Super Admin blocks, suspends, or lifts
 * either (owner, 2026-11-25). Texts in lang/en/restaurant_status.php. Queued;
 * the cron-run queue worker sends it. The reason is included for blocked and
 * suspended.
 */
class RestaurantStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $kind, // blocked | suspended | unblocked | unsuspended
        public readonly Tenant $tenant,
        public readonly ?string $reason = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = (string) PlatformSettings::get('name');
        $r = [
            'app' => $app,
            'restaurant' => $this->tenant->name,
            'name' => $notifiable->name ?? '',
            'reason' => (string) ($this->reason ?? ''),
            'url' => url('/'.$this->tenant->slug),
        ];
        $k = 'restaurant_status.mail.'.$this->kind.'.';

        $mail = (new MailMessage)
            ->subject(__($k.'subject', $r))
            ->greeting(__($r['name'] === '' ? 'restaurant_status.mail.hello' : 'restaurant_status.mail.hello_name', $r))
            ->line(__($k.'intro', $r));

        if ($this->reason !== null && $this->reason !== '' && in_array($this->kind, ['blocked', 'suspended'], true)) {
            $mail->line(__('restaurant_status.mail.reason', $r));
        }
        if (in_array($this->kind, ['unblocked', 'unsuspended'], true)) {
            $mail->action(__('restaurant_status.mail.open'), url('/admin'));
        }
        $mail->line(__($k.'outro', $r));

        $support = PlatformSettings::get('support_email');
        if (is_string($support) && $support !== '') {
            $mail->line(__('restaurant_status.mail.help', ['email' => $support]));
        }

        return $mail->salutation(__('restaurant_status.mail.signature', $r));
    }
}

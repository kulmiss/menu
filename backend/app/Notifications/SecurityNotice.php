<?php

namespace App\Notifications;

use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Was this you?" e-mails for account changes (SECURITY_REPORT.md M4):
 * password changed or reset, two-factor on or off, new recovery codes, a new
 * app sign-in. Device name only, never an IP address.
 */
class SecurityNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPES = ['password_changed', 'password_reset', 'two_factor_on', 'two_factor_off', 'recovery_codes', 'new_device'];

    /** @param  array<string, string>  $details */
    public function __construct(public readonly string $type, public readonly array $details = []) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = ['app' => (string) PlatformSettings::get('name'), 'name' => $notifiable->name ?? '', ...$this->details];

        return (new MailMessage)
            ->subject(__("security.notice.{$this->type}.subject", $r))
            ->greeting(__('signup.mail.hello_name', $r))
            ->line(__("security.notice.{$this->type}.line", $r))
            ->line(__('security.notice.not_you'))
            ->action(__('security.notice.action'), url('/admin/settings/security'))
            ->salutation(__('signup.mail.signature', $r));
    }
}

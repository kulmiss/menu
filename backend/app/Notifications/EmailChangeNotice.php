<?php

namespace App\Notifications;

use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the OLD address when a change is asked for (SECURITY_REPORT.md H3). */
class EmailChangeNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $newEmail, public readonly string $name) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = ['app' => (string) PlatformSettings::get('name'), 'name' => $this->name, 'email' => $this->newEmail];

        return (new MailMessage)
            ->subject(__('security.email_change.notice.subject', $r))
            ->greeting(__('signup.mail.hello_name', $r))
            ->line(__('security.email_change.notice.intro', $r))
            ->line(__('security.email_change.notice.keep'))
            ->action(__('security.email_change.notice.action'), url('/admin/settings/security'))
            ->line(__('security.email_change.notice.not_you'))
            ->salutation(__('signup.mail.signature', $r));
    }
}

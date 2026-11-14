<?php

namespace App\Notifications;

use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One reminder, 7 days after an Owner signed up without two-factor (SECURITY_REPORT.md M17). */
class TwoFactorReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = ['app' => (string) PlatformSettings::get('name'), 'name' => $notifiable->name ?? ''];

        return (new MailMessage)
            ->subject(__('security.two_factor_reminder.subject', $r))
            ->greeting(__('signup.mail.hello_name', $r))
            ->line(__('security.two_factor_reminder.intro', $r))
            ->line(__('security.two_factor_reminder.how'))
            ->action(__('security.two_factor_reminder.action'), url('/admin/settings/security?setup=1'))
            ->line(__('security.two_factor_reminder.once'))
            ->salutation(__('signup.mail.signature', $r));
    }
}

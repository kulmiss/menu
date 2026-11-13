<?php

namespace App\Notifications;

use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To every Super Admin when a backup failed or none succeeded in 26 hours. Sent at once (not queued). */
class BackupProblemNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $problem) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject(__('backups.mail.subject', ['platform' => PlatformSettings::get('name')]))
            ->greeting(__('backups.mail.greeting'))
            ->line($this->problem)
            ->line(__('backups.mail.line'))
            ->action(__('backups.mail.action'), url('/platform/backups'))
            ->salutation(__('invoice.mail.salutation', ['platform' => PlatformSettings::get('name')]));
    }
}

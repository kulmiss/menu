<?php

namespace App\Notifications;

use App\Support\PlatformSettings;
use App\Support\Security\SuperAdminAccess;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A server alert to a Super Admin (QUESTIONS #233). Sent at once, never queued: the queue may be what is broken. */
class ServerAlertNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $subject, public readonly string $text) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->error()->subject($this->subject)->greeting(__('alerts.greeting'));
        foreach (explode("\n", $this->text) as $line) {
            if (trim($line) !== '') {
                $mail->line($line);
            }
        }

        return $mail->line(__('alerts.line'))
            ->action(__('alerts.action'), url(SuperAdminAccess::url()))
            ->salutation(__('invoice.mail.salutation', ['platform' => PlatformSettings::get('name')]));
    }
}

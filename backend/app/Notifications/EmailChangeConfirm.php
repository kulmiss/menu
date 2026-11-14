<?php

namespace App\Notifications;

use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** Sent to the NEW address: a signed one-hour link that makes the change (SECURITY_REPORT.md H3). */
class EmailChangeConfirm extends Notification implements ShouldQueue
{
    use Queueable;

    public const MINUTES = 60;

    public function __construct(public readonly int $userId, public readonly string $email, public readonly string $name) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public static function url(int $userId, string $email): string
    {
        return URL::temporarySignedRoute('email-change.confirm', now()->addMinutes(self::MINUTES), [
            'user' => $userId,
            'hash' => sha1($email),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = ['app' => (string) PlatformSettings::get('name'), 'name' => $this->name, 'minutes' => self::MINUTES];

        return (new MailMessage)
            ->subject(__('security.email_change.confirm.subject', $r))
            ->greeting(__('signup.mail.hello_name', $r))
            ->line(__('security.email_change.confirm.intro', $r))
            ->action(__('security.email_change.confirm.action'), self::url($this->userId, $this->email))
            ->line(__('security.email_change.confirm.expiry', $r))
            ->line(__('security.email_change.confirm.not_you'))
            ->salutation(__('signup.mail.signature', $r));
    }
}

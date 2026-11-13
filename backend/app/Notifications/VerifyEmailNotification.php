<?php

namespace App\Notifications;

use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * "Verify your e-mail" (owner, 2026-11-13): a signed link that works for
 * 24 hours and doesn't need the owner to be signed in on that device.
 */
class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const HOURS = 24;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public static function url(object $notifiable): string
    {
        return URL::temporarySignedRoute('signup.verify', now()->addHours(self::HOURS), [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = ['app' => (string) PlatformSettings::get('name'), 'name' => $notifiable->name ?? '', 'hours' => self::HOURS];

        return (new MailMessage)
            ->subject(__('signup.mail.verify.subject', $r))
            ->greeting(__('signup.mail.hello_name', $r))
            ->line(__('signup.mail.verify.intro', $r))
            ->action(__('signup.mail.verify.action'), self::url($notifiable))
            ->line(__('signup.mail.verify.expiry', $r))
            ->line(__('signup.mail.verify.not_you'))
            ->salutation(__('signup.mail.signature', $r));
    }
}

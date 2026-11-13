<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the owner of a restaurant the Super Admin creates (and again with
 * "Resend set-password e-mail"): a friendly welcome, a set-password button
 * (valid 7 days), the menu address and three first steps. Queued; the
 * cron-run queue worker sends it.
 */
class OwnerWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $token,
        public readonly bool $reminder = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = (string) PlatformSettings::get('name');
        $replace = ['app' => $app, 'restaurant' => $this->tenant->name, 'name' => $notifiable->name ?? ''];
        $url = url('/auth/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'welcome' => 1,
        ]));

        $mail = (new MailMessage)
            ->subject(__($this->reminder ? 'owner_welcome.subject_again' : 'owner_welcome.subject', $replace))
            ->greeting(__('owner_welcome.greeting', $replace))
            ->line(__('owner_welcome.intro', $replace))
            ->line(__('owner_welcome.set_password'))
            ->action(__('owner_welcome.action'), $url)
            ->line(__('owner_welcome.menu_address', ['url' => url('/'.$this->tenant->slug)]))
            ->line(__('owner_welcome.first_steps'))
            ->line('• '.__('owner_welcome.step_dishes'))
            ->line('• '.__('owner_welcome.step_look'))
            ->line('• '.__('owner_welcome.step_qr'))
            ->line(__('owner_welcome.expiry'));
        $support = PlatformSettings::get('support_email');
        if (is_string($support) && $support !== '') {
            $mail->line(__('owner_welcome.help', ['email' => $support]));
        }

        return $mail->salutation(__('owner_welcome.signature', $replace));
    }
}

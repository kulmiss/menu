<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Queued; the cron-run queue worker sends it (no worker on shared hosting). */
class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly string $token,
        public readonly string $restaurantName,
        public readonly string $inviterName,
        public readonly string $locale = 'en',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = [
            'restaurant' => $this->restaurantName,
            'inviter' => $this->inviterName,
            'role' => __('menu.roles.'.$this->invitation->role->value, [], $this->locale),
            'date' => $this->invitation->expires_at->toFormattedDateString(),
        ];

        return (new MailMessage)
            ->subject(__('menu.invitation.mail_subject', $replace, $this->locale))
            ->line(__('menu.invitation.mail_line', $replace, $this->locale))
            ->action(__('menu.invitation.mail_action', [], $this->locale), url('/app/invite/'.$this->token))
            ->line(__('menu.invitation.mail_expiry', $replace, $this->locale));
    }
}

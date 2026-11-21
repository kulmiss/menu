<?php

namespace App\Notifications;

use App\Support\PlatformSettings;
use App\Support\Security\SuperAdminAccess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To every Super Admin: a restaurant asked "Add my menu for me" (QUESTIONS #235). */
class MenuRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $requestId, public readonly string $restaurant, public readonly int $files) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('menu_request.mail.subject', ['restaurant' => $this->restaurant]))
            ->greeting(__('alerts.greeting'))
            ->line(trans_choice('menu_request.mail.line', $this->files, ['restaurant' => $this->restaurant, 'count' => $this->files]))
            ->action(__('menu_request.mail.action'), url(SuperAdminAccess::url('menu-requests')))
            ->salutation(__('invoice.mail.salutation', ['platform' => PlatformSettings::get('name')]));
    }
}

<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the owner who asked "Add my menu for me": it is done (QUESTIONS #235). */
class MenuReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Tenant $tenant) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $platform = PlatformSettings::get('name');

        return (new MailMessage)
            ->subject(__('menu_request.mail.done_subject'))
            ->greeting(__('owner_welcome.greeting', ['name' => $notifiable->name ?? '', 'app' => $platform, 'restaurant' => $this->tenant->name]))
            ->line(__('menu_request.mail.done_line', ['restaurant' => $this->tenant->name]))
            ->action(__('menu_request.mail.done_action'), url('/'.$this->tenant->slug))
            ->salutation(__('owner_welcome.signature', ['app' => $platform]));
    }
}

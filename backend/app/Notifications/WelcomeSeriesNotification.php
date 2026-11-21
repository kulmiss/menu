<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** One of the day 1, 3 and 7 welcome e-mails (QUESTIONS #235), with a one-click way out. */
class WelcomeSeriesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Tenant $tenant, public readonly int $step) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = (string) PlatformSettings::get('name');
        $r = ['app' => $app, 'restaurant' => $this->tenant->name, 'name' => $notifiable->name ?? ''];
        $k = 'welcome_series.step'.$this->step;
        $unsubscribe = URL::signedRoute('news.unsubscribe', ['user' => $notifiable->getKey()]);

        $mail = (new MailMessage)
            ->subject(__("{$k}.subject", $r))
            ->greeting(__('owner_welcome.greeting', $r));
        foreach ((array) __("{$k}.lines", $r) as $line) {
            $mail->line($line);
        }

        return $mail->action(__("{$k}.action"), url(__("{$k}.path")))
            ->line(__('welcome_series.unsubscribe', ['url' => $unsubscribe]))
            ->salutation(__('owner_welcome.signature', $r))
            ->withSymfonyMessage(fn ($m) => $m->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>')->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click'));
    }
}

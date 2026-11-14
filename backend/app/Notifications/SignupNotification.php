<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sign-up e-mails (owner, 2026-11-13), all texts in lang/en/signup.php:
 * welcome (after verifying), pending / approved / rejected (approval on),
 * admin (new sign-up, to Super Admins) and existing (someone signed up with
 * an address that already has an account — the page never says so).
 */
class SignupNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $extra */
    public function __construct(
        public readonly string $kind,
        public readonly ?Tenant $tenant = null,
        public readonly array $extra = [],
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = (string) PlatformSettings::get('name');
        $r = [
            'app' => $app,
            'restaurant' => $this->tenant->name ?? '',
            'name' => $notifiable->name ?? ($this->extra['name'] ?? ''),
            'url' => $this->tenant ? url('/'.$this->tenant->slug) : url('/'),
            'reason' => (string) ($this->extra['reason'] ?? ''),
            'owner' => (string) ($this->extra['owner'] ?? ''),
            'email' => (string) ($this->extra['email'] ?? ''),
        ];
        $k = 'signup.mail.'.$this->kind.'.';
        $mail = (new MailMessage)->subject(__($k.'subject', $r))->greeting(__($r['name'] === '' ? 'signup.mail.hello' : 'signup.mail.hello_name', $r));

        match ($this->kind) {
            'welcome' => $mail->line(__($k.'intro', $r))
                ->line(__('signup.mail.menu_address', $r))
                ->line(__('signup.mail.first_steps'))
                ->line('• '.__('signup.mail.step_look'))
                ->line('• '.__('signup.mail.step_dishes'))
                ->line('• '.__('signup.mail.step_qr'))
                ->action(__($k.'action'), url('/admin?welcome=1')),
            'pending' => $mail->line(__($k.'intro', $r))->line(__($k.'meanwhile'))->action(__($k.'action'), url('/admin')),
            'approved' => $mail->line(__($k.'intro', $r))->line(__('signup.mail.menu_address', $r))->action(__($k.'action'), url('/admin')),
            'rejected' => $mail->line(__($k.'intro', $r))->line(__($k.'reason', $r))->line(__($k.'outro')),
            'admin' => $mail->line(__($k.'intro', $r))
                ->line(__($k.'owner', $r))
                ->line(__('signup.mail.menu_address', $r))
                ->line(__(($this->extra['approval'] ?? false) ? $k.'waiting' : $k.'live'))
                ->action(__($k.'action'), url('/platform/restaurants'.(($this->extra['approval'] ?? false) ? '?state=pending' : ''))),
            'existing' => $mail->line(__($k.'intro', $r))
                ->action(__($k.'action'), url('/auth/sign-in'))
                ->line(__($k.'forgot'))
                ->line(__($k.'not_you')),
            default => null,
        };
        $support = PlatformSettings::get('support_email');
        if (is_string($support) && $support !== '' && $this->kind !== 'admin') {
            $mail->line(__('signup.mail.help', ['email' => $support]));
        }

        return $mail->salutation(__('signup.mail.signature', $r));
    }
}

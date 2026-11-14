<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\Billing\InvoicePdf;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The invoice PDF, e-mailed to the restaurant's owners (account e-mail: always sent). Queued. */
class InvoiceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $invoiceId) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = Invoice::with('replaces')->findOrFail($this->invoiceId);
        $platform = (string) PlatformSettings::get('name');
        $date = fn ($d) => $d->format('j M Y');

        return (new MailMessage)
            ->subject(__('invoice.mail.subject', ['number' => $invoice->number, 'platform' => $platform]))
            ->greeting(__('invoice.mail.greeting', ['name' => $notifiable->name ?? '']))
            ->line(__('invoice.mail.line', ['restaurant' => $invoice->customer['name'] ?? '', 'number' => $invoice->number]))
            ->line(__('invoice.mail.period', ['start' => $date($invoice->period_start), 'end' => $date($invoice->period_end)]))
            ->action(__('invoice.mail.action'), url('/admin/settings/billing'))
            ->salutation(__('invoice.mail.salutation', ['platform' => $platform]))
            ->attachData(InvoicePdf::render($invoice), InvoicePdf::filename($invoice), ['mime' => 'application/pdf']);
    }
}

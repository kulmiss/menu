<?php

namespace App\Mail;

use App\Support\Campaigns\Body;
use App\Support\PlatformSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/** One campaign e-mail (news): body, and an unsubscribe link and header. */
class CampaignMail extends Mailable
{
    /** @param  array<string, string>  $vars */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $text,
        public readonly array $vars,
        public readonly ?string $unsubscribeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Body::subject($this->subjectLine, $this->vars));
    }

    public function headers(): Headers
    {
        return new Headers(text: $this->unsubscribeUrl ? [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ] : []);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.campaign', with: [
            'html' => Body::html($this->text, $this->vars),
            'unsubscribe' => $this->unsubscribeUrl,
            'platform' => (string) PlatformSettings::get('name'),
        ]);
    }
}

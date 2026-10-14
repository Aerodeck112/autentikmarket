<?php

namespace App\Mail;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mailable generic: corpul este deja randat (HTML sigur) de NotificationService.
 */
class TemplatedMail extends Mailable
{
    use Queueable;

    public function __construct(public string $mailSubject, public string $htmlBody, public string $language = 'ro') {}

    public function envelope(): Envelope
    {
        $replyTo = Settings::get('mail.reply_to');

        return new Envelope(
            subject: $this->mailSubject,
            replyTo: filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? [new Address($replyTo)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->htmlBody);
    }
}

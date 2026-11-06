<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Headers;

/** E-mail de campanie: ca TemplatedMail, plus antetul List-Unsubscribe (dezabonare dintr-un clic în Gmail/Outlook). */
class CampaignMail extends TemplatedMail
{
    public function __construct(string $mailSubject, string $htmlBody, string $language, public string $unsubscribeUrl)
    {
        parent::__construct($mailSubject, $htmlBody, $language);
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }
}

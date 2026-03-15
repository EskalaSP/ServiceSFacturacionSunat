<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ConnectionRequestMail extends BaseMailable
{
    public function __construct(
        public string $fromBusinessName,
        public string $toBusinessName,
        public string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->fromBusinessName} quiere conectar contigo",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.connection-request',
        );
    }
}

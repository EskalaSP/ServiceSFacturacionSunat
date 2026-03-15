<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitationMail extends BaseMailable
{
    public function __construct(
        public string $fromBusinessName,
        public string $inviteeEmail,
        public string $registerUrl,
        public ?string $inviteeRuc = null,
        public ?string $inviteeRazonSocial = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->fromBusinessName} te invita a " . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation',
        );
    }
}

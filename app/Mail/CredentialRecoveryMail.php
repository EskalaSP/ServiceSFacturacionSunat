<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CredentialRecoveryMail extends BaseMailable
{
    public function __construct(
        public Tenant $tenant,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recuperación de credenciales API — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.credential-recovery',
            with: [
                'tenantName' => $this->tenant->razon_social,
                'ruc'        => $this->tenant->ruc,
                'token'      => $this->token,
            ],
        );
    }
}

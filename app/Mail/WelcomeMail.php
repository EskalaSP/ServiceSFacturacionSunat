<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WelcomeMail extends BaseMailable
{
    public function __construct(
        public Tenant $tenant,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenido a ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: [
                'tenantName' => $this->tenant->razon_social,
                'plan' => $this->tenant->plan,
                'loginUrl' => config('app.url') . '/login',
            ],
        );
    }
}

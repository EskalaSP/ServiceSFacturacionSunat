<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TrialEndingMail extends BaseMailable
{
    public function __construct(
        public Subscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu prueba gratis termina en 3 días',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trial-ending',
            with: [
                'tenantName' => $this->subscription->tenant->razon_social,
                'planName' => $this->subscription->plan->name,
                'trialEndsAt' => $this->subscription->trial_ends_at?->format('d/m/Y'),
                'billingUrl' => config('app.url') . '/settings/billing',
            ],
        );
    }
}

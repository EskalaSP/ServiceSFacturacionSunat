<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PaymentFailedMail extends BaseMailable
{
    public function __construct(
        public Subscription $subscription,
        public string $reason = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Problema con tu pago',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-failed',
            with: [
                'tenantName' => $this->subscription->tenant->razon_social,
                'planName' => $this->subscription->plan->name,
                'reason' => $this->reason,
                'billingUrl' => config('app.url') . '/settings/billing',
            ],
        );
    }
}

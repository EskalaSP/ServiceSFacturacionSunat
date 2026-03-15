<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PaymentReminderMail extends BaseMailable
{
    public function __construct(
        public Subscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu suscripción se renueva pronto',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-reminder',
            with: [
                'tenantName' => $this->subscription->tenant->razon_social,
                'planName' => $this->subscription->plan->name,
                'renewalDate' => $this->subscription->current_period_end?->format('d/m/Y'),
                'amount' => $this->subscription->billing_cycle === 'yearly'
                    ? $this->subscription->plan->price_yearly
                    : $this->subscription->plan->price_monthly,
                'billingUrl' => config('app.url') . '/settings/billing',
            ],
        );
    }
}

<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Mail\PaymentFailedMail;
use Illuminate\Support\Facades\Mail;

class SendPaymentFailedEmail
{
    public function handle(PaymentFailed $event): void
    {
        $tenant = $event->subscription->tenant;
        $email = $tenant->user?->email;

        if (! $email) {
            return;
        }

        Mail::to($email)->queue(
            new PaymentFailedMail($event->subscription, $event->reason)
        );
    }
}

<?php

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Mail\WelcomeMail;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail
{
    public function handle(SubscriptionCreated $event): void
    {
        $tenant = $event->subscription->tenant;
        $email = $tenant->user?->email;

        if (! $email) {
            return;
        }

        Mail::to($email)->queue(new WelcomeMail($tenant));
    }
}

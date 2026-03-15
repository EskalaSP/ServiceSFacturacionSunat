<?php

namespace App\Listeners;

use App\Events\TrialExpiring;
use App\Mail\TrialEndingMail;
use Illuminate\Support\Facades\Mail;

class SendTrialEndingEmail
{
    public function handle(TrialExpiring $event): void
    {
        $tenant = $event->subscription->tenant;
        $email = $tenant->user?->email;

        if (! $email) {
            return;
        }

        Mail::to($email)->queue(new TrialEndingMail($event->subscription));
    }
}

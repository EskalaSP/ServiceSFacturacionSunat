<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

abstract class BaseMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    // Cola propia para correos: un flood de facturas no retrasa los emails,
    // y un SMTP lento/caído no bloquea los envíos a SUNAT.
    public $queue = 'mail';
}

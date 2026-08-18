<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

abstract class BaseMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    /**
     * Encola los correos en su propia cola "mail".
     *
     * No se declara `public $queue = 'mail'` como propiedad porque el trait
     * Queueable ya define `$queue` sin valor y, desde PHP 8.5, redeclararla
     * con un default distinto es un error fatal de composición de traits.
     * En su lugar se fija la cola aquí, al momento de encolar, vía onQueue().
     *
     * Cola propia: un flood de facturas no retrasa los emails, y un SMTP
     * lento/caído no bloquea los envíos a SUNAT.
     */
    public function queue(QueueFactory $queue)
    {
        $this->onQueue('mail');

        return parent::queue($queue);
    }
}

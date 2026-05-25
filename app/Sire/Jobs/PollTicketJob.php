<?php

namespace App\Sire\Jobs;

use App\Sire\Enums\EstadoTicket;
use App\Sire\Exceptions\SireException;
use App\Sire\Exceptions\SireTicketFailedException;
use App\Sire\Models\SireTicket;
use App\Sire\Services\Tickets\TicketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Poolea el estado de un ticket SIRE hasta que termine o se agote el intento máximo.
 *
 * Estrategia:
 *   - Llama 5.31 (fetchStatus)
 *   - Si estado es final (TERMINADO/ERROR) → dispatch DownloadTicketFileJob si hay archivo
 *   - Si estado no es final → re-encola con $this->release(delay)
 *   - `delay` crece con backoff hasta max_backoff
 */
class PollTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // usamos release() para reintentar, no reintentos por fallo

    public function __construct(
        public readonly int $ticketId,
        public readonly int $attemptNumber = 0,
    ) {
        $this->onQueue(config('sire.queues.poll'));
    }

    public function middleware(): array
    {
        return [
            new RateLimited('sunat-sire'),
        ];
    }

    public function handle(TicketService $service): void
    {
        /** @var SireTicket|null $ticket */
        $ticket = SireTicket::with('tenant')->find($this->ticketId);

        if (! $ticket) {
            Log::channel('stack')->warning('[SIRE][PollTicketJob] ticket no existe', [
                'ticket_id' => $this->ticketId,
            ]);
            return;
        }

        if ($ticket->isFinal()) {
            return; // ya terminó, nada que hacer
        }

        $maxAttempts = (int) config('sire.polling.max_attempts');
        if ($this->attemptNumber >= $maxAttempts) {
            $ticket->cod_estado_proceso = EstadoTicket::ERROR->value;
            $ticket->des_estado_proceso = "Timeout después de {$this->attemptNumber} intentos";
            $ticket->finished_at = now();
            $ticket->save();
            Log::channel('stack')->error('[SIRE][PollTicketJob] timeout', [
                'ticket' => $ticket->num_ticket,
                'attempts' => $this->attemptNumber,
            ]);
            return;
        }

        try {
            $service->fetchStatus($ticket);
        } catch (SireException $e) {
            Log::channel('stack')->warning('[SIRE][PollTicketJob] error consultando ticket', [
                'ticket'    => $ticket->num_ticket,
                'exception' => $e->getMessage(),
            ]);
            // Reencolamos con retroceso exponencial — un 5xx transitorio no debe matar el sondeo
            $this->releaseWithBackoff();
            return;
        }

        if ($ticket->isFinal()) {
            $this->dispatchDownloadIfApplicable($ticket);
            return;
        }

        $this->releaseWithBackoff();
    }

    private function dispatchDownloadIfApplicable(SireTicket $ticket): void
    {
        if (! $ticket->isSuccess()) {
            return;
        }

        if (empty($ticket->nom_archivo_reporte)) {
            return;
        }

        DownloadTicketFileJob::dispatch($ticket->id);
    }

    private function releaseWithBackoff(): void
    {
        $base     = (int) config('sire.polling.interval_seconds');
        $mult     = (float) config('sire.polling.backoff_multiplier');
        $maxDelay = (int) config('sire.polling.max_backoff_seconds');

        $delay = (int) min(
            $maxDelay,
            $base * ($mult ** $this->attemptNumber),
        );

        static::dispatch($this->ticketId, $this->attemptNumber + 1)
            ->delay(now()->addSeconds($delay));
    }
}

<?php

namespace App\Console\Commands\Sire;

use App\Sire\Enums\EstadoTicket;
use App\Sire\Jobs\PollTicketJob;
use App\Sire\Models\SireTicket;
use Illuminate\Console\Command;

/**
 * Barre tickets que están "en proceso" y que no han sido pooleados recientemente,
 * encolando un PollTicketJob para cada uno.
 *
 * Pensado para correr en el scheduler cada minuto. Idempotente: si ya hay un Job
 * en vuelo para un ticket, agregar otro no genera problemas — el Job es corto
 * y la duplicación es benigna (SUNAT responde rápido a 5.31).
 */
class SirePollPendingCommand extends Command
{
    protected $signature = 'sire:poll-pending
        {--stale=120 : segundos sin poll para considerar un ticket "stale"}
        {--limit=200 : máximo de tickets a encolar por ejecución}';

    protected $description = 'Encola PollTicketJob para tickets SIRE que siguen en proceso.';

    public function handle(): int
    {
        $staleSeconds = (int) $this->option('stale');
        $limit        = (int) $this->option('limit');

        $noFinales = [
            EstadoTicket::PENDIENTE->value,
            EstadoTicket::EN_PROCESO->value,
            EstadoTicket::PROCESANDO->value,
        ];

        $tickets = SireTicket::query()
            ->whereIn('cod_estado_proceso', $noFinales)
            ->where(function ($q) use ($staleSeconds) {
                $q->whereNull('last_polled_at')
                  ->orWhere('last_polled_at', '<', now()->subSeconds($staleSeconds));
            })
            ->orderBy('last_polled_at')
            ->limit($limit)
            ->get(['id', 'num_ticket', 'poll_attempts']);

        foreach ($tickets as $ticket) {
            PollTicketJob::dispatch($ticket->id, (int) $ticket->poll_attempts);
        }

        $this->info("Encolados {$tickets->count()} PollTicketJob.");

        return self::SUCCESS;
    }
}

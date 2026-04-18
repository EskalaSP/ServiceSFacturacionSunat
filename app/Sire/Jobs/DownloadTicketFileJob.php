<?php

namespace App\Sire\Jobs;

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
 * Descarga el archivo asociado a un ticket SIRE (servicio 5.32)
 * una vez que el ticket está en estado TERMINADO.
 *
 * Se ejecuta en la cola `sire-heavy` porque los archivos pueden ser de varios MB.
 */
class DownloadTicketFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 600; // 10 minutos para archivos grandes

    public function __construct(public readonly int $ticketId)
    {
        $this->onQueue(config('sire.queues.heavy'));
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
            Log::channel('stack')->warning('[SIRE][DownloadTicketFileJob] ticket no existe', [
                'ticket_id' => $this->ticketId,
            ]);
            return;
        }

        if (empty($ticket->nom_archivo_reporte)) {
            Log::channel('stack')->warning('[SIRE][DownloadTicketFileJob] ticket sin archivo', [
                'ticket' => $ticket->num_ticket,
            ]);
            return;
        }

        if ($ticket->archivo_local_path) {
            $this->dispatchPostProcess($ticket);
            return;
        }

        $service->downloadFile($ticket);
        $ticket->refresh();

        $this->dispatchPostProcess($ticket);
    }

    /**
     * Si el archivo corresponde a una propuesta RCE, dispatch del parser.
     */
    private function dispatchPostProcess(\App\Sire\Models\SireTicket $ticket): void
    {
        $codProcesosParseables = [
            \App\Sire\Enums\CodProceso::GENERAR_EXPORT_PROPUESTA->value,
            \App\Sire\Enums\CodProceso::GENERAR_EXPORT_PRELIMINAR->value,
        ];

        if (in_array($ticket->cod_proceso, $codProcesosParseables, true)) {
            ProcessPropuestaJob::dispatch($ticket->id);
        }
    }
}

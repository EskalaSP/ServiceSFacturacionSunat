<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckSummaryTicketStatus;
use App\Models\Summary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rescata resúmenes diarios que quedaron sin resolver.
 *
 * `CheckSummaryTicketStatus` reintenta 15 veces con un backoff que suma unos 55
 * minutos. Si SUNAT sigue respondiendo "en proceso" pasada esa hora, el job se
 * agota y muere: el resumen queda en `enviado` para siempre y las boletas que
 * contiene se quedan colgadas con él. Nadie lo miraba después.
 *
 * Este barrido vuelve a preguntar por el ticket, espaciando las consultas.
 *
 * NO reenvía nada. Un resumen que ya tiene ticket fue recibido por SUNAT;
 * mandarlo de nuevo lo duplicaría, que es justo lo que hay que evitar. Los que
 * quedaron sin ticket se reportan aparte para revisarlos, no se reenvían solos.
 */
class SummariesPollPendingCommand extends Command
{
    protected $signature = 'summaries:poll-pending
        {--limit=100 : máximo de resúmenes a encolar por ejecución}';

    protected $description = 'Vuelve a consultar el ticket de los resúmenes diarios sin estado final.';

    /** Consultas seguidas mientras SUNAT todavía puede estar procesando. */
    private const INTENTOS_RAPIDOS = 6;

    private const INTERVALO_RAPIDO_MINUTOS = 10;

    private const INTERVALO_LENTO_HORAS = 4;

    /**
     * Tope de días para seguir preguntando desde el envío.
     *
     * Un ticket que SUNAT no resuelve en dos semanas no lo va a resolver
     * insistiendo: es un caso para mirar a mano.
     */
    private const DIAS_MAXIMOS = 15;

    public function handle(): int
    {
        $limite = (int) $this->option('limit');

        $pendientes = Summary::query()
            ->whereNotIn('sunat_status', ['aceptado', 'rechazado'])
            ->orderByRaw('last_polled_at IS NULL DESC')
            ->orderBy('last_polled_at')
            ->limit($limite)
            ->get();

        $encolados = 0;
        $sinTicket = [];
        $abandonados = [];

        foreach ($pendientes as $summary) {
            if (empty($summary->ticket)) {
                // Se marcó como "enviado" al encolar el envío, pero el envío
                // nunca devolvió ticket: SUNAT no lo recibió. Reenviarlo desde
                // acá podría duplicarlo si en realidad sí entró, así que solo
                // se reporta.
                $sinTicket[] = $summary->identifier;

                continue;
            }

            if ($summary->created_at?->diffInDays(now()) > self::DIAS_MAXIMOS) {
                $abandonados[] = $summary->identifier;

                continue;
            }

            if (! $this->tocaConsultar($summary)) {
                continue;
            }

            $summary->update([
                'last_polled_at' => now(),
                'poll_attempts' => $summary->poll_attempts + 1,
            ]);

            CheckSummaryTicketStatus::dispatch($summary->id);
            $encolados++;
        }

        if ($sinTicket !== []) {
            Log::warning('Resúmenes en enviado sin ticket: el envío no llegó a SUNAT', [
                'identificadores' => $sinTicket,
            ]);
            $this->warn(count($sinTicket).' resumen(es) sin ticket — requieren revisión: '.implode(', ', $sinTicket));
        }

        if ($abandonados !== []) {
            Log::warning('Resúmenes sin resolver tras '.self::DIAS_MAXIMOS.' días', [
                'identificadores' => $abandonados,
            ]);
            $this->warn(count($abandonados).' resumen(es) llevan más de '.self::DIAS_MAXIMOS.' días sin resolver: '.implode(', ', $abandonados));
        }

        $this->info("{$encolados} consulta(s) de ticket encolada(s).");

        return self::SUCCESS;
    }

    /**
     * Seguido al principio, espaciado después.
     *
     * SUNAT suele resolver el ticket en minutos; cuando no lo hace, insistir
     * cada diez minutos durante días no acelera nada y solo gasta llamadas.
     */
    private function tocaConsultar(Summary $summary): bool
    {
        if ($summary->last_polled_at === null) {
            return true;
        }

        $espera = $summary->poll_attempts < self::INTENTOS_RAPIDOS
            ? $summary->last_polled_at->addMinutes(self::INTERVALO_RAPIDO_MINUTOS)
            : $summary->last_polled_at->addHours(self::INTERVALO_LENTO_HORAS);

        return $espera->isPast();
    }
}

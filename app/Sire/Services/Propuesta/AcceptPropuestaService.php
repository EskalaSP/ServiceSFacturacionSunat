<?php

namespace App\Sire\Services\Propuesta;

use App\Models\Tenant;
use App\Sire\Enums\CodProceso;
use App\Sire\Jobs\PollTicketJob;
use App\Sire\Models\SireTicket;
use App\Sire\Services\Http\SireHttpClient;
use App\Sire\Services\Tickets\TicketService;
use App\Sire\Support\PeriodoTributario;

/**
 * Servicio 5.2 — Aceptar Propuesta.
 *
 * POST /v1/contribuyente/migeigv/libros/rce/propuesta/web/registroslibros/{perTributario}/aceptarpropuesta
 *
 * Respuesta: { numTicket: "AAAA99999999" }
 *
 * Después del ticket TERMINADO, se considera la propuesta aceptada y el sistema
 * cambia al estado "Preliminar registrado".
 */
class AcceptPropuestaService
{
    public function __construct(
        private readonly SireHttpClient $http,
        private readonly TicketService $tickets,
    ) {}

    public function aceptar(Tenant $tenant, PeriodoTributario $periodo): SireTicket
    {
        $path = sprintf(
            'contribuyente/migeigv/libros/rce/propuesta/web/registroslibros/%s/aceptarpropuesta',
            $periodo->toString(),
        );

        $response = $this->http->post($tenant, $path);

        $numTicket = $response['numTicket'] ?? null;
        if (! $numTicket) {
            throw new \RuntimeException('SUNAT no devolvió numTicket al aceptar la propuesta.');
        }

        $ticket = $this->tickets->register(
            tenant: $tenant,
            numTicket: $numTicket,
            perTributario: $periodo->toString(),
            codProceso: CodProceso::ACEPTAR_PROPUESTA->value,
            requestPayload: ['periodo' => $periodo->toString()],
        );

        PollTicketJob::dispatch($ticket->id);

        return $ticket;
    }
}

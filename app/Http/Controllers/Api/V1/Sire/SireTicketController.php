<?php

namespace App\Http\Controllers\Api\V1\Sire;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Sire\Exceptions\SireException;
use App\Sire\Jobs\PollTicketJob;
use App\Sire\Models\SireTicket;
use App\Sire\Services\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SireTicketController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TicketService $tickets,
    ) {}

    /**
     * GET /v1/sire/tickets
     *
     * Lista tickets del tenant con filtros por periodo/estado/proceso.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $validated = $request->validate([
            'per_tributario' => 'sometimes|string|size:6',
            'estado'         => 'sometimes|string|size:2',
            'cod_proceso'    => 'sometimes|string|max:10',
            'finalizado'     => 'sometimes|boolean',
            'por_pagina' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = SireTicket::query()
            ->where('tenant_id', $tenant->id)
            ->when(isset($validated['per_tributario']), fn ($q) => $q->where('per_tributario', $validated['per_tributario']))
            ->when(isset($validated['estado']), fn ($q) => $q->where('cod_estado_proceso', $validated['estado']))
            ->when(isset($validated['cod_proceso']), fn ($q) => $q->where('cod_proceso', $validated['cod_proceso']))
            ->when(isset($validated['finalizado']), fn ($q) => $validated['finalizado']
                ? $q->whereNotNull('finished_at')
                : $q->whereNull('finished_at'))
            ->orderByDesc('created_at');

        return $this->success(
            $query->paginate($validated['per_page'] ?? 20)->through(fn (SireTicket $t) => $this->presentTicket($t)),
        );
    }

    /**
     * GET /v1/sire/tickets/{numTicket}
     */
    public function show(Request $request, string $numTicket): JsonResponse
    {
        $tenant = $request->get('tenant');

        $ticket = SireTicket::where('tenant_id', $tenant->id)
            ->where('num_ticket', $numTicket)
            ->firstOrFail();

        return $this->success($this->presentTicket($ticket));
    }

    /**
     * POST /v1/sire/tickets/{numTicket}/refrescar
     *
     * Fuerza una consulta inmediata del estado del ticket contra SUNAT.
     * Útil cuando el cliente no quiere esperar al polling automático.
     */
    public function refresh(Request $request, string $numTicket): JsonResponse
    {
        $tenant = $request->get('tenant');

        $ticket = SireTicket::with('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('num_ticket', $numTicket)
            ->firstOrFail();

        if ($ticket->isFinal()) {
            return $this->success($this->presentTicket($ticket));
        }

        try {
            $ticket = $this->tickets->fetchStatus($ticket);

            if ($ticket->isSuccess() && $ticket->nom_archivo_reporte && ! $ticket->archivo_local_path) {
                \App\Sire\Jobs\DownloadTicketFileJob::dispatch($ticket->id);
            }

            return $this->success($this->presentTicket($ticket));
        } catch (SireException $e) {
            return $this->error($e->getMessage(), $e->httpStatus, ['sunat_code' => $e->sunatCode]);
        }
    }

    /**
     * GET /v1/sire/tickets/{numTicket}/archivo
     *
     * Descarga el ZIP guardado localmente. Si aún no fue descargado, devuelve 409.
     */
    public function archivo(Request $request, string $numTicket): StreamedResponse|JsonResponse
    {
        $tenant = $request->get('tenant');

        $ticket = SireTicket::where('tenant_id', $tenant->id)
            ->where('num_ticket', $numTicket)
            ->firstOrFail();

        if (! $ticket->archivo_local_path) {
            return $this->error(
                'El archivo aún no está disponible. Verifica el estado con GET /v1/sire/tickets/' . $numTicket,
                409,
            );
        }

        $disk = Storage::disk(config('sire.storage.disk'));

        if (! $disk->exists($ticket->archivo_local_path)) {
            return $this->error('Archivo no encontrado en storage.', 404);
        }

        return $disk->download(
            $ticket->archivo_local_path,
            $ticket->nom_archivo_reporte ?? "ticket-{$numTicket}.zip",
        );
    }

    private function presentTicket(SireTicket $t): array
    {
        return [
            'num_ticket'          => $t->num_ticket,
            'per_tributario'      => $t->per_tributario,
            'cod_proceso'         => $t->cod_proceso,
            'des_proceso'         => $t->des_proceso,
            'estado'              => $t->cod_estado_proceso,
            'estado_descripcion'  => $t->des_estado_proceso,
            'estado_enum'         => $t->estadoEnum()?->name,
            'finalizado'          => $t->isFinal(),
            'exitoso'             => $t->isSuccess(),
            'archivo_disponible'  => ! empty($t->archivo_local_path),
            'nom_archivo_reporte' => $t->nom_archivo_reporte,
            'cnt_cp_informados'   => $t->cnt_cp_informados,
            'cnt_cp_error'        => $t->cnt_cp_error,
            'poll_attempts'       => $t->poll_attempts,
            'created_at'          => $t->created_at,
            'last_polled_at'      => $t->last_polled_at,
            'finished_at'         => $t->finished_at,
        ];
    }
}

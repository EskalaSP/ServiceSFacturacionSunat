<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Services\Tenancy\EmpresaActiva;
use App\Sire\Enums\CodLibro;
use App\Sire\Enums\CodTipoArchivo;
use App\Sire\Models\SireTicket;
use App\Sire\Services\Auth\SireAuthService;
use App\Sire\Services\Periodos\PeriodoService;
use App\Sire\Services\Preliminar\RegisterPreliminarService;
use App\Sire\Services\Propuesta\AcceptPropuestaService;
use App\Sire\Services\Propuesta\DownloadPropuestaService;
use App\Sire\Services\Reconciliation\ReconciliationService;
use App\Sire\Services\Tickets\TicketService;
use App\Sire\Support\PeriodoTributario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel SIRE (RCE): activación, periodos, propuesta/aceptar/preliminar, tickets y
 * reconciliación. Reutiliza los servicios de App\Sire. Todo gateado por sire.gestionar.
 * Las llamadas a SUNAT pueden fallar (SireException) → se devuelven como {ok:false}.
 */
class SireController extends Controller
{
    private function tenant()
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('gestionar-sire', $tenant);

        return $tenant;
    }

    public function index(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();
        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }
        Gate::authorize('gestionar-sire', $tenant);

        $tickets = SireTicket::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')->limit(20)->get()
            ->map(fn (SireTicket $t) => $this->presentTicket($t));

        return Inertia::render('sunat/sire/index', [
            'sire_enabled' => (bool) $tenant->sire_enabled,
            'tickets' => $tickets,
        ]);
    }

    public function activar(SireAuthService $auth): RedirectResponse
    {
        $tenant = $this->tenant();

        try {
            $auth->getToken($tenant);
            $tenant->update(['sire_enabled' => true]);

            return back()->with('success', 'SIRE activado correctamente.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo activar SIRE: '.$e->getMessage());
        }
    }

    public function desactivar(SireAuthService $auth): RedirectResponse
    {
        $tenant = $this->tenant();
        $tenant->update(['sire_enabled' => false]);
        $auth->invalidate($tenant);

        return back()->with('success', 'SIRE desactivado.');
    }

    public function periodos(PeriodoService $service): JsonResponse
    {
        $tenant = $this->tenant();

        try {
            $data = $service->listar($tenant, CodLibro::RCE);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function propuesta(Request $request, DownloadPropuestaService $service): JsonResponse
    {
        $tenant = $this->tenant();
        $periodo = (string) $request->input('periodo');

        try {
            $ticket = $service->solicitar($tenant, PeriodoTributario::fromString($periodo), CodTipoArchivo::TXT, []);

            return response()->json(['ok' => true, 'num_ticket' => $ticket->num_ticket, 'mensaje' => 'Propuesta solicitada. Revisa los tickets.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function aceptarPropuesta(Request $request, AcceptPropuestaService $service): JsonResponse
    {
        $tenant = $this->tenant();
        $periodo = (string) $request->input('periodo');

        try {
            $ticket = $service->aceptar($tenant, PeriodoTributario::fromString($periodo));

            return response()->json(['ok' => true, 'num_ticket' => $ticket->num_ticket, 'mensaje' => 'Propuesta aceptada. Revisa los tickets.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function registrarPreliminar(Request $request, RegisterPreliminarService $service): JsonResponse
    {
        $tenant = $this->tenant();
        $periodo = (string) $request->input('periodo');

        try {
            $result = $service->registrar($tenant, PeriodoTributario::fromString($periodo));

            return response()->json(['ok' => true, 'data' => $result, 'mensaje' => 'Preliminar registrado.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function reconciliar(Request $request, ReconciliationService $service): JsonResponse
    {
        $tenant = $this->tenant();
        $periodo = (string) $request->input('periodo');

        try {
            $report = $service->run($tenant, PeriodoTributario::fromString($periodo)->toString());

            return response()->json(['ok' => true, 'data' => $report->toArray()]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function tickets(): JsonResponse
    {
        $tenant = $this->tenant();

        $tickets = SireTicket::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')->limit(30)->get()
            ->map(fn (SireTicket $t) => $this->presentTicket($t));

        return response()->json(['ok' => true, 'data' => $tickets]);
    }

    public function refrescarTicket(string $numTicket, TicketService $service): JsonResponse
    {
        $tenant = $this->tenant();

        $ticket = SireTicket::with('tenant')->where('tenant_id', $tenant->id)->where('num_ticket', $numTicket)->firstOrFail();

        if ($ticket->isFinal()) {
            return response()->json(['ok' => true, 'data' => $this->presentTicket($ticket)]);
        }

        try {
            $ticket = $service->fetchStatus($ticket);
            if ($ticket->isSuccess() && $ticket->nom_archivo_reporte && ! $ticket->archivo_local_path) {
                \App\Sire\Jobs\DownloadTicketFileJob::dispatch($ticket->id);
            }

            return response()->json(['ok' => true, 'data' => $this->presentTicket($ticket)]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function descargarTicket(string $numTicket): StreamedResponse|RedirectResponse
    {
        $tenant = $this->tenant();

        $ticket = SireTicket::where('tenant_id', $tenant->id)->where('num_ticket', $numTicket)->firstOrFail();

        if (! $ticket->archivo_local_path) {
            return back()->with('error', 'El archivo aún no está disponible. Refresca el ticket.');
        }

        $disk = Storage::disk(config('sire.storage.disk'));
        abort_unless($disk->exists($ticket->archivo_local_path), 404);

        return $disk->download($ticket->archivo_local_path, $ticket->nom_archivo_reporte ?? "ticket-{$numTicket}.zip");
    }

    private function presentTicket(SireTicket $t): array
    {
        return [
            'num_ticket' => $t->num_ticket,
            'per_tributario' => $t->per_tributario,
            'des_proceso' => $t->des_proceso,
            'estado' => $t->cod_estado_proceso,
            'estado_descripcion' => $t->des_estado_proceso,
            'finalizado' => $t->isFinal(),
            'exitoso' => $t->isSuccess(),
            'archivo_disponible' => ! empty($t->archivo_local_path),
            'created_at' => optional($t->created_at)->toIso8601String(),
        ];
    }
}

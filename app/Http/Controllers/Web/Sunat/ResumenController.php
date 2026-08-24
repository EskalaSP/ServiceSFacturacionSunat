<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Jobs\CheckSummaryTicketStatus;
use App\Models\Boleta;
use App\Models\Summary;
use App\Services\Documents\SummaryService;
use App\Services\Tenancy\EmpresaActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Resumen diario de boletas desde el panel: envía a SUNAT las boletas pendientes
 * de una fecha. Reutiliza SummaryService (misma lógica que la API).
 */
class ResumenController extends Controller
{
    /** Lista todos los resúmenes diarios (envío y anulación) de la empresa. */
    public function index(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();

        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        $resumenes = Summary::where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'identifier' => $s->identifier,
                'tipo' => $s->tipo,
                'fecha_referencia' => optional($s->fecha_referencia)->format('Y-m-d') ?? (string) $s->fecha_referencia,
                'fecha_envio' => optional($s->fecha_envio)->format('Y-m-d') ?? (string) $s->fecha_envio,
                'total_documentos' => (int) $s->total_documentos,
                'estado' => $s->sunat_status,
                'codigo' => $s->sunat_code,
                'descripcion' => $s->sunat_description,
                'tiene_xml' => ! empty($s->xml_path),
                'tiene_cdr' => ! empty($s->cdr_path),
                'ticket' => (bool) $s->ticket,
            ])
            ->all();

        return Inertia::render('sunat/resumenes/index', [
            'resumenes' => $resumenes,
        ]);
    }

    /** Vuelve a consultar en SUNAT el estado (ticket) de un resumen aún no finalizado. */
    public function refrescar(int $id): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        $summary = Summary::where('tenant_id', $tenant->id)->findOrFail($id);

        if (in_array($summary->sunat_status, ['aceptado', 'rechazado'], true)) {
            return back()->with('info', 'Este resumen ya tiene un estado final de SUNAT.');
        }
        if (! $summary->ticket) {
            return back()->with('error', 'El resumen todavía no tiene ticket de SUNAT.');
        }

        CheckSummaryTicketStatus::dispatch($summary->id);

        return back()->with('success', 'Consultando el estado del resumen en SUNAT…');
    }

    /** Descarga el XML enviado o la constancia (CDR) del resumen. */
    public function descargar(int $id, string $archivo): mixed
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        abort_unless(in_array($archivo, ['xml', 'cdr'], true), 404);

        $summary = Summary::where('tenant_id', $tenant->id)->findOrFail($id);
        $path = $archivo === 'cdr' ? $summary->cdr_path : $summary->xml_path;

        if (empty($path) || ! Storage::disk('public')->exists($path)) {
            abort(404, strtoupper($archivo).' del resumen no disponible aún.');
        }

        $ext = $archivo === 'cdr' ? 'zip' : 'xml';
        $prefijo = $archivo === 'cdr' ? 'R-' : '';

        return Storage::disk('public')->download($path, "{$prefijo}{$summary->identifier}.{$ext}");
    }

    public function create(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();

        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        $pendientes = Boleta::where('tenant_id', $tenant->id)
            ->where('sunat_status', 'pendiente')
            ->get(['id', 'fecha_emision', 'mto_imp_venta']);

        $fechas = $pendientes
            ->groupBy(fn ($b) => optional($b->fecha_emision)->format('Y-m-d') ?? (string) $b->fecha_emision)
            ->map(fn ($grupo, $fecha) => [
                'fecha' => $fecha,
                'cantidad' => $grupo->count(),
                'total' => (float) $grupo->sum('mto_imp_venta'),
            ])
            ->sortByDesc('fecha')
            ->values();

        return Inertia::render('sunat/resumenes/nueva', [
            'fechas' => $fechas,
        ]);
    }

    public function store(Request $request, SummaryService $service): RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('emitir', [$tenant, 'resumen']);

        $data = $request->validate([
            'fecha_resumen' => 'required|date',
        ]);

        $resultado = $service->crear($tenant, $data['fecha_resumen'], null, true);

        if (! $resultado['ok']) {
            return back()->with('error', $resultado['error'] ?? implode(' ', $resultado['errores'] ?? []));
        }

        return redirect()->route('sunat.historial')
            ->with('success', 'Resumen diario enviado a SUNAT: '.$resultado['meta']['identifier']);
    }
}

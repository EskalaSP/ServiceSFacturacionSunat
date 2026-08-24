<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Jobs\SendDocumentToSunat;
use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\DispatchGuide;
use App\Models\Invoice;
use App\Models\Perception;
use App\Models\Retention;
use App\Models\Summary;
use App\Models\VoidedDocument;
use App\Services\Documents\DocumentoListingService;
use App\Services\Documents\SummaryService;
use App\Services\Documents\VoidedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HistorialController extends Controller
{
    public function index(Request $request, DocumentoListingService $listing): Response|\Illuminate\Http\RedirectResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actual();

        if (! $tenant || ! $tenant->sol_user) {
            return redirect()->route('sunat.configuracion');
        }

        $tipo = $request->input('tipo', 'todos');
        $filtros = [
            'estado' => $request->input('estado'),
            'desde' => $request->input('desde'),
            'hasta' => $request->input('hasta'),
            'cliente' => $request->input('cliente'),
        ];

        $data = $listing->listar($tenant, $tipo, $filtros);

        return Inertia::render('sunat/historial', [
            'documentos' => ['data' => $data, 'total' => count($data)],
            'filtros' => array_merge(['tipo' => $tipo], $filtros),
            'tenant' => ['environment' => $tenant->environment ?? 'beta'],
        ]);
    }

    public function pdf(string $tipo, int $id): mixed
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actualOFallar();
        $doc = $this->modelPara($tipo)::forTenant($tenant->id)->findOrFail($id);

        if (! empty($doc->pdf_path) && Storage::disk('public')->exists($doc->pdf_path)) {
            return Storage::disk('public')->download($doc->pdf_path, "{$doc->serie}-{$doc->correlativo}.pdf");
        }

        abort(404, 'PDF no disponible aún.');
    }

    public function xml(string $tipo, int $id): mixed
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actualOFallar();
        $doc = $this->modelPara($tipo)::forTenant($tenant->id)->findOrFail($id);

        if (! empty($doc->xml_path) && Storage::disk('public')->exists($doc->xml_path)) {
            return Storage::disk('public')->download($doc->xml_path, "{$doc->serie}-{$doc->correlativo}.xml");
        }

        abort(404, 'XML no disponible aún.');
    }

    public function cdr(string $tipo, int $id): mixed
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actualOFallar();
        $doc = $this->modelPara($tipo)::forTenant($tenant->id)->findOrFail($id);

        if (! empty($doc->cdr_path) && Storage::disk('public')->exists($doc->cdr_path)) {
            return Storage::disk('public')->download($doc->cdr_path, "R-{$doc->serie}-{$doc->correlativo}.zip");
        }

        abort(404, 'CDR no disponible aún.');
    }

    /**
     * Reenvía un comprobante a SUNAT (útil para pendientes/rechazados).
     * Reutiliza el mismo job de emisión que la API.
     */
    public function reenviar(string $tipo, int $id): RedirectResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actualOFallar();
        $model = $this->modelPara($tipo);
        $doc = $model::forTenant($tenant->id)->findOrFail($id);

        if (($doc->sunat_status ?? null) === 'aceptado') {
            return back()->with('error', 'Este comprobante ya fue aceptado por SUNAT; no se puede reenviar.');
        }

        $doc->update(['sunat_status' => 'pendiente', 'sunat_code' => null, 'sunat_description' => null]);
        SendDocumentToSunat::dispatch($model, $doc->id);
        $doc->update(['sunat_status' => 'enviado']);

        return back()->with('success', "Comprobante {$doc->serie}-{$doc->correlativo} reenviado a SUNAT.");
    }

    /**
     * Descarga la constancia de la anulación (XML enviado o CDR de SUNAT) del
     * comprobante: el Resumen Diario (RC) si es boleta, o la Comunicación de Baja (RA)
     * si es factura/nota. Así el emisor conserva el respaldo de la anulación.
     */
    public function descargarAnulacion(string $tipo, int $id, string $archivo): mixed
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actualOFallar();
        abort_unless(in_array($archivo, ['xml', 'cdr'], true), 404);

        $doc = $this->modelPara($tipo)::forTenant($tenant->id)->findOrFail($id);

        if ($tipo === '03') {
            $anulacion = Summary::where('tenant_id', $tenant->id)
                ->where('tipo', 'anulacion')
                ->whereJsonContains('document_ids', $doc->id)
                ->orderByDesc('id')
                ->first();
        } else {
            $anulacion = VoidedDocument::where('tenant_id', $tenant->id)
                ->where('identifier', 'like', 'RA-%')
                ->whereJsonContains('detalles', [
                    'tipo_documento' => $tipo,
                    'serie' => $doc->serie,
                    'correlativo' => (string) $doc->correlativo,
                ])
                ->orderByDesc('id')
                ->first();
        }

        if (! $anulacion) {
            abort(404, 'No se encontró el registro de anulación de este comprobante.');
        }

        $path = $archivo === 'cdr' ? $anulacion->cdr_path : $anulacion->xml_path;

        if (empty($path) || ! Storage::disk('public')->exists($path)) {
            abort(404, strtoupper($archivo).' de la anulación no disponible aún.');
        }

        $ext = $archivo === 'cdr' ? 'zip' : 'xml';
        $prefijo = $archivo === 'cdr' ? 'R-' : '';

        return Storage::disk('public')->download($path, "{$prefijo}{$anulacion->identifier}.{$ext}");
    }

    /**
     * Anula un comprobante por el mecanismo que SUNAT exige según su tipo:
     *   - Boleta (03)            → Resumen Diario de anulación (RC).
     *   - Factura / notas (01/07/08) → Comunicación de Baja (RA).
     *
     * No cambia el estado a 'anulado' directamente: marca 'anulacion_en_proceso'
     * y encola el envío. El estado final lo fija el job al procesar el ticket.
     */
    public function anular(Request $request, string $tipo, int $id, VoidedService $voidedService, SummaryService $summaryService): RedirectResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actualOFallar();
        Gate::authorize('emitir', [$tenant, 'anulacion']);

        $data = $request->validate([
            'motivo' => 'required|string|min:3|max:255',
        ]);

        $doc = $this->modelPara($tipo)::forTenant($tenant->id)->findOrFail($id);

        if (($doc->sunat_status ?? null) !== 'aceptado') {
            return back()->with('error', 'Solo se pueden anular comprobantes aceptados por SUNAT (estado actual: '.($doc->sunat_status ?? 'desconocido').').');
        }

        $fechaEmision = optional($doc->fecha_emision)->format('Y-m-d') ?? (string) $doc->fecha_emision;
        $userId = $request->user()?->id;

        // ── Boletas: Resumen Diario de anulación ──
        if ($tipo === '03') {
            $resultado = $summaryService->crear(
                $tenant,
                $fechaEmision,
                [['id' => $doc->id, 'motivo' => $data['motivo']]],
                true,
                $data['motivo'],
                $userId,
            );

            if (! ($resultado['ok'] ?? false)) {
                return back()->with('error', $resultado['error'] ?? implode(' ', $resultado['errores'] ?? ['No se pudo anular la boleta.']));
            }

            return back()->with('success', 'Anulación enviada por Resumen Diario ('.$resultado['meta']['identifier'].'). El comprobante quedará anulado cuando SUNAT procese el ticket.');
        }

        // ── Facturas y notas: Comunicación de Baja ──
        $detalle = [
            'tipo_documento' => $tipo,
            'serie' => $doc->serie,
            'correlativo' => (string) $doc->correlativo,
            'motivo' => $data['motivo'],
        ];

        $resultado = $voidedService->crear($tenant, $fechaEmision, null, [$detalle], true, $data['motivo'], $userId);

        if (! ($resultado['ok'] ?? false)) {
            return back()->with('error', 'No se puede anular: '.implode(' ', $resultado['errores'] ?? ['error desconocido']));
        }

        return back()->with('success', 'Comunicación de baja enviada a SUNAT ('.$resultado['voided']->identifier.'). El comprobante quedará anulado cuando SUNAT procese el ticket.');
    }

    /** @return class-string */
    private function modelPara(string $tipo): string
    {
        return match ($tipo) {
            '03' => Boleta::class,
            '07' => CreditNote::class,
            '08' => DebitNote::class,
            '09', '31' => DispatchGuide::class,
            '20' => Retention::class,
            '40' => Perception::class,
            default => Invoice::class,
        };
    }
}

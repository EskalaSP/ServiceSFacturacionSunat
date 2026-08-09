<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\V1\SummaryController;
use App\Http\Controllers\Api\V1\VoidedController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSummaryRequest;
use App\Http\Requests\Api\V1\StoreVoidedRequest;
use App\Http\Resources\Api\V1\BoletaResource;
use App\Http\Resources\Api\V1\CreditNoteResource;
use App\Http\Resources\Api\V1\DebitNoteResource;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\DispatchGuide;
use App\Models\Invoice;
use App\Models\Perception;
use App\Models\Quotation;
use App\Models\Retention;
use App\Models\SaleNote;
use App\Models\Summary;
use App\Models\Tenant;
use App\Models\VoidedDocument;
use App\Jobs\SendDocumentToSunat;
use App\Services\Admin\EmpresaComprobantesQuery;
use App\Services\Pdf\PdfFormatConfig;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

/**
 * Vista de administrador: TODOS los comprobantes de una empresa (tenant), unificados.
 * Protegido por el middleware 'admin' (solo usuarios is_admin). El admin puede ver
 * cualquier empresa.
 */
class EmpresaComprobanteController extends Controller
{
    /** tipo → clase de modelo, para resolver descargas. */
    private const MODELOS = [
        '01' => Invoice::class,
        '03' => Boleta::class,
        '07' => CreditNote::class,
        '08' => DebitNote::class,
        '09' => DispatchGuide::class,
        '31' => DispatchGuide::class,
        '20' => Retention::class,
        '40' => Perception::class,
        'RC' => Summary::class,
        'RA' => VoidedDocument::class,
        'COT' => Quotation::class,
        'NV' => SaleNote::class,
    ];

    public function index(Request $request, Tenant $tenant, EmpresaComprobantesQuery $query)
    {
        $filtros = $request->only([
            'tipo', 'estado', 'serie', 'sucursal_id',
            'fecha_desde', 'fecha_hasta', 'buscar', 'sort', 'dir', 'per_page',
        ]);

        $sucursales = $tenant->sucursales()->get()->map(fn ($s) => [
            'id' => $s->id,
            'nombre' => $s->nombre ?? $s->sucursal_nombre ?? ('Sucursal ' . $s->id),
        ])->values();

        return Inertia::render('admin/empresas/comprobantes', [
            'empresa' => [
                'id' => $tenant->id,
                'ruc' => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
            ],
            'comprobantes' => $query->paginate($tenant->id, $filtros),
            'stats' => $query->stats($tenant->id),
            'filtros' => $filtros,
            'sucursales' => $sucursales,
            'tipos' => EmpresaComprobantesQuery::TIPOS,
        ]);
    }

    /** Devuelve el JSON completo del comprobante para el visor administrativo. */
    public function respuesta(Tenant $tenant, string $tipo, int $id)
    {
        $clase = self::MODELOS[$tipo] ?? abort(404, 'Tipo de comprobante desconocido.');

        /** @var Model $doc */
        $doc = $clase::where('tenant_id', $tenant->id)->findOrFail($id);

        if (in_array($tipo, ['01', '03', '07', '08'], true)) {
            $doc->load(['items', 'payments', 'client']);
        }

        $datos = match ($tipo) {
            '01' => (new InvoiceResource($doc))->toArray(request()),
            '03' => (new BoletaResource($doc))->toArray(request()),
            '07' => (new CreditNoteResource($doc))->toArray(request()),
            '08' => (new DebitNoteResource($doc))->toArray(request()),
            default => $doc->toArray(),
        };

        return response()->json([
            'estado' => 'exito',
            'mensaje' => 'OK',
            'datos' => $datos,
        ]);
    }

    /** Reenvía manualmente un comprobante electrónico que no fue aceptado. */
    public function reenviar(Tenant $tenant, string $tipo, int $id)
    {
        $modelos = ['01' => Invoice::class, '03' => Boleta::class, '07' => CreditNote::class, '08' => DebitNote::class];
        $clase = $modelos[$tipo] ?? abort(422, 'Este tipo de comprobante no admite reenvío manual.');
        $doc = $clase::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($doc->sunat_status === 'aceptado') {
            return response()->json(['estado' => 'error', 'mensaje' => 'El comprobante ya fue aceptado por SUNAT.'], 422);
        }

        $doc->update(['sunat_status' => 'pendiente', 'sunat_code' => null, 'sunat_description' => null, 'sunat_notes' => null]);
        SendDocumentToSunat::dispatch($clase, $doc->id);

        return response()->json([
            'estado' => 'exito',
            'mensaje' => 'Comprobante encolado para reenvío a SUNAT.',
            'datos' => ['estado' => 'pendiente'],
        ]);
    }

    /** Anula una factura/nota mediante RA o una boleta mediante resumen diario. */
    public function anular(Request $request, Tenant $tenant, string $tipo, int $id)
    {
        $doc = (self::MODELOS[$tipo] ?? abort(404))::where('tenant_id', $tenant->id)->findOrFail($id);
        $motivo = trim((string) $request->input('motivo', 'Anulación solicitada por el administrador'));
        abort_if($motivo === '', 422, 'El motivo de anulación es obligatorio.');

        $payload = $tipo === '03'
            ? ['fecha_resumen' => now()->format('Y-m-d'), 'anular' => [['id' => $doc->id, 'motivo' => $motivo]]]
            : ['fecha_generacion' => now()->format('Y-m-d'), 'fecha_comunicacion' => now()->format('Y-m-d'), 'detalles' => [[
                'tipo_documento' => $tipo, 'serie' => $doc->serie, 'correlativo' => (string) $doc->correlativo, 'motivo' => $motivo,
            ]]];

        $formRequest = $tipo === '03' ? StoreSummaryRequest::create('', 'POST', $payload) : StoreVoidedRequest::create('', 'POST', $payload);
        $formRequest->setContainer(app())->setRedirector(app('redirect'));
        $formRequest->validateResolved();
        $formRequest->attributes->set('tenant', $tenant);

        $response = $tipo === '03'
            ? app(SummaryController::class)->store($formRequest)
            : app(VoidedController::class)->store($formRequest);

        return $response;
    }

    /** Descarga XML / CDR / PDF de un comprobante de la empresa. */
    public function download(Tenant $tenant, string $tipo, int $id, string $formato, DocumentStorageService $storage)
    {
        $clase = self::MODELOS[$tipo] ?? abort(404, 'Tipo de comprobante desconocido.');

        /** @var Model $doc */
        $doc = $clase::where('tenant_id', $tenant->id)->findOrFail($id);
        $nombre = $doc->numero_completo ?? $doc->numero ?? $doc->identifier ?? (string) $id;

        return match ($formato) {
            'xml' => $this->stream($storage->getXmlContent($doc), 'application/xml', "{$nombre}.xml"),
            'cdr' => $this->stream($storage->getCdrContent($doc), 'application/zip', "R-{$nombre}.zip"),
            'pdf' => $this->pdf($doc, $tenant, $storage, $nombre),
            default => abort(404),
        };
    }

    private function pdf(Model $doc, Tenant $tenant, DocumentStorageService $storage, string $nombre)
    {
        $content = $storage->getPdfContent($doc);

        if (! $content) {
            try {
                $content = app(PdfGeneratorService::class)->generate($doc, $tenant, PdfFormatConfig::from('a4'));
            } catch (\Throwable) {
                $content = null;
            }
        }

        abort_if($content === null, 404, 'PDF no disponible para este comprobante.');

        return Response::make($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$nombre}.pdf\"",
        ]);
    }

    private function stream(?string $content, string $mime, string $filename)
    {
        abort_if($content === null, 404, 'Archivo no disponible para este comprobante.');

        return Response::make($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

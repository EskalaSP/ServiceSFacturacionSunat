<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

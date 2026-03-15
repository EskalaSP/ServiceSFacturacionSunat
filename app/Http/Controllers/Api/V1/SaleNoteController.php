<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateSaleNoteAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InternalDocumentResource;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CachesPdf;
use App\Models\SaleNote;
use App\Services\Pdf\PdfFormatConfig;
use App\Services\Pdf\PdfGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleNoteController extends Controller
{
    use ApiResponse, CachesPdf;

    public function store(Request $request, CreateSaleNoteAction $action): JsonResponse
    {
        $request->validate([
            'fecha_emision' => 'required|date',
            'fecha_vencimiento' => 'nullable|date',
            'tipo_moneda' => 'nullable|string|size:3',
            'forma_pago' => 'nullable|string|max:20',
            'observacion' => 'nullable|string',
            'cliente' => 'required|array',
            'cliente.tipo_doc' => 'required|string|size:1',
            'cliente.num_doc' => 'required|string|max:15',
            'cliente.razon_social' => 'required|string|max:255',
            'cliente.direccion' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.descripcion' => 'required|string|max:500',
            'items.*.cantidad' => 'required|numeric|min:0.0001',
            'items.*.precio_unitario' => 'required|numeric|min:0',
            'items.*.unidad' => 'required|string|max:5',
            'items.*.codigo' => 'nullable|string|max:50',
            'items.*.tip_afe_igv' => 'nullable|string|size:2',
        ]);

        $tenant = $request->get('tenant');

        try {
            $saleNote = $action->execute($tenant, $request->all());
            return $this->created(new InternalDocumentResource($saleNote), 'Nota de venta creada.');
        } catch (\Throwable $e) {
            return $this->error('Error al crear nota de venta: ' . $e->getMessage(), 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = SaleNote::with('items')
            ->forTenant($tenant->id)
            ->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->status($request->input('status'));
        }

        if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
            $query->fechas($request->input('fecha_desde'), $request->input('fecha_hasta'));
        }

        if ($request->has('client_num_doc')) {
            $query->where('client_num_doc', $request->input('client_num_doc'));
        }

        $docs = $query->paginate($request->integer('per_page', 15));

        return $this->success([
            'data' => InternalDocumentResource::collection($docs),
            'pagination' => [
                'current_page' => $docs->currentPage(),
                'last_page' => $docs->lastPage(),
                'per_page' => $docs->perPage(),
                'total' => $docs->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $saleNote = SaleNote::with(['items', 'payments'])->forTenant($tenant->id)->findOrFail($id);

        return $this->success(new InternalDocumentResource($saleNote));
    }

    public function pdf(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $saleNote = SaleNote::with('items')->forTenant($tenant->id)->findOrFail($id);
        $formatStr = $request->input('format', config('pdf.default_format', 'a4'));

        try {
            $format = PdfFormatConfig::from($formatStr);
        } catch (\ValueError) {
            return $this->error('Formato inválido. Opciones: a4, a5, ticket-80, ticket-58', 422);
        }

        $content = $this->getCachedPdfContent($saleNote, $formatStr);

        if (! $content) {
            $content = app(PdfGeneratorService::class)->generate($saleNote, $tenant, $format);
            $this->cachePdfContent($saleNote, $formatStr, $content);
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$saleNote->numero}.pdf\"",
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}

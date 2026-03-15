<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateQuotationAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InternalDocumentResource;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CachesPdf;
use App\Models\Quotation;
use App\Services\Pdf\PdfFormatConfig;
use App\Services\Pdf\PdfGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    use ApiResponse, CachesPdf;

    public function store(Request $request, CreateQuotationAction $action): JsonResponse
    {
        $request->validate([
            'fecha_emision' => 'required|date',
            'fecha_vencimiento' => 'nullable|date',
            'tipo_moneda' => 'nullable|string|size:3',
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
        ]);

        $tenant = $request->get('tenant');

        try {
            $quotation = $action->execute($tenant, $request->all());
            return $this->created(new InternalDocumentResource($quotation), 'Cotización creada.');
        } catch (\Throwable $e) {
            return $this->error('Error al crear cotización: ' . $e->getMessage(), 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = Quotation::with('items')
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
        $quotation = Quotation::with('items')->forTenant($tenant->id)->findOrFail($id);

        return $this->success(new InternalDocumentResource($quotation));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:' . implode(',', Quotation::$validStatuses),
        ]);

        $tenant = $request->get('tenant');
        $quotation = Quotation::forTenant($tenant->id)->findOrFail($id);
        $quotation->update(['status' => $request->input('status')]);

        return $this->success(new InternalDocumentResource($quotation->fresh(['items'])), 'Estado actualizado.');
    }

    public function pdf(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $quotation = Quotation::with('items')->forTenant($tenant->id)->findOrFail($id);
        $formatStr = $request->input('format', config('pdf.default_format', 'a4'));

        try {
            $format = PdfFormatConfig::from($formatStr);
        } catch (\ValueError) {
            return $this->error('Formato inválido. Opciones: a4, a5, ticket-80, ticket-58', 422);
        }

        $content = $this->getCachedPdfContent($quotation, $formatStr);

        if (! $content) {
            $content = app(PdfGeneratorService::class)->generate($quotation, $tenant, $format);
            $this->cachePdfContent($quotation, $formatStr, $content);
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$quotation->numero}.pdf\"",
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}

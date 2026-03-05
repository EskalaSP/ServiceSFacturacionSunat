<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateInvoiceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Traits\ApiResponse;
use App\Models\Invoice;
use App\Services\Pdf\PdfFormatConfig;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use ApiResponse;

    public function store(StoreInvoiceRequest $request, CreateInvoiceAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');

        try {
            $invoice = $action->execute($tenant, $request->validated());

            return $this->created(new InvoiceResource($invoice), 'Factura creada y encolada para envío a SUNAT.');
        } catch (\Throwable $e) {
            return $this->error('Error al crear factura: ' . $e->getMessage(), 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = Invoice::with('items')
            ->forTenant($tenant->id)
            ->orderByDesc('created_at');

        if ($request->has('sunat_status')) {
            $query->status($request->input('sunat_status'));
        }

        if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
            $query->fechas($request->input('fecha_desde'), $request->input('fecha_hasta'));
        }

        if ($request->has('serie')) {
            $query->where('serie', $request->input('serie'));
        }

        if ($request->has('client_num_doc')) {
            $query->where('client_num_doc', $request->input('client_num_doc'));
        }

        $invoices = $query->paginate($request->integer('per_page', 15));

        return $this->success([
            'data' => InvoiceResource::collection($invoices),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $invoice = Invoice::with('items')->forTenant($tenant->id)->findOrFail($id);

        return $this->success(new InvoiceResource($invoice));
    }

    public function xml(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $invoice = Invoice::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getXmlContent($invoice);

        if (! $content) {
            return $this->error('XML no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$invoice->numero_completo}.xml\"",
        ]);
    }

    public function cdr(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $invoice = Invoice::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getCdrContent($invoice);

        if (! $content) {
            return $this->error('CDR no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"R-{$invoice->numero_completo}.zip\"",
        ]);
    }

    public function pdf(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $invoice = Invoice::with('items')->forTenant($tenant->id)->findOrFail($id);
        $formatStr = $request->input('format', config('pdf.default_format', 'a4'));

        try {
            $format = PdfFormatConfig::from($formatStr);
        } catch (\ValueError) {
            return $this->error('Formato inválido. Opciones: a4, a5, ticket-80, ticket-58', 422);
        }

        // Serve cached PDF only for the stored format (a4) with no explicit format request
        if (! $request->has('format') && $invoice->pdf_path) {
            $storage = new DocumentStorageService();
            $content = $storage->getPdfContent($invoice);
            if ($content) {
                return response($content, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "inline; filename=\"{$invoice->numero_completo}.pdf\"",
                ]);
            }
        }

        $content = app(PdfGeneratorService::class)->generate($invoice, $tenant, $format);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$invoice->numero_completo}.pdf\"",
        ]);
    }
}

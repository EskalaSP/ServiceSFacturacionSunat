<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateInvoiceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CachesPdf;
use App\Jobs\SendDocumentToSunat;
use App\Models\Invoice;
use App\Services\Pdf\PdfFormatConfig;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use ApiResponse, CachesPdf;

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

        $query = Invoice::forTenant($tenant->id)
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

        if ($request->has('correlativo')) {
            $query->where('correlativo', $request->input('correlativo'));
            $query->with('items');
        }

        if ($request->has('client_num_doc')) {
            $query->where('client_num_doc', $request->input('client_num_doc'));
        }

        if ($request->has('sucursal_id')) {
            $query->where('sucursal_id', $request->input('sucursal_id'));
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->has('tipo_moneda')) {
            $query->where('tipo_moneda', $request->input('tipo_moneda'));
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
        $invoice = Invoice::with(['items', 'payments'])->forTenant($tenant->id)->findOrFail($id);

        return $this->success(new InvoiceResource($invoice));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $invoice = Invoice::with(['items', 'payments'])->forTenant($tenant->id)->findOrFail($id);

        if ($invoice->sunat_status === 'aceptado') {
            return $this->error('No se puede editar una factura aceptada por SUNAT.', 422);
        }

        $data = $request->all();

        return \DB::transaction(function () use ($invoice, $tenant, $data) {
            // Update client data if provided
            if (! empty($data['cliente'])) {
                $client = $data['cliente'];
                $invoice->fill([
                    'client_tipo_doc' => $client['tipo_doc'] ?? $invoice->client_tipo_doc,
                    'client_num_doc' => $client['num_doc'] ?? $invoice->client_num_doc,
                    'client_razon_social' => $client['razon_social'] ?? $invoice->client_razon_social,
                    'client_direccion' => $client['direccion'] ?? $invoice->client_direccion,
                ]);

                $clientResolver = new \App\Services\ClientResolverService();
                $clientResolver->resolve($tenant, [
                    'tipo_doc' => $invoice->client_tipo_doc,
                    'num_doc' => $invoice->client_num_doc,
                    'razon_social' => $invoice->client_razon_social,
                    'direccion' => $invoice->client_direccion,
                ]);
            }

            // Update simple fields if provided
            $simpleFields = ['fecha_vencimiento', 'tipo_operacion', 'tipo_moneda', 'forma_pago', 'observacion'];
            foreach ($simpleFields as $field) {
                if (array_key_exists($field, $data)) {
                    $invoice->{$field} = $data[$field];
                }
            }

            // Recalculate items if provided
            if (! empty($data['items'])) {
                $calcService = new \App\Services\DocumentCalculationService();
                $calculatedItems = $calcService->calculateItems($data['items']);
                $totals = $calcService->calculateTotals($calculatedItems, $data);

                $invoice->fill($totals);
                $leyendaTotal = !empty($data['percepcion']['mto_total'])
                    ? (float) $data['percepcion']['mto_total']
                    : $totals['mto_imp_venta'];
                $invoice->leyenda = $data['leyenda'] ?? $calcService->generateLeyenda(
                    $leyendaTotal,
                    $data['tipo_moneda'] ?? $invoice->tipo_moneda ?? 'PEN'
                );

                // Replace items
                $invoice->items()->delete();
                $invoice->items()->insert(array_map(fn ($item) => [
                    'invoice_id' => $invoice->id,
                    'codigo' => $item['codigo'],
                    'descripcion' => $item['descripcion'],
                    'unidad' => $item['unidad'],
                    'cantidad' => $item['cantidad'],
                    'mto_valor_unitario' => $item['mto_valor_unitario'],
                    'mto_valor_venta' => $item['mto_valor_venta'],
                    'mto_base_igv' => $item['mto_base_igv'],
                    'porcentaje_igv' => $item['porcentaje_igv'],
                    'igv' => $item['igv'],
                    'tip_afe_igv' => $item['tip_afe_igv'],
                    'isc' => $item['isc'],
                    'icbper' => $item['icbper'],
                    'total_impuestos' => $item['total_impuestos'],
                    'mto_precio_unitario' => $item['mto_precio_unitario'],
                    'descuento' => $item['descuento'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $calculatedItems));
            }

            // Reset SUNAT status and resend
            $invoice->fill([
                'sunat_status' => 'pendiente',
                'sunat_code' => null,
                'sunat_description' => null,
                'sunat_notes' => null,
            ]);
            $invoice->save();

            SendDocumentToSunat::dispatch(Invoice::class, $invoice->id);

            return $this->success(
                new InvoiceResource($invoice->load(['items', 'payments'])),
                'Factura actualizada y reenviada a SUNAT.'
            );
        });
    }

    public function resend(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $invoice = Invoice::forTenant($tenant->id)->findOrFail($id);

        if ($invoice->sunat_status === 'aceptado') {
            return $this->error('Esta factura ya fue aceptada por SUNAT.', 422);
        }

        $invoice->update([
            'sunat_status' => 'pendiente',
            'sunat_code' => null,
            'sunat_description' => null,
        ]);

        SendDocumentToSunat::dispatch(Invoice::class, $invoice->id);

        return $this->success(
            new InvoiceResource($invoice->fresh()),
            'Factura reenviada a SUNAT.'
        );
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
        $invoice = Invoice::with(['items', 'payments'])->forTenant($tenant->id)->findOrFail($id);
        $formatStr = $request->input('format', config('pdf.default_format', 'a4'));

        try {
            $format = PdfFormatConfig::from($formatStr);
        } catch (\ValueError) {
            return $this->error('Formato inválido. Opciones: a4, a5, ticket-80, ticket-58', 422);
        }

        // Try cached PDF first (any format)
        $content = $this->getCachedPdfContent($invoice, $formatStr);

        if (! $content) {
            $content = app(PdfGeneratorService::class)->generate($invoice, $tenant, $format);
            $this->cachePdfContent($invoice, $formatStr, $content);
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$invoice->numero_completo}.pdf\"",
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}

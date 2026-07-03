<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateInvoiceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\AppliesDocumentFilters;
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
    use ApiResponse, AppliesDocumentFilters, CachesPdf;

    public function store(StoreInvoiceRequest $request, CreateInvoiceAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');
        $enviarAuto = $request->boolean('enviar_automatico', true);

        try {
            $invoice = $action->execute($tenant, $request->validated(), $enviarAuto);

            $msg = $enviarAuto
                ? 'Factura creada y encolada para envío a SUNAT.'
                : 'Factura creada en estado pendiente. Use POST /facturas/{id}/enviar para enviarla a SUNAT.';

            return $this->created(new InvoiceResource($invoice), $msg);
        } catch (\Throwable $e) {
            return $this->error('Error al crear factura: ' . $e->getMessage(), 500);
        }
    }

    public function masivo(Request $request, CreateInvoiceAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');

        $items = $request->input('facturas');

        if (! is_array($items) || empty($items)) {
            return $this->error('El campo "facturas" debe ser un array con al menos 1 elemento.', 422);
        }

        $limit = 100;
        if (count($items) > $limit) {
            return $this->error("Máximo {$limit} facturas por envío masivo.", 422);
        }

        $creadas  = [];
        $errores  = [];

        foreach ($items as $index => $data) {
            // Validar campos mínimos por item
            $missing = [];
            foreach (['serie', 'fecha_emision', 'cliente', 'items'] as $campo) {
                if (empty($data[$campo])) {
                    $missing[] = $campo;
                }
            }
            if (! empty($missing)) {
                $errores[] = [
                    'indice'  => $index,
                    'mensaje' => 'Campos requeridos faltantes: ' . implode(', ', $missing),
                    'data'    => $data,
                ];
                continue;
            }

            try {
                $invoice = $action->execute($tenant, $data, true);
                $creadas[] = [
                    'indice'          => $index,
                    'id'              => $invoice->id,
                    'numero_completo' => $invoice->numero_completo,
                    'total'           => $invoice->mto_imp_venta,
                    'estado_sunat'    => $invoice->sunat_status,
                ];
            } catch (\Throwable $e) {
                $errores[] = [
                    'indice'  => $index,
                    'mensaje' => $e->getMessage(),
                    'data'    => $data,
                ];
            }
        }

        $totalCreadas = count($creadas);
        $totalErrores = count($errores);

        return $this->success([
            'resumen' => [
                'total_enviadas'  => count($items),
                'creadas'         => $totalCreadas,
                'errores'         => $totalErrores,
            ],
            'facturas' => $creadas,
            'errores'  => $errores,
        ], $totalCreadas > 0
            ? "{$totalCreadas} factura(s) creadas y encoladas para envío a SUNAT."
            : 'Ninguna factura fue creada. Revisa los errores.',
            $totalCreadas > 0 ? 201 : 422
        );
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = Invoice::forTenant($tenant->id);

        // Cargar relaciones solicitadas (?con=items,payments)
        if ($request->filled('con')) {
            $permitidas = ['items', 'payments'];
            $relaciones = array_intersect(
                explode(',', (string) $request->input('con')),
                $permitidas
            );
            if (! empty($relaciones)) {
                $query->with($relaciones);
            }
        }

        // Cargar items automáticamente cuando se filtra por correlativo exacto
        if ($request->filled('correlativo')) {
            $query->with('items');
        }

        $query = $this->applyDocumentFilters($query, $request);

        $perPage = min((int) $request->input('por_pagina', 15), 100);
        $invoices = $query->paginate($perPage);

        return $this->success([
            'datos' => InvoiceResource::collection($invoices),
            'paginacion' => [
                'pagina_actual' => $invoices->currentPage(),
                'ultima_pagina' => $invoices->lastPage(),
                'por_pagina' => $invoices->perPage(),
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
            return $this->errorAccionable(
                'No se puede editar una factura aceptada por SUNAT. Emita una Nota de Crédito para corregir o anular.',
                'documento_aceptado_no_editable',
                [
                    'operacion' => 'emitir_nota_credito',
                    'endpoint' => 'POST /api/v1/notas-credito',
                    'doc_afectado' => [
                        'tipo' => '01',
                        'serie' => $invoice->serie,
                        'correlativo' => $invoice->correlativo,
                    ],
                ],
            );
        }

        $data = $request->all();

        return \DB::transaction(function () use ($invoice, $tenant, $data) {
            // Actualizar datos del cliente si se proporcionan
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

            // Actualizar campos simples si se proporcionan
            $simpleFields = ['fecha_vencimiento', 'tipo_operacion', 'tipo_moneda', 'forma_pago', 'observacion'];
            foreach ($simpleFields as $field) {
                if (array_key_exists($field, $data)) {
                    $invoice->{$field} = $data[$field];
                }
            }

            // Recalcular ítems si se proporcionan
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

                // Reemplazar ítems
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

            // Reiniciar estado SUNAT y reenviar
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
        return $this->enviar($request, $id);
    }

    public function enviar(Request $request, int $id): JsonResponse
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
        $invoice->update(['sunat_status' => 'enviado']);

        return $this->success(
            new InvoiceResource($invoice->fresh()),
            'Factura enviada a SUNAT.'
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

    public function pdf(Request $request, int|string $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $invoice = Invoice::with(['items', 'payments'])->forTenant($tenant->id)->findOrFail((int) $id);
        $formatStr = $request->input('formato', $request->input('format', config('pdf.default_format', 'a4')));

        try {
            $format = PdfFormatConfig::from($formatStr);
        } catch (\ValueError) {
            return $this->error('Formato inválido. Opciones: a4, a5, ticket-80, ticket-58', 422);
        }

        // Intentar PDF en caché primero (cualquier formato)
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

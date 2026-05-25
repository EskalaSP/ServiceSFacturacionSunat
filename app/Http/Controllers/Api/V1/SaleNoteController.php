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
            'cliente.email' => 'nullable|email',
            'cliente.telefono' => 'nullable|string|max:20',
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

        if ($request->has('estado')) {
            $query->status($request->input('estado'));
        }

        if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
            $query->fechas($request->input('fecha_desde'), $request->input('fecha_hasta'));
        }

        if ($request->has('client_num_doc')) {
            $query->where('client_num_doc', $request->input('client_num_doc'));
        }

        $docs = $query->paginate($request->integer('por_pagina', 15));

        return $this->success([
            'datos' => InternalDocumentResource::collection($docs),
            'paginacion' => [
                'pagina_actual' => $docs->currentPage(),
                'ultima_pagina' => $docs->lastPage(),
                'por_pagina' => $docs->perPage(),
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

    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $saleNote = SaleNote::with(['items', 'payments'])->forTenant($tenant->id)->findOrFail($id);

        if ($saleNote->status === 'anulada') {
            return $this->error('No se puede editar una nota de venta anulada.', 422);
        }

        $data = $request->all();

        return \DB::transaction(function () use ($saleNote, $tenant, $data) {
            // Actualizar datos del cliente si se proporcionan
            if (! empty($data['cliente'])) {
                $client = $data['cliente'];
                $saleNote->fill([
                    'client_tipo_doc' => $client['tipo_doc'] ?? $saleNote->client_tipo_doc,
                    'client_num_doc' => $client['num_doc'] ?? $saleNote->client_num_doc,
                    'client_razon_social' => $client['razon_social'] ?? $saleNote->client_razon_social,
                    'client_direccion' => $client['direccion'] ?? $saleNote->client_direccion,
                ]);

                $clientResolver = new \App\Services\ClientResolverService();
                $clientResolver->resolve($tenant, [
                    'tipo_doc' => $saleNote->client_tipo_doc,
                    'num_doc' => $saleNote->client_num_doc,
                    'razon_social' => $saleNote->client_razon_social,
                    'direccion' => $saleNote->client_direccion,
                ]);
            }

            // Actualizar campos simples si se proporcionan
            $simpleFields = ['fecha_emision', 'fecha_vencimiento', 'tipo_moneda', 'forma_pago', 'observacion'];
            foreach ($simpleFields as $field) {
                if (array_key_exists($field, $data)) {
                    $saleNote->{$field} = $data[$field];
                }
            }

            // Recalcular ítems si se proporcionan
            if (! empty($data['items'])) {
                $calcService = new \App\Services\DocumentCalculationService();
                $calculatedItems = $calcService->calculateItems($data['items']);
                $totals = $calcService->calculateTotals($calculatedItems, $data);

                $saleNote->fill($totals);

                // Reemplazar ítems
                $saleNote->items()->delete();
                $saleNote->items()->insert(array_map(fn ($item) => [
                    'internal_document_id' => $saleNote->id,
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

                // Recalcular estado de pago si el total cambió
                $totalPagado = $saleNote->payments->sum('monto');
                $nuevoTotal = $totals['mto_imp_venta'];
                $saleNote->monto_pagado = $totalPagado;
                if ($totalPagado <= 0) {
                    $saleNote->payment_status = 'pendiente';
                } elseif ($totalPagado >= $nuevoTotal) {
                    $saleNote->payment_status = 'pagado';
                } else {
                    $saleNote->payment_status = 'parcial';
                }
            }

            // Limpiar PDF en caché
            $saleNote->pdf_path = null;
            $saleNote->save();

            return $this->success(
                new InternalDocumentResource($saleNote->load(['items', 'payments'])),
                'Nota de venta actualizada.'
            );
        });
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

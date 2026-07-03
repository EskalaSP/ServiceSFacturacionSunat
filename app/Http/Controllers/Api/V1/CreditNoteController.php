<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateCreditNoteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCreditNoteRequest;
use App\Http\Resources\Api\V1\CreditNoteResource;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CachesPdf;
use App\Jobs\SendDocumentToSunat;
use App\Models\CreditNote;
use App\Services\Pdf\PdfFormatConfig;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditNoteController extends Controller
{
    use ApiResponse, CachesPdf;

    public function store(StoreCreditNoteRequest $request, CreateCreditNoteAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');
        $enviarAuto = $request->boolean('enviar_automatico', true);

        try {
            $creditNote = $action->execute($tenant, $request->validated(), $enviarAuto);

            $msg = $enviarAuto
                ? 'Nota de crédito creada y encolada para envío a SUNAT.'
                : 'Nota de crédito creada en estado pendiente. Use POST /notas-credito/{id}/enviar para enviarla a SUNAT.';

            return $this->created(new CreditNoteResource($creditNote), $msg);
        } catch (\Throwable $e) {
            return $this->error('Error al crear nota de crédito: ' . $e->getMessage(), 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = CreditNote::forTenant($tenant->id)
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

        if ($request->has('sucursal_id')) {
            $query->where('sucursal_id', $request->input('sucursal_id'));
        }

        if ($request->has('tipo_moneda')) {
            $query->where('tipo_moneda', $request->input('tipo_moneda'));
        }

        $creditNotes = $query->paginate($request->integer('por_pagina', 15));

        return $this->success([
            'datos' => CreditNoteResource::collection($creditNotes),
            'paginacion' => [
                'pagina_actual' => $creditNotes->currentPage(),
                'ultima_pagina' => $creditNotes->lastPage(),
                'por_pagina' => $creditNotes->perPage(),
                'total' => $creditNotes->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $creditNote = CreditNote::with('items')->forTenant($tenant->id)->findOrFail($id);

        return $this->success(new CreditNoteResource($creditNote));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $creditNote = CreditNote::with('items')->forTenant($tenant->id)->findOrFail($id);

        if ($creditNote->sunat_status === 'aceptado') {
            return $this->errorAccionable(
                'No se puede editar una nota de crédito aceptada por SUNAT. Si la NC es errónea, anúlela por comunicación de baja.',
                'documento_aceptado_no_editable',
                [
                    'operacion' => 'anular_por_comunicacion_baja',
                    'endpoint' => 'POST /api/v1/anulaciones',
                    'doc_afectado' => [
                        'tipo' => '07',
                        'serie' => $creditNote->serie,
                        'correlativo' => $creditNote->correlativo,
                    ],
                ],
            );
        }

        $data = $request->all();

        return \DB::transaction(function () use ($creditNote, $tenant, $data) {
            // Actualizar datos del cliente si se proporcionan
            if (! empty($data['cliente'])) {
                $client = $data['cliente'];
                $creditNote->fill([
                    'client_tipo_doc' => $client['tipo_doc'] ?? $creditNote->client_tipo_doc,
                    'client_num_doc' => $client['num_doc'] ?? $creditNote->client_num_doc,
                    'client_razon_social' => $client['razon_social'] ?? $creditNote->client_razon_social,
                    'client_direccion' => $client['direccion'] ?? $creditNote->client_direccion,
                ]);

                $clientResolver = new \App\Services\ClientResolverService();
                $clientResolver->resolve($tenant, [
                    'tipo_doc' => $creditNote->client_tipo_doc,
                    'num_doc' => $creditNote->client_num_doc,
                    'razon_social' => $creditNote->client_razon_social,
                    'direccion' => $creditNote->client_direccion,
                ]);
            }

            // Actualizar campos simples si se proporcionan
            $simpleFields = ['tipo_moneda', 'observacion', 'doc_afectado_tipo', 'doc_afectado_serie', 'doc_afectado_correlativo', 'cod_motivo', 'des_motivo'];
            foreach ($simpleFields as $field) {
                if (array_key_exists($field, $data)) {
                    $creditNote->{$field} = $data[$field];
                }
            }

            // Recalcular ítems si se proporcionan
            if (! empty($data['items'])) {
                $calcService = new \App\Services\DocumentCalculationService();
                $calculatedItems = $calcService->calculateItems($data['items']);
                $totals = $calcService->calculateTotals($calculatedItems, $data);

                $creditNote->fill($totals);
                $creditNote->leyenda = $data['leyenda'] ?? $calcService->generateLeyenda(
                    $totals['mto_imp_venta'],
                    $data['tipo_moneda'] ?? $creditNote->tipo_moneda ?? 'PEN'
                );

                // Reemplazar ítems
                $creditNote->items()->delete();
                $creditNote->items()->insert(array_map(fn ($item) => [
                    'credit_note_id' => $creditNote->id,
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
                    'descuentos' => isset($item['descuentos']) ? json_encode($item['descuentos']) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $calculatedItems));
            }

            // Reiniciar estado SUNAT y reenviar
            $creditNote->fill([
                'sunat_status' => 'pendiente',
                'sunat_code' => null,
                'sunat_description' => null,
                'sunat_notes' => null,
            ]);
            $creditNote->save();

            SendDocumentToSunat::dispatch(CreditNote::class, $creditNote->id);

            return $this->success(
                new CreditNoteResource($creditNote->load('items')),
                'Nota de crédito actualizada y reenviada a SUNAT.'
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
        $creditNote = CreditNote::forTenant($tenant->id)->findOrFail($id);

        if ($creditNote->sunat_status === 'aceptado') {
            return $this->error('Esta nota de crédito ya fue aceptada por SUNAT.', 422);
        }

        $creditNote->update([
            'sunat_status' => 'pendiente',
            'sunat_code' => null,
            'sunat_description' => null,
        ]);

        SendDocumentToSunat::dispatch(CreditNote::class, $creditNote->id);
        $creditNote->update(['sunat_status' => 'enviado']);

        return $this->success(
            new CreditNoteResource($creditNote->fresh()),
            'Nota de crédito enviada a SUNAT.'
        );
    }

    public function xml(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $creditNote = CreditNote::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getXmlContent($creditNote);

        if (! $content) {
            return $this->error('XML no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$creditNote->numero_completo}.xml\"",
        ]);
    }

    public function cdr(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $creditNote = CreditNote::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getCdrContent($creditNote);

        if (! $content) {
            return $this->error('CDR no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"R-{$creditNote->numero_completo}.zip\"",
        ]);
    }

    public function pdf(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $creditNote = CreditNote::with('items')->forTenant($tenant->id)->findOrFail($id);
        $formatStr = $request->input('format', config('pdf.default_format', 'a4'));

        try {
            $format = PdfFormatConfig::from($formatStr);
        } catch (\ValueError) {
            return $this->error('Formato inválido. Opciones: a4, a5, ticket-80, ticket-58', 422);
        }

        $content = $this->getCachedPdfContent($creditNote, $formatStr);

        if (! $content) {
            $content = app(PdfGeneratorService::class)->generate($creditNote, $tenant, $format);
            $this->cachePdfContent($creditNote, $formatStr, $content);
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$creditNote->numero_completo}.pdf\"",
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}

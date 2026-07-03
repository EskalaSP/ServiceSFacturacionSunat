<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateDebitNoteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDebitNoteRequest;
use App\Http\Resources\Api\V1\DebitNoteResource;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CachesPdf;
use App\Jobs\SendDocumentToSunat;
use App\Models\DebitNote;
use App\Services\Pdf\PdfFormatConfig;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DebitNoteController extends Controller
{
    use ApiResponse, CachesPdf;

    public function store(StoreDebitNoteRequest $request, CreateDebitNoteAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');
        $enviarAuto = $request->boolean('enviar_automatico', true);

        try {
            $debitNote = $action->execute($tenant, $request->validated(), $enviarAuto);

            $msg = $enviarAuto
                ? 'Nota de débito creada y encolada para envío a SUNAT.'
                : 'Nota de débito creada en estado pendiente. Use POST /notas-debito/{id}/enviar para enviarla a SUNAT.';

            return $this->created(new DebitNoteResource($debitNote), $msg);
        } catch (\Throwable $e) {
            return $this->error('Error al crear nota de débito: ' . $e->getMessage(), 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = DebitNote::forTenant($tenant->id)
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

        $debitNotes = $query->paginate($request->integer('por_pagina', 15));

        return $this->success([
            'datos' => DebitNoteResource::collection($debitNotes),
            'paginacion' => [
                'pagina_actual' => $debitNotes->currentPage(),
                'ultima_pagina' => $debitNotes->lastPage(),
                'por_pagina' => $debitNotes->perPage(),
                'total' => $debitNotes->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $debitNote = DebitNote::with('items')->forTenant($tenant->id)->findOrFail($id);

        return $this->success(new DebitNoteResource($debitNote));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $debitNote = DebitNote::with('items')->forTenant($tenant->id)->findOrFail($id);

        if ($debitNote->sunat_status === 'aceptado') {
            return $this->errorAccionable(
                'No se puede editar una nota de débito aceptada por SUNAT. Si la ND es errónea, anúlela por comunicación de baja.',
                'documento_aceptado_no_editable',
                [
                    'operacion' => 'anular_por_comunicacion_baja',
                    'endpoint' => 'POST /api/v1/anulaciones',
                    'doc_afectado' => [
                        'tipo' => '08',
                        'serie' => $debitNote->serie,
                        'correlativo' => $debitNote->correlativo,
                    ],
                ],
            );
        }

        $data = $request->all();

        return \DB::transaction(function () use ($debitNote, $tenant, $data) {
            // Actualizar datos del cliente si se proporcionan
            if (! empty($data['cliente'])) {
                $client = $data['cliente'];
                $debitNote->fill([
                    'client_tipo_doc' => $client['tipo_doc'] ?? $debitNote->client_tipo_doc,
                    'client_num_doc' => $client['num_doc'] ?? $debitNote->client_num_doc,
                    'client_razon_social' => $client['razon_social'] ?? $debitNote->client_razon_social,
                    'client_direccion' => $client['direccion'] ?? $debitNote->client_direccion,
                ]);

                $clientResolver = new \App\Services\ClientResolverService();
                $clientResolver->resolve($tenant, [
                    'tipo_doc' => $debitNote->client_tipo_doc,
                    'num_doc' => $debitNote->client_num_doc,
                    'razon_social' => $debitNote->client_razon_social,
                    'direccion' => $debitNote->client_direccion,
                ]);
            }

            // Actualizar campos simples si se proporcionan
            $simpleFields = ['tipo_moneda', 'observacion', 'doc_afectado_tipo', 'doc_afectado_serie', 'doc_afectado_correlativo', 'cod_motivo', 'des_motivo'];
            foreach ($simpleFields as $field) {
                if (array_key_exists($field, $data)) {
                    $debitNote->{$field} = $data[$field];
                }
            }

            // Recalcular ítems si se proporcionan
            if (! empty($data['items'])) {
                $calcService = new \App\Services\DocumentCalculationService();
                $calculatedItems = $calcService->calculateItems($data['items']);
                $totals = $calcService->calculateTotals($calculatedItems, $data);

                $debitNote->fill($totals);
                $debitNote->leyenda = $data['leyenda'] ?? $calcService->generateLeyenda(
                    $totals['mto_imp_venta'],
                    $data['tipo_moneda'] ?? $debitNote->tipo_moneda ?? 'PEN'
                );

                // Reemplazar ítems
                $debitNote->items()->delete();
                $debitNote->items()->insert(array_map(fn ($item) => [
                    'debit_note_id' => $debitNote->id,
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
            $debitNote->fill([
                'sunat_status' => 'pendiente',
                'sunat_code' => null,
                'sunat_description' => null,
                'sunat_notes' => null,
            ]);
            $debitNote->save();

            SendDocumentToSunat::dispatch(DebitNote::class, $debitNote->id);

            return $this->success(
                new DebitNoteResource($debitNote->load('items')),
                'Nota de débito actualizada y reenviada a SUNAT.'
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
        $debitNote = DebitNote::forTenant($tenant->id)->findOrFail($id);

        if ($debitNote->sunat_status === 'aceptado') {
            return $this->error('Esta nota de débito ya fue aceptada por SUNAT.', 422);
        }

        $debitNote->update([
            'sunat_status' => 'pendiente',
            'sunat_code' => null,
            'sunat_description' => null,
        ]);

        SendDocumentToSunat::dispatch(DebitNote::class, $debitNote->id);
        $debitNote->update(['sunat_status' => 'enviado']);

        return $this->success(
            new DebitNoteResource($debitNote->fresh()),
            'Nota de débito enviada a SUNAT.'
        );
    }

    public function xml(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $debitNote = DebitNote::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getXmlContent($debitNote);

        if (! $content) {
            return $this->error('XML no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$debitNote->numero_completo}.xml\"",
        ]);
    }

    public function cdr(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $debitNote = DebitNote::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getCdrContent($debitNote);

        if (! $content) {
            return $this->error('CDR no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"R-{$debitNote->numero_completo}.zip\"",
        ]);
    }

    public function pdf(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $debitNote = DebitNote::with('items')->forTenant($tenant->id)->findOrFail($id);
        $formatStr = $request->input('format', config('pdf.default_format', 'a4'));

        try {
            $format = PdfFormatConfig::from($formatStr);
        } catch (\ValueError) {
            return $this->error('Formato inválido. Opciones: a4, a5, ticket-80, ticket-58', 422);
        }

        $content = $this->getCachedPdfContent($debitNote, $formatStr);

        if (! $content) {
            $content = app(PdfGeneratorService::class)->generate($debitNote, $tenant, $format);
            $this->cachePdfContent($debitNote, $formatStr, $content);
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$debitNote->numero_completo}.pdf\"",
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}

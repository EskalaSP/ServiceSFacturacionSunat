<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateBoletaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBoletaRequest;
use App\Http\Resources\Api\V1\BoletaResource;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\AppliesDocumentFilters;
use App\Http\Traits\CachesPdf;
use App\Jobs\SendDocumentToSunat;
use App\Models\Boleta;
use App\Services\Pdf\PdfFormatConfig;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoletaController extends Controller
{
    use ApiResponse, AppliesDocumentFilters, CachesPdf;

    public function store(StoreBoletaRequest $request, CreateBoletaAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');
        $soloRegistro = $request->boolean('solo_registro', false);
        $enviarAuto = $request->boolean('enviar_automatico', true);

        try {
            $boleta = $action->execute($tenant, $request->validated(), $soloRegistro, $enviarAuto);

            $message = match (true) {
                $soloRegistro    => 'Boleta registrada. Pendiente de envío vía resumen diario.',
                ! $enviarAuto    => 'Boleta creada en estado pendiente. Use POST /boletas/{id}/enviar para enviarla a SUNAT.',
                default          => 'Boleta creada y encolada para envío a SUNAT.',
            };

            return $this->created(new BoletaResource($boleta), $message);
        } catch (\Throwable $e) {
            return $this->error('Error al crear boleta: ' . $e->getMessage(), 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = Boleta::forTenant($tenant->id);

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
        $boletas = $query->paginate($perPage);

        return $this->success([
            'datos' => BoletaResource::collection($boletas),
            'paginacion' => [
                'pagina_actual' => $boletas->currentPage(),
                'ultima_pagina' => $boletas->lastPage(),
                'por_pagina' => $boletas->perPage(),
                'total' => $boletas->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $boleta = Boleta::with(['items', 'payments'])->forTenant($tenant->id)->findOrFail($id);

        return $this->success(new BoletaResource($boleta));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $boleta = Boleta::with(['items', 'payments'])->forTenant($tenant->id)->findOrFail($id);

        if ($boleta->sunat_status === 'aceptado') {
            return $this->errorAccionable(
                'No se puede editar una boleta aceptada por SUNAT. Emita una Nota de Crédito para corregir o anular.',
                'documento_aceptado_no_editable',
                [
                    'operacion' => 'emitir_nota_credito',
                    'endpoint' => 'POST /api/v1/notas-credito',
                    'doc_afectado' => [
                        'tipo' => '03',
                        'serie' => $boleta->serie,
                        'correlativo' => $boleta->correlativo,
                    ],
                ],
            );
        }

        $data = $request->all();

        return \DB::transaction(function () use ($boleta, $tenant, $data) {
            if (! empty($data['cliente'])) {
                $client = $data['cliente'];
                $boleta->fill([
                    'client_tipo_doc' => $client['tipo_doc'] ?? $boleta->client_tipo_doc,
                    'client_num_doc' => $client['num_doc'] ?? $boleta->client_num_doc,
                    'client_razon_social' => $client['razon_social'] ?? $boleta->client_razon_social,
                    'client_direccion' => $client['direccion'] ?? $boleta->client_direccion,
                ]);

                $clientResolver = new \App\Services\ClientResolverService();
                $clientResolver->resolve($tenant, [
                    'tipo_doc' => $boleta->client_tipo_doc,
                    'num_doc' => $boleta->client_num_doc,
                    'razon_social' => $boleta->client_razon_social,
                    'direccion' => $boleta->client_direccion,
                ]);
            }

            $simpleFields = ['fecha_vencimiento', 'tipo_operacion', 'tipo_moneda', 'forma_pago', 'observacion'];
            foreach ($simpleFields as $field) {
                if (array_key_exists($field, $data)) {
                    $boleta->{$field} = $data[$field];
                }
            }

            if (! empty($data['items'])) {
                $calcService = new \App\Services\DocumentCalculationService();
                $calculatedItems = $calcService->calculateItems($data['items']);
                $totals = $calcService->calculateTotals($calculatedItems, $data);

                $boleta->fill($totals);
                $leyendaTotal = !empty($data['percepcion']['mto_total'])
                    ? (float) $data['percepcion']['mto_total']
                    : $totals['mto_imp_venta'];
                $boleta->leyenda = $data['leyenda'] ?? $calcService->generateLeyenda(
                    $leyendaTotal,
                    $data['tipo_moneda'] ?? $boleta->tipo_moneda ?? 'PEN'
                );

                $boleta->items()->delete();
                $boleta->items()->insert(array_map(fn ($item) => [
                    'boleta_id' => $boleta->id,
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

            $boleta->fill([
                'sunat_status' => 'pendiente',
                'sunat_code' => null,
                'sunat_description' => null,
                'sunat_notes' => null,
            ]);
            $boleta->save();

            SendDocumentToSunat::dispatch(Boleta::class, $boleta->id);

            return $this->success(
                new BoletaResource($boleta->load(['items', 'payments'])),
                'Boleta actualizada y reenviada a SUNAT.'
            );
        });
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $boleta = Boleta::forTenant($tenant->id)->findOrFail($id);

        // Solo se pueden eliminar boletas que SUNAT no conoce (pendiente o rechazado sin hash)
        if (! in_array($boleta->sunat_status, ['pendiente', 'rechazado'], true) || $boleta->hash_cpe) {
            return $this->error(
                'No se puede eliminar una boleta ya aceptada por SUNAT. Debe anularla vía resumen RC.',
                422
            );
        }

        $boleta->items()->delete();
        $boleta->delete();

        return $this->success(null, 'Boleta eliminada localmente.');
    }

    public function resend(Request $request, int $id): JsonResponse
    {
        return $this->enviar($request, $id);
    }

    public function enviar(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $boleta = Boleta::forTenant($tenant->id)->findOrFail($id);

        if ($boleta->sunat_status === 'aceptado') {
            return $this->error('Esta boleta ya fue aceptada por SUNAT.', 422);
        }

        $boleta->update([
            'sunat_status' => 'pendiente',
            'sunat_code' => null,
            'sunat_description' => null,
        ]);

        SendDocumentToSunat::dispatch(Boleta::class, $boleta->id);
        $boleta->update(['sunat_status' => 'enviado']);

        return $this->success(
            new BoletaResource($boleta->fresh()),
            'Boleta enviada a SUNAT.'
        );
    }

    public function xml(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $boleta = Boleta::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getXmlContent($boleta);

        if (! $content) {
            return $this->error('XML no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$boleta->numero_completo}.xml\"",
        ]);
    }

    public function cdr(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $boleta = Boleta::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getCdrContent($boleta);

        if (! $content) {
            return $this->error('CDR no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"R-{$boleta->numero_completo}.zip\"",
        ]);
    }

    public function pdf(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $boleta = Boleta::with(['items', 'payments'])->forTenant($tenant->id)->findOrFail($id);
        $formatStr = $request->input('format', config('pdf.default_format', 'a4'));

        try {
            $format = PdfFormatConfig::from($formatStr);
        } catch (\ValueError) {
            return $this->error('Formato inválido. Opciones: a4, a5, ticket-80, ticket-58', 422);
        }

        // Intentar PDF en caché primero (cualquier formato)
        $content = $this->getCachedPdfContent($boleta, $formatStr);

        if (! $content) {
            $content = app(PdfGeneratorService::class)->generate($boleta, $tenant, $format);
            $this->cachePdfContent($boleta, $formatStr, $content);
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$boleta->numero_completo}.pdf\"",
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}

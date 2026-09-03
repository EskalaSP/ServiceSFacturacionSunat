<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreVoidedRequest;
use App\Http\Traits\ApiResponse;
use App\Jobs\SendVoidedToSunat;
use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\VoidedDocument;
use App\Services\Documents\VoidedService;
use App\Services\Greenter\GreenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoidedController extends Controller
{
    use ApiResponse;

    public function store(StoreVoidedRequest $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        $enviarAuto = $request->boolean('enviar_automatico', true);

        try {
            $validated = $request->validated();

            $fechaCom = $validated['fecha_comunicacion'] ?? \Illuminate\Support\Carbon::now('America/Lima')->format('Y-m-d');
            $validated['fecha_comunicacion'] = $fechaCom;

            // SUNAT rechaza la comunicación de baja (RA) para boletas (tipo 03).
            // Se anulan por resumen diario de anulación.
            foreach ($validated['detalles'] ?? [] as $detalle) {
                if (($detalle['tipo_documento'] ?? null) === '03') {
                    return response()->json([
                        'estado' => 'error',
                        'mensaje' => 'Las boletas no se anulan por comunicación de baja. Use POST /resumenes con {"anular":[{"id":..., "motivo":"..."}]}',
                        'codigo_error' => 'boletas_no_soportadas_en_ra',
                        'siguiente_accion' => [
                            'operacion' => 'anular_boleta_por_resumen_diario',
                            'endpoint' => 'POST /api/v1/resumenes',
                        ],
                    ], 422);
                }
            }

            $resultado = app(VoidedService::class)->crear(
                $tenant,
                $validated['fecha_generacion'],
                $fechaCom,
                $validated['detalles'],
                $enviarAuto,
            );

            if (! $resultado['ok']) {
                return response()->json([
                    'estado' => 'error',
                    'mensaje' => 'No se puede anular: '.implode(' ', $resultado['errores']),
                    'errores' => $resultado['errores'],
                ], 422);
            }

            $voided = $resultado['voided'];
            $correlativo = $voided->correlativo;
            $identifier = $voided->identifier;

            $message = $enviarAuto
                ? 'Comunicación de baja encolada para envío a SUNAT.'
                : 'Comunicación de baja creada en estado pendiente. Use POST /anulaciones/{id}/enviar para enviarla a SUNAT.';

            return response()->json([
                'estado' => 'exito',
                'mensaje' => $message,
                'datos' => [
                    'id_anulacion' => $voided->id,
                    'identifier' => $identifier,
                    'correlativo' => $correlativo,
                    'fecha_comunicacion' => $fechaCom,
                    'total_documentos' => count($validated['detalles']),
                    'estado_sunat' => $enviarAuto ? 'enviado' : 'pendiente',
                    'consulta_estado' => url("/api/v1/anulaciones/{$voided->id}/estado"),
                ],
            ], 201);
        } catch (\Throwable $e) {
            return $this->error('Error: '.$e->getMessage(), 500);
        }
    }

    private function updateOriginalDocuments(VoidedDocument $voided): void
    {
        $modelMap = [
            '01' => Invoice::class,
            '03' => Boleta::class,
            '07' => CreditNote::class,
            '08' => DebitNote::class,
        ];

        foreach ($voided->detalles ?? [] as $detalle) {
            $model = $modelMap[$detalle['tipo_documento'] ?? null] ?? null;
            if (! $model) {
                continue;
            }

            $model::where('tenant_id', $voided->tenant_id)
                ->where('serie', $detalle['serie'] ?? '')
                ->where('correlativo', $detalle['correlativo'] ?? '')
                ->update(['sunat_status' => 'anulado']);
        }
    }

    public function checkStatus(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $voided = VoidedDocument::where('tenant_id', $tenant->id)->findOrFail($id);

        // Si hay ticket y aún está enviado, consultar SUNAT en tiempo real
        if ($voided->ticket && $voided->sunat_status === 'enviado') {
            try {
                $service = new GreenterService($tenant);
                $result = $service->getStatus($voided->ticket);

                if ($result['success']) {
                    $accepted = $result['accepted'] ?? false;
                    $updateData = [
                        'sunat_status' => $accepted ? 'aceptado' : 'rechazado',
                        'sunat_code' => $result['code'] ?? null,
                        'sunat_description' => $result['description'] ?? null,
                        'sunat_notes' => $result['notes'] ?? null,
                    ];
                    $voided->update($updateData);
                    $voided->refresh();

                    if ($accepted) {
                        $this->updateOriginalDocuments($voided);
                    }
                } elseif (
                    is_numeric((string) ($result['error_code'] ?? ''))
                    && ! in_array($result['error_code'] ?? '', ['0', '187', 0, 187], true)
                ) {
                    // Error SUNAT definitivo (código 1xxx/2xxx/4xxx): actualizar en BD
                    $voided->update([
                        'sunat_status' => 'rechazado',
                        'sunat_code' => $result['error_code'],
                        'sunat_description' => $result['error_message'] ?? null,
                    ]);
                    $voided->refresh();
                }
                // Código 0/187 = aún procesando; error no numérico = red → sin cambios
            } catch (\Throwable) {
                // Si falla la conexión con SUNAT, devolver estado actual de BD
            }
        }

        return $this->buildResponse($voided);
    }

    public function enviar(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $voided = VoidedDocument::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($voided->sunat_status === 'aceptado') {
            return $this->error('Esta comunicación de baja ya fue aceptada por SUNAT.', 422);
        }

        // Detectar si es Reversion (RR-) o Voided/Anulación (RA-) por el identifier
        $isReversion = str_starts_with((string) $voided->identifier, 'RR-');

        $voided->update([
            'sunat_status' => 'pendiente',
            'sunat_code' => null,
            'sunat_description' => null,
        ]);

        if ($isReversion) {
            \App\Jobs\SendReversionToSunat::dispatch($voided->id);
        } else {
            SendVoidedToSunat::dispatch($voided->id);
        }
        $voided->update(['sunat_status' => 'enviado']);

        return response()->json([
            'estado' => 'exito',
            'mensaje' => $isReversion ? 'Reversión enviada a SUNAT.' : 'Comunicación de baja enviada a SUNAT.',
            'datos' => [
                'id_anulacion' => $voided->id,
                'identifier' => $voided->identifier,
                'estado_sunat' => 'enviado',
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $voided = VoidedDocument::where('tenant_id', $tenant->id)->findOrFail($id);

        return $this->buildResponse($voided);
    }

    private function buildResponse(VoidedDocument $voided): JsonResponse
    {
        return response()->json([
            'estado' => 'exito',
            'datos' => [
                'id_anulacion' => $voided->id,
                'identifier' => $voided->identifier,
                'correlativo' => $voided->correlativo,
                'ticket' => $voided->ticket,
                'fecha_generacion' => $voided->fecha_generacion?->format('Y-m-d'),
                'fecha_comunicacion' => $voided->fecha_comunicacion?->format('Y-m-d'),
                'estado_sunat' => $voided->sunat_status,
                'codigo_sunat' => $voided->sunat_code,
                'descripcion_sunat' => $voided->sunat_description,
                'notas_sunat' => $voided->sunat_notes,
                'total_documentos' => $voided->total_documentos,
                'detalles' => $voided->detalles,
                'creado_en' => $voided->created_at?->toIso8601String(),
                'actualizado_en' => $voided->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = VoidedDocument::where('tenant_id', $tenant->id);

        if ($request->filled('estado')) {
            $query->where('sunat_status', $request->input('estado'));
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_comunicacion', '>=', $request->input('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_comunicacion', '<=', $request->input('fecha_hasta'));
        }

        $anulaciones = $query->orderByDesc('id')->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'estado' => 'exito',
            'datos' => $anulaciones->items(),
            'meta' => [
                'pagina_actual' => $anulaciones->currentPage(),
                'por_pagina' => $anulaciones->perPage(),
                'total' => $anulaciones->total(),
                'ultima_pagina' => $anulaciones->lastPage(),
            ],
        ]);
    }
}

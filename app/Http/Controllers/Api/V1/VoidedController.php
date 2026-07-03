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
use App\Services\Greenter\GreenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VoidedController extends Controller
{
    use ApiResponse;

    public function store(StoreVoidedRequest $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        $enviarAuto = $request->boolean('enviar_automatico', true);

        try {
            $validated = $request->validated();

            $fechaCom = $validated['fecha_comunicacion'] ?? now()->format('Y-m-d');
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

            // Pre-validar contra las reglas de SUNAT antes de enviarles.
            $errors = $this->validateDetalles($tenant->id, $validated['detalles']);
            if (! empty($errors)) {
                return response()->json([
                    'estado' => 'error',
                    'mensaje' => 'No se puede anular: '.implode(' ', $errors),
                    'errores' => $errors,
                ], 422);
            }

            $fechaId = str_replace('-', '', $fechaCom);
            $lastCorrelativo = VoidedDocument::where('tenant_id', $tenant->id)
                ->where('fecha_comunicacion', $fechaCom)
                ->max('correlativo') ?? 0;

            $correlativo = str_pad((string) ((int) $lastCorrelativo + 1), 3, '0', STR_PAD_LEFT);
            $identifier = "RA-{$fechaId}-{$correlativo}";

            $voided = VoidedDocument::create([
                'tenant_id' => $tenant->id,
                'identifier' => $identifier,
                'correlativo' => $correlativo,
                'fecha_generacion' => $validated['fecha_generacion'],
                'fecha_comunicacion' => $fechaCom,
                'total_documentos' => count($validated['detalles']),
                'detalles' => $validated['detalles'],
                'sunat_status' => 'pendiente',
            ]);

            $this->markDocumentsAsProcessing($tenant->id, $validated['detalles']);

            if ($enviarAuto) {
                SendVoidedToSunat::dispatch($voided->id);
                $voided->update(['sunat_status' => 'enviado']);
            }

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
            return $this->error('Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Valida cada detalle antes de enviar a SUNAT:
     * - Documento original existe y pertenece al tenant
     * - Estado es aceptado por SUNAT
     * - Plazo 7 días calendario respecto a fecha_emision
     * - No tiene nota de crédito asociada (solo facturas)
     * - No hay otra comunicación de baja en proceso para el mismo documento
     *
     * @return array<int, string>  Lista de mensajes de error. Vacío = todo ok.
     */
    private function validateDetalles(int $tenantId, array $detalles): array
    {
        $errors = [];
        $hoy = Carbon::today('America/Lima');
        $limite = $hoy->copy()->subDays(7)->startOfDay();

        $modelMap = [
            '01' => Invoice::class,
            '03' => Boleta::class,
            '07' => CreditNote::class,
            '08' => DebitNote::class,
        ];

        foreach ($detalles as $detalle) {
            $tipo = $detalle['tipo_documento'] ?? null;
            $serie = $detalle['serie'] ?? null;
            $correlativo = $detalle['correlativo'] ?? null;

            if (! $tipo || ! $serie || ! $correlativo) {
                $errors[] = 'Cada detalle requiere tipo_documento, serie y correlativo.';
                continue;
            }

            $model = $modelMap[$tipo] ?? null;
            if (! $model) {
                $errors[] = "Tipo de documento {$tipo} no soportado para anulación.";
                continue;
            }

            $document = $model::where('tenant_id', $tenantId)
                ->where('serie', $serie)
                ->where('correlativo', $correlativo)
                ->first();

            $ref = "{$serie}-{$correlativo}";

            if (! $document) {
                $errors[] = "Documento {$ref} no existe.";
                continue;
            }

            $status = strtolower((string) $document->sunat_status);
            if ($status !== 'aceptado') {
                $errors[] = "Documento {$ref} no está aceptado por SUNAT (estado actual: {$status}).";
                continue;
            }

            // Plazo 7 días calendario respecto a la fecha de emisión real
            $fechaEmision = $document->fecha_emision instanceof Carbon
                ? $document->fecha_emision
                : Carbon::parse((string) $document->fecha_emision);

            if ($fechaEmision->lt($limite)) {
                $errors[] = "Documento {$ref} ya pasó el plazo de 7 días para anulación (emitido el {$fechaEmision->format('Y-m-d')}).";
                continue;
            }

            // Facturas: no deben tener NC asociada
            if ($tipo === '01') {
                $hasNC = CreditNote::where('tenant_id', $tenantId)
                    ->where('doc_afectado_tipo', '01')
                    ->where('doc_afectado_serie', $serie)
                    ->where('doc_afectado_correlativo', $correlativo)
                    ->exists();

                if ($hasNC) {
                    $errors[] = "La factura {$ref} tiene una nota de crédito asociada. Usa la NC en vez de anular la factura.";
                    continue;
                }
            }

            // No debe haber otra comunicación de baja en proceso para el mismo par
            $duplicate = VoidedDocument::where('tenant_id', $tenantId)
                ->whereIn('sunat_status', ['pendiente', 'enviado', 'aceptado'])
                ->whereJsonContains('detalles', [
                    'tipo_documento' => $tipo,
                    'serie' => $serie,
                    'correlativo' => $correlativo,
                ])
                ->first();

            if ($duplicate) {
                $errors[] = "Ya existe una comunicación de baja para {$ref} ({$duplicate->identifier}).";
                continue;
            }
        }

        return $errors;
    }

    private function markDocumentsAsProcessing(int $tenantId, array $detalles): void
    {
        $modelMap = [
            '01' => Invoice::class,
            '03' => Boleta::class,
            '07' => CreditNote::class,
            '08' => DebitNote::class,
        ];

        foreach ($detalles as $detalle) {
            $model = $modelMap[$detalle['tipo_documento']] ?? null;
            if (! $model) continue;

            $model::where('tenant_id', $tenantId)
                ->where('serie', $detalle['serie'])
                ->where('correlativo', $detalle['correlativo'])
                ->update(['sunat_status' => 'anulacion_en_proceso']);
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
            if (! $model) continue;

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
                        'sunat_status'      => $accepted ? 'aceptado' : 'rechazado',
                        'sunat_code'        => $result['code'] ?? null,
                        'sunat_description' => $result['description'] ?? null,
                        'sunat_notes'       => $result['notes'] ?? null,
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
                        'sunat_status'      => 'rechazado',
                        'sunat_code'        => $result['error_code'],
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
                'id_anulacion'     => $voided->id,
                'identifier'       => $voided->identifier,
                'correlativo'      => $voided->correlativo,
                'ticket'           => $voided->ticket,
                'fecha_generacion' => $voided->fecha_generacion?->format('Y-m-d'),
                'fecha_comunicacion' => $voided->fecha_comunicacion?->format('Y-m-d'),
                'estado_sunat'     => $voided->sunat_status,
                'codigo_sunat'     => $voided->sunat_code,
                'descripcion_sunat' => $voided->sunat_description,
                'notas_sunat'      => $voided->sunat_notes,
                'total_documentos' => $voided->total_documentos,
                'detalles'         => $voided->detalles,
                'creado_en'        => $voided->created_at?->toIso8601String(),
                'actualizado_en'   => $voided->updated_at?->toIso8601String(),
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

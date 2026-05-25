<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Jobs\SendReversionToSunat;
use App\Models\Perception;
use App\Models\Retention;
use App\Models\VoidedDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReversionController extends Controller
{
    use ApiResponse;

    /**
     * Crea una Reversión (RR) para anular Retenciones (20) o Percepciones (40).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_generacion' => 'required|date',
            'fecha_comunicacion' => 'sometimes|date',
            'detalles' => 'required|array|min:1',
            'detalles.*.tipo_documento' => 'required|string|in:20,40',
            'detalles.*.serie' => 'required|string|size:4',
            'detalles.*.correlativo' => 'required|string',
            'detalles.*.motivo' => 'required|string|max:255',
        ]);

        $tenant = $request->get('tenant');
        $validated = $request->all();
        $fechaCom = $validated['fecha_comunicacion'] ?? now()->format('Y-m-d');
        $enviarAuto = $request->boolean('enviar_automatico', true);

        // Validar que los documentos existan y estén aceptados
        $errors = $this->validateDetalles($tenant->id, $validated['detalles']);
        if (! empty($errors)) {
            return response()->json([
                'estado' => 'error',
                'mensaje' => 'No se puede anular: ' . implode(' ', $errors),
                'errores' => $errors,
            ], 422);
        }

        try {
            $fechaId = str_replace('-', '', $fechaCom);
            $lastCorrelativo = VoidedDocument::where('tenant_id', $tenant->id)
                ->where('identifier', 'like', "RR-{$fechaId}-%")
                ->max('correlativo') ?? 0;

            $correlativo = str_pad((string) ((int) $lastCorrelativo + 1), 3, '0', STR_PAD_LEFT);
            $identifier = "RR-{$fechaId}-{$correlativo}";

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

            // Marcar documentos como en proceso de anulación
            $this->markDocumentsAsProcessing($tenant->id, $validated['detalles']);

            if ($enviarAuto) {
                SendReversionToSunat::dispatch($voided->id);
                $voided->update(['sunat_status' => 'enviado']);
            }

            $message = $enviarAuto
                ? 'Reversión encolada para envío a SUNAT.'
                : 'Reversión creada en estado pendiente. Use POST /anulaciones/{id}/enviar para enviarla a SUNAT.';

            return response()->json([
                'estado' => 'exito',
                'mensaje' => $message,
                'datos' => [
                    'voided_id' => $voided->id,
                    'identifier' => $identifier,
                    'correlativo' => $correlativo,
                    'fecha_comunicacion' => $fechaCom,
                    'total_documentos' => count($validated['detalles']),
                    'sunat_status' => $enviarAuto ? 'enviado' : 'pendiente',
                    'consulta_estado' => url("/api/v1/voided/{$voided->id}/status"),
                ],
            ], 201);
        } catch (\Throwable $e) {
            return $this->error('Error: ' . $e->getMessage(), 500);
        }
    }

    private function validateDetalles(int $tenantId, array $detalles): array
    {
        $errors = [];

        $modelMap = [
            '20' => Retention::class,
            '40' => Perception::class,
        ];

        foreach ($detalles as $detalle) {
            $tipo = $detalle['tipo_documento'];
            $serie = $detalle['serie'];
            $correlativo = $detalle['correlativo'];

            $model = $modelMap[$tipo] ?? null;
            if (! $model) {
                $errors[] = "Tipo de documento {$tipo} no soportado para reversión.";
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

            if (strtolower((string) $document->sunat_status) !== 'aceptado') {
                $errors[] = "Documento {$ref} no está aceptado por SUNAT (estado: {$document->sunat_status}).";
                continue;
            }

            // Verificar que no exista una reversión duplicada en proceso
            $duplicate = VoidedDocument::where('tenant_id', $tenantId)
                ->where('identifier', 'like', 'RR-%')
                ->whereIn('sunat_status', ['pendiente', 'enviado', 'aceptado'])
                ->whereJsonContains('detalles', [
                    'tipo_documento' => $tipo,
                    'serie' => $serie,
                    'correlativo' => $correlativo,
                ])
                ->first();

            if ($duplicate) {
                $errors[] = "Ya existe una reversión para {$ref} ({$duplicate->identifier}).";
            }
        }

        return $errors;
    }

    private function markDocumentsAsProcessing(int $tenantId, array $detalles): void
    {
        $modelMap = [
            '20' => Retention::class,
            '40' => Perception::class,
        ];

        foreach ($detalles as $detalle) {
            $model = $modelMap[$detalle['tipo_documento']] ?? null;
            if (! $model) {
                continue;
            }

            $model::where('tenant_id', $tenantId)
                ->where('serie', $detalle['serie'])
                ->where('correlativo', $detalle['correlativo'])
                ->update(['sunat_status' => 'anulacion_en_proceso']);
        }
    }
}

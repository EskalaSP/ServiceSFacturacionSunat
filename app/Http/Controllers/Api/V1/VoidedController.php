<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreVoidedRequest;
use App\Http\Traits\ApiResponse;
use App\Jobs\SendVoidedToSunat;
use App\Models\VoidedDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoidedController extends Controller
{
    use ApiResponse;

    public function store(StoreVoidedRequest $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        try {
            $validated = $request->validated();

            $fechaCom = $validated['fecha_comunicacion'] ?? now()->format('Y-m-d');
            $validated['fecha_comunicacion'] = $fechaCom;

            $fechaId = str_replace('-', '', $fechaCom);
            $lastCorrelativo = VoidedDocument::where('tenant_id', $tenant->id)
                ->where('fecha_comunicacion', $fechaCom)
                ->max('correlativo') ?? 0;

            $correlativo = str_pad((int) $lastCorrelativo + 1, 3, '0', STR_PAD_LEFT);
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

            SendVoidedToSunat::dispatch($voided->id);
            $voided->update(['sunat_status' => 'enviado']);

            return response()->json([
                'success' => true,
                'message' => 'Comunicación de baja encolada para envío a SUNAT.',
                'data' => [
                    'voided_id' => $voided->id,
                    'identifier' => $identifier,
                    'correlativo' => $correlativo,
                    'fecha_comunicacion' => $fechaCom,
                    'total_documentos' => count($validated['detalles']),
                    'sunat_status' => 'enviado',
                    'consulta_estado' => url("/api/v1/voided/{$voided->id}/status"),
                ],
            ], 201);
        } catch (\Throwable $e) {
            return $this->error('Error: ' . $e->getMessage(), 500);
        }
    }

    public function checkStatus(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');

        $voided = VoidedDocument::where('tenant_id', $tenant->id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'voided_id' => $voided->id,
                'identifier' => $voided->identifier,
                'ticket' => $voided->ticket,
                'sunat_status' => $voided->sunat_status,
                'sunat_code' => $voided->sunat_code,
                'sunat_description' => $voided->sunat_description,
                'sunat_notes' => $voided->sunat_notes,
                'total_documentos' => $voided->total_documentos,
                'detalles' => $voided->detalles,
            ],
        ]);
    }
}

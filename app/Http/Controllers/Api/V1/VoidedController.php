<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateVoidedAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreVoidedRequest;
use App\Http\Traits\ApiResponse;
use App\Models\VoidedDocument;
use App\Services\Greenter\GreenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoidedController extends Controller
{
    use ApiResponse;

    public function store(StoreVoidedRequest $request, CreateVoidedAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');

        try {
            $validated = $request->validated();

            // Auto-generar fecha_comunicacion si no viene
            $fechaCom = $validated['fecha_comunicacion'] ?? now()->format('Y-m-d');
            $validated['fecha_comunicacion'] = $fechaCom;

            // Auto-generar correlativo: siguiente secuencial del día
            $fechaId = str_replace('-', '', $fechaCom);
            $lastCorrelativo = VoidedDocument::where('tenant_id', $tenant->id)
                ->where('fecha_comunicacion', $fechaCom)
                ->max('correlativo') ?? 0;

            $correlativo = str_pad((int) $lastCorrelativo + 1, 3, '0', STR_PAD_LEFT);
            $validated['correlativo'] = $correlativo;

            $identifier = "RA-{$fechaId}-{$correlativo}";

            $result = $action->execute($tenant, $validated);

            if ($result['success']) {
                VoidedDocument::create([
                    'tenant_id' => $tenant->id,
                    'identifier' => $identifier,
                    'correlativo' => $correlativo,
                    'fecha_generacion' => $validated['fecha_generacion'],
                    'fecha_comunicacion' => $fechaCom,
                    'total_documentos' => count($validated['detalles']),
                    'detalles' => $validated['detalles'],
                    'ticket' => $result['ticket'],
                    'sunat_status' => 'enviado',
                ]);

                return $this->created([
                    'identifier' => $identifier,
                    'correlativo' => $correlativo,
                    'ticket' => $result['ticket'],
                    'total_documentos' => count($validated['detalles']),
                    'message' => 'Comunicación de baja enviada. Use el ticket para consultar el estado.',
                ]);
            }

            return $this->error($result['error_message'] ?? 'Error al enviar comunicación de baja', 422);
        } catch (\Throwable $e) {
            return $this->error('Error: '.$e->getMessage(), 500);
        }
    }

    public function checkStatus(Request $request, string $ticket): JsonResponse
    {
        $tenant = $request->get('tenant');

        try {
            $service = new GreenterService($tenant);
            $result = $service->getStatus($ticket);

            $voided = VoidedDocument::where('tenant_id', $tenant->id)
                ->where('ticket', $ticket)
                ->first();

            if ($voided && $result['success']) {
                $voided->update([
                    'sunat_status' => ($result['accepted'] ?? false) ? 'aceptado' : 'rechazado',
                    'sunat_code' => $result['code'] ?? null,
                    'sunat_description' => $result['description'] ?? null,
                    'sunat_notes' => $result['notes'] ?? null,
                ]);
            }

            unset($result['cdr_zip']);

            return $this->success($result);
        } catch (\Throwable $e) {
            return $this->error('Error al consultar ticket: '.$e->getMessage(), 500);
        }
    }
}

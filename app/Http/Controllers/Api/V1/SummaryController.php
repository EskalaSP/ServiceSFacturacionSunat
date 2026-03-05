<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Jobs\SendSummaryToSunat;
use App\Models\Boleta;
use App\Models\Summary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SummaryController extends Controller
{
    use ApiResponse;

    /**
     * Generar y encolar resumen diario de boletas.
     *
     * Modos:
     * 1. Enviar boletas pendientes: POST { "fecha_resumen": "2026-03-05" }
     * 2. Anular boletas: POST { "fecha_resumen": "2026-03-05", "anular": [{"id": 15, "motivo": "..."}, ...] }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_resumen' => 'required|date',
            'anular' => 'nullable|array',
            'anular.*.id' => 'required_with:anular|integer|exists:boletas,id',
            'anular.*.motivo' => 'required_with:anular|string|max:255',
        ]);

        $tenant = $request->get('tenant');
        $fechaResumen = Carbon::parse($request->input('fecha_resumen'));

        $hoy = Carbon::today('America/Lima');
        $limiteAnterior = $hoy->copy()->subDays(7);
        if ($fechaResumen->lt($limiteAnterior->startOfDay()) || $fechaResumen->gt($hoy->endOfDay())) {
            return $this->error(
                'SUNAT solo permite resumen diario del mismo día de emisión o hasta 7 días calendario después. Fecha límite: ' . $limiteAnterior->format('Y-m-d'),
                422
            );
        }

        $isAnulacion = ! empty($request->input('anular'));

        try {
            if ($isAnulacion) {
                $anularItems = $request->input('anular');
                $documentIds = collect($anularItems)->pluck('id')->toArray();

                $boletas = Boleta::where('tenant_id', $tenant->id)
                    ->whereIn('id', $documentIds)
                    ->whereIn('sunat_status', ['aceptado', 'enviado'])
                    ->get();

                if ($boletas->isEmpty()) {
                    return $this->error('No se encontraron boletas válidas para anular. Deben estar aceptadas o enviadas.', 422);
                }
            } else {
                $boletas = Boleta::where('tenant_id', $tenant->id)
                    ->whereDate('fecha_emision', $fechaResumen)
                    ->where('sunat_status', 'pendiente')
                    ->get();

                if ($boletas->isEmpty()) {
                    return $this->error(
                        'No hay boletas pendientes para la fecha ' . $fechaResumen->format('Y-m-d') . '.',
                        422
                    );
                }
            }

            $correlativo = $this->generateCorrelativo($tenant, $fechaResumen);
            $fechaEnvio = Carbon::now('America/Lima')->format('Y-m-d');
            $fechaId = str_replace('-', '', $fechaEnvio);
            $identifier = "RC-{$fechaId}-{$correlativo}";

            // Crear registro en tabla summaries
            $summary = Summary::create([
                'tenant_id' => $tenant->id,
                'identifier' => $identifier,
                'correlativo' => $correlativo,
                'fecha_referencia' => $fechaResumen->format('Y-m-d'),
                'fecha_envio' => $fechaEnvio,
                'total_documentos' => $boletas->count(),
                'tipo' => $isAnulacion ? 'anulacion' : 'envio',
                'document_ids' => $boletas->pluck('id')->toArray(),
                'sunat_status' => 'pendiente',
            ]);

            // Encolar el envío a SUNAT
            SendSummaryToSunat::dispatch($summary->id);
            $summary->update(['sunat_status' => 'enviado']);

            return response()->json([
                'success' => true,
                'message' => $isAnulacion
                    ? 'Resumen de anulación encolado para envío a SUNAT.'
                    : 'Resumen diario encolado para envío a SUNAT.',
                'data' => [
                    'summary_id' => $summary->id,
                    'identifier' => $identifier,
                    'fecha_envio' => $fechaEnvio,
                    'fecha_documentos' => $fechaResumen->format('Y-m-d'),
                    'correlativo' => $correlativo,
                    'accion' => $isAnulacion ? 'anulacion' : 'envio',
                    'total_documentos' => $boletas->count(),
                    'sunat_status' => 'enviado',
                    'documentos' => $boletas->map(fn (Boleta $doc) => [
                        'id' => $doc->id,
                        'numero' => $doc->numero_completo,
                        'total' => (float) $doc->mto_imp_venta,
                    ])->toArray(),
                    'consulta_estado' => url("/api/v1/summaries/{$summary->id}/status"),
                ],
            ], 201);
        } catch (\Throwable $e) {
            return $this->error('Error al crear resumen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Consultar estado de un resumen por ID.
     */
    public function checkStatus(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');

        $summary = Summary::where('tenant_id', $tenant->id)->findOrFail($id);

        $boletas = Boleta::whereIn('id', $summary->document_ids ?? [])
            ->get(['id', 'serie', 'correlativo', 'mto_imp_venta', 'sunat_status', 'sunat_code', 'sunat_description']);

        return response()->json([
            'success' => true,
            'data' => [
                'summary_id' => $summary->id,
                'identifier' => $summary->identifier,
                'ticket' => $summary->ticket,
                'sunat_status' => $summary->sunat_status,
                'sunat_code' => $summary->sunat_code,
                'sunat_description' => $summary->sunat_description,
                'sunat_notes' => $summary->sunat_notes,
                'tipo' => $summary->tipo,
                'total_documentos' => $summary->total_documentos,
                'documentos' => $boletas->map(fn (Boleta $doc) => [
                    'id' => $doc->id,
                    'numero' => $doc->serie . '-' . $doc->correlativo,
                    'total' => (float) $doc->mto_imp_venta,
                    'sunat_status' => $doc->sunat_status,
                ])->toArray(),
            ],
        ]);
    }

    private function generateCorrelativo($tenant, Carbon $fecha): string
    {
        $fechaStr = $fecha->format('Y-m-d');
        $cacheKey = "tenant:{$tenant->id}:summary_correlativo:{$fechaStr}";

        $correlativo = Cache::increment($cacheKey);

        if ($correlativo === 1) {
            Cache::put($cacheKey, 1, 172800);
        }

        return str_pad((string) $correlativo, 3, '0', STR_PAD_LEFT);
    }
}

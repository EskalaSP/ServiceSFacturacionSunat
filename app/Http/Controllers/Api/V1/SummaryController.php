<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateSummaryAction;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Boleta;
use App\Models\Summary;
use App\Services\Greenter\GreenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SummaryController extends Controller
{
    use ApiResponse;

    /**
     * Generar y enviar resumen diario de boletas.
     *
     * Modos:
     * 1. Enviar boletas pendientes: POST { "fecha_resumen": "2026-03-05" }
     * 2. Anular boletas: POST { "fecha_resumen": "2026-03-05", "anular": [15, 16] }
     */
    public function store(Request $request, CreateSummaryAction $action): JsonResponse
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
        $motivosMap = [];

        try {
            if ($isAnulacion) {
                $anularItems = $request->input('anular');
                $documentIds = collect($anularItems)->pluck('id')->toArray();
                $motivosMap = collect($anularItems)->pluck('motivo', 'id')->toArray();

                $boletas = Boleta::where('tenant_id', $tenant->id)
                    ->whereIn('id', $documentIds)
                    ->whereIn('sunat_status', ['aceptado', 'enviado'])
                    ->get();

                if ($boletas->isEmpty()) {
                    return $this->error('No se encontraron boletas válidas para anular. Deben estar aceptadas o enviadas.', 422);
                }

                $estado = '3'; // Anular
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

                $estado = '1'; // Agregar
            }

            $correlativo = $this->generateCorrelativo($tenant, $fechaResumen);

            $fechaEnvio = Carbon::now('America/Lima')->format('Y-m-d');
            $data = [
                'correlativo' => $correlativo,
                'fecha_referencia' => $fechaResumen->format('Y-m-d'),
                'fecha_envio' => $fechaEnvio,
                'fecha_resumen' => $fechaResumen->format('Y-m-d'),
                'detalles' => $boletas->map(fn (Boleta $doc) => [
                    'tipo_documento' => '03',
                    'serie_nro' => $doc->serie . '-' . $doc->correlativo,
                    'estado' => $estado,
                    'cliente_tipo_doc' => $doc->client_tipo_doc,
                    'cliente_num_doc' => $doc->client_num_doc,
                    'total' => (float) $doc->mto_imp_venta,
                    'mto_oper_gravadas' => (float) $doc->mto_oper_gravadas,
                    'mto_oper_exoneradas' => (float) $doc->mto_oper_exoneradas,
                    'mto_oper_inafectas' => (float) $doc->mto_oper_inafectas,
                    'mto_oper_gratuitas' => (float) $doc->mto_oper_gratuitas,
                    'mto_igv' => (float) $doc->mto_igv,
                ])->toArray(),
            ];

            $result = $action->execute($tenant, $data);

            if ($result['success']) {
                $newStatus = $isAnulacion ? 'anulado' : 'enviado';
                Boleta::whereIn('id', $boletas->pluck('id'))
                    ->update([
                        'sunat_status' => $newStatus,
                        'ticket' => $result['ticket'],
                        'sent_at' => now(),
                    ]);

                // Persistir en tabla summaries
                Summary::create([
                    'tenant_id' => $tenant->id,
                    'identifier' => $result['identifier'],
                    'correlativo' => $correlativo,
                    'fecha_referencia' => $fechaResumen->format('Y-m-d'),
                    'fecha_envio' => $fechaEnvio,
                    'total_documentos' => $boletas->count(),
                    'tipo' => $isAnulacion ? 'anulacion' : 'envio',
                    'document_ids' => $boletas->pluck('id')->toArray(),
                    'xml_path' => $result['xml_path'],
                    'ticket' => $result['ticket'],
                    'sunat_status' => 'enviado',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $isAnulacion
                        ? 'Resumen de anulación enviado exitosamente.'
                        : 'Resumen diario enviado exitosamente.',
                    'data' => [
                        'identifier' => $result['identifier'],
                        'ticket' => $result['ticket'],
                        'fecha_envio' => $data['fecha_envio'],
                        'fecha_documentos' => $data['fecha_resumen'],
                        'correlativo' => $correlativo,
                        'accion' => $isAnulacion ? 'anulacion' : 'envio',
                        'total_documentos' => $boletas->count(),
                        'documentos' => $boletas->map(fn (Boleta $doc) => array_filter([
                            'id' => $doc->id,
                            'tipo_documento' => '03',
                            'numero' => $doc->numero_completo,
                            'cliente' => $doc->client_razon_social,
                            'total' => (float) $doc->mto_imp_venta,
                            'motivo' => $isAnulacion ? ($motivosMap[$doc->id] ?? null) : null,
                        ]))->toArray(),
                        'consulta_estado' => url("/api/v1/summaries/{$result['ticket']}/status"),
                        'archivos' => [
                            'xml' => $result['xml_path']
                                ? url("/storage/{$result['xml_path']}")
                                : null,
                        ],
                    ],
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar resumen a SUNAT',
                'error' => [
                    'identifier' => $result['identifier'],
                    'code' => $result['error_code'],
                    'description' => $result['error_message'],
                ],
            ], 422);
        } catch (\Throwable $e) {
            return $this->error('Error al crear resumen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Consultar estado de un ticket de resumen.
     */
    public function checkStatus(Request $request, string $ticket): JsonResponse
    {
        $tenant = $request->get('tenant');

        try {
            $service = new GreenterService($tenant);
            $result = $service->getStatus($ticket);

            // Actualizar summary record
            $summary = Summary::where('tenant_id', $tenant->id)
                ->where('ticket', $ticket)
                ->first();

            if ($result['success'] && ($result['accepted'] ?? false)) {
                // Actualizar boletas asociadas
                Boleta::where('tenant_id', $tenant->id)
                    ->where('ticket', $ticket)
                    ->update([
                        'sunat_status' => 'aceptado',
                        'sunat_code' => $result['code'] ?? null,
                        'sunat_description' => $result['description'] ?? null,
                        'sunat_notes' => $result['notes'] ?? null,
                    ]);

                if ($summary) {
                    $summary->update([
                        'sunat_status' => 'aceptado',
                        'sunat_code' => $result['code'] ?? null,
                        'sunat_description' => $result['description'] ?? null,
                        'sunat_notes' => $result['notes'] ?? null,
                    ]);
                }
            }

            $boletas = Boleta::where('tenant_id', $tenant->id)
                ->where('ticket', $ticket)
                ->get(['id', 'serie', 'correlativo', 'mto_imp_venta', 'sunat_status']);

            return response()->json([
                'success' => true,
                'data' => [
                    'ticket' => $ticket,
                    'sunat' => [
                        'accepted' => $result['accepted'] ?? false,
                        'code' => $result['code'] ?? null,
                        'description' => $result['description'] ?? null,
                        'notes' => $result['notes'] ?? null,
                    ],
                    'documentos' => $boletas->map(fn (Boleta $doc) => [
                        'id' => $doc->id,
                        'numero' => $doc->serie . '-' . $doc->correlativo,
                        'total' => (float) $doc->mto_imp_venta,
                        'status' => $doc->sunat_status,
                    ])->toArray(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->error('Error al consultar ticket: ' . $e->getMessage(), 500);
        }
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

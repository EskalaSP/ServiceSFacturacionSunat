<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSummaryRequest;
use App\Http\Traits\ApiResponse;
use App\Jobs\SendSummaryToSunat;
use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Summary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SummaryController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = Summary::where('tenant_id', $tenant->id);

        if ($mes = $request->query('mes')) {
            $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
            $ymExpr = $driver === 'pgsql'
                ? "to_char(fecha_envio, 'YYYY-MM')"
                : "DATE_FORMAT(fecha_envio, '%Y-%m')";
            $query->whereRaw("{$ymExpr} = ?", [$mes]);
        }

        if ($tipo = $request->query('tipo')) {
            $query->where('tipo', $tipo);
        }

        $summaries = $query->orderByDesc('created_at')
            ->paginate($request->query('por_pagina', 15));

        return response()->json([
            'estado' => 'exito',
            'datos' => $summaries->map(fn (Summary $s) => [
                'id' => $s->id,
                'identifier' => $s->identifier,
                'tipo' => $s->tipo,
                'fecha_referencia' => $s->fecha_referencia->format('Y-m-d'),
                'fecha_envio' => $s->fecha_envio->format('Y-m-d'),
                'total_documentos' => $s->total_documentos,
                'ticket' => $s->ticket,
                'estado_sunat' => $s->sunat_status,
                'codigo_sunat' => $s->sunat_code,
                'descripcion_sunat' => $s->sunat_description,
                'creado_en' => $s->created_at->toIso8601String(),
            ]),
            'paginacion' => [
                'total' => $summaries->total(),
                'pagina_actual' => $summaries->currentPage(),
                'ultima_pagina' => $summaries->lastPage(),
            ],
        ]);
    }

    /**
     * Generar y encolar resumen diario de boletas.
     *
     * Modos:
     * 1. Enviar boletas pendientes: POST { "fecha_resumen": "2026-03-05" }
     * 2. Anular boletas: POST { "fecha_resumen": "2026-03-05", "anular": [{"id": 15, "motivo": "..."}, ...] }
     */
    public function store(StoreSummaryRequest $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        $fechaResumen = Carbon::parse($request->input('fecha_resumen'));
        $enviarAuto = $request->boolean('enviar_automatico', true);

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
                    ->where('sunat_status', 'aceptado')
                    ->get();

                if ($boletas->isEmpty()) {
                    return $this->error('No se encontraron boletas aceptadas por SUNAT para anular.', 422);
                }

                // Validaciones adicionales: plazo 7 días por boleta, NC asociada, duplicado en proceso.
                $anulErrors = [];
                foreach ($boletas as $b) {
                    $ref = $b->serie.'-'.$b->correlativo;

                    $fechaEmision = $b->fecha_emision instanceof Carbon
                        ? $b->fecha_emision
                        : Carbon::parse((string) $b->fecha_emision);

                    if ($fechaEmision->lt($limiteAnterior->copy()->startOfDay())) {
                        $anulErrors[] = "Boleta {$ref} ya pasó el plazo de 7 días para anulación (emitida el {$fechaEmision->format('Y-m-d')}).";
                        continue;
                    }

                    $hasNC = CreditNote::where('tenant_id', $tenant->id)
                        ->where('doc_afectado_tipo', '03')
                        ->where('doc_afectado_serie', $b->serie)
                        ->where('doc_afectado_correlativo', $b->correlativo)
                        ->exists();

                    if ($hasNC) {
                        $anulErrors[] = "La boleta {$ref} tiene una nota de crédito asociada. Usa la NC en vez de anularla.";
                        continue;
                    }

                    $duplicate = Summary::where('tenant_id', $tenant->id)
                        ->where('tipo', 'anulacion')
                        ->whereIn('sunat_status', ['pendiente', 'enviado', 'aceptado'])
                        ->whereJsonContains('document_ids', $b->id)
                        ->first();

                    if ($duplicate) {
                        $anulErrors[] = "Ya existe un resumen de anulación para la boleta {$ref} ({$duplicate->identifier}).";
                    }
                }

                if (! empty($anulErrors)) {
                    return response()->json([
                        'estado' => 'error',
                        'mensaje' => 'No se puede anular: '.implode(' ', $anulErrors),
                        'errores' => $anulErrors,
                    ], 422);
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

            if ($isAnulacion) {
                Boleta::whereIn('id', $boletas->pluck('id')->toArray())
                    ->update(['sunat_status' => 'anulacion_en_proceso']);
            }

            if ($enviarAuto) {
                SendSummaryToSunat::dispatch($summary->id);
                $summary->update(['sunat_status' => 'enviado']);
            }

            $messageBase = $isAnulacion ? 'Resumen de anulación' : 'Resumen diario';
            $message = $enviarAuto
                ? "{$messageBase} encolado para envío a SUNAT."
                : "{$messageBase} creado en estado pendiente. Use POST /resumenes/{id}/enviar para enviarlo a SUNAT.";

            return response()->json([
                'estado' => 'exito',
                'mensaje' => $message,
                'datos' => [
                    'id_resumen' => $summary->id,
                    'identifier' => $identifier,
                    'fecha_envio' => $fechaEnvio,
                    'fecha_documentos' => $fechaResumen->format('Y-m-d'),
                    'correlativo' => $correlativo,
                    'accion' => $isAnulacion ? 'anulacion' : 'envio',
                    'total_documentos' => $boletas->count(),
                    'estado_sunat' => $enviarAuto ? 'enviado' : 'pendiente',
                    'documentos' => $boletas->map(fn (Boleta $doc) => [
                        'id' => $doc->id,
                        'numero' => $doc->numero_completo,
                        'total' => (float) $doc->mto_imp_venta,
                    ])->toArray(),
                    'consulta_estado' => url("/api/v1/resumenes/{$summary->id}/estado"),
                ],
            ], 201);
        } catch (\Throwable $e) {
            return $this->error('Error al crear resumen: ' . $e->getMessage(), 500);
        }
    }

    public function enviar(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $summary = Summary::where('tenant_id', $tenant->id)->findOrFail($id);

        if (in_array($summary->sunat_status, ['aceptado'], true)) {
            return $this->error('Este resumen ya fue aceptado por SUNAT.', 422);
        }

        $summary->update([
            'sunat_status' => 'pendiente',
            'sunat_code' => null,
            'sunat_description' => null,
        ]);

        SendSummaryToSunat::dispatch($summary->id);
        $summary->update(['sunat_status' => 'enviado']);

        return response()->json([
            'estado' => 'exito',
            'mensaje' => 'Resumen enviado a SUNAT.',
            'datos' => [
                'id_resumen' => $summary->id,
                'identifier' => $summary->identifier,
                'estado_sunat' => 'enviado',
                'consulta_estado' => url("/api/v1/resumenes/{$summary->id}/estado"),
            ],
        ]);
    }

    public function checkStatus(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');

        $summary = Summary::where('tenant_id', $tenant->id)->findOrFail($id);

        $boletas = Boleta::whereIn('id', $summary->document_ids ?? [])
            ->get(['id', 'serie', 'correlativo', 'mto_imp_venta', 'sunat_status', 'sunat_code', 'sunat_description']);

        return response()->json([
            'estado' => 'exito',
            'datos' => [
                'id_resumen' => $summary->id,
                'identifier' => $summary->identifier,
                'ticket' => $summary->ticket,
                'estado_sunat' => $summary->sunat_status,
                'codigo_sunat' => $summary->sunat_code,
                'descripcion_sunat' => $summary->sunat_description,
                'notas_sunat' => $summary->sunat_notes,
                'tipo' => $summary->tipo,
                'total_documentos' => $summary->total_documentos,
                'documentos' => $boletas->map(fn (Boleta $doc) => [
                    'id' => $doc->id,
                    'numero' => $doc->serie . '-' . $doc->correlativo,
                    'total' => (float) $doc->mto_imp_venta,
                    'estado_sunat' => $doc->sunat_status,
                ])->toArray(),
            ],
        ]);
    }

    public function xml(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $summary = Summary::where('tenant_id', $tenant->id)->findOrFail($id);

        if (! $summary->xml_path) {
            $motivo = match ($summary->sunat_status) {
                'pendiente' => 'El resumen aún no fue procesado (estado: pendiente). Espera a que el worker lo envíe a SUNAT.',
                'enviado'   => 'El resumen fue enviado pero el XML no está disponible aún. Consulta el estado con GET /resumenes/{id}/estado.',
                'rechazado' => 'El resumen fue rechazado por SUNAT y el XML no pudo guardarse. Código: ' . ($summary->sunat_code ?? 'N/A') . '.',
                default     => 'XML no generado (estado: ' . $summary->sunat_status . ').',
            };
            return $this->error($motivo, 404);
        }

        if (! Storage::disk('public')->exists($summary->xml_path)) {
            return $this->error(
                'El XML fue generado pero el archivo no se encuentra en disco. Ruta esperada: ' . $summary->xml_path,
                404
            );
        }

        $content = Storage::disk('public')->get($summary->xml_path);

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$summary->identifier}.xml\"",
        ]);
    }

    public function cdr(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $summary = Summary::where('tenant_id', $tenant->id)->findOrFail($id);

        if (! $summary->cdr_path || ! Storage::disk('public')->exists($summary->cdr_path)) {
            return $this->error('CDR no disponible', 404);
        }

        $content = Storage::disk('public')->get($summary->cdr_path);

        return response($content, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"R-{$summary->identifier}.zip\"",
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

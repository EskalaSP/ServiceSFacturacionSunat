<?php

namespace App\Services\Documents;

use App\Jobs\SendSummaryToSunat;
use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\Summary;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Resumen diario (RC) de boletas, compartido por la API y el panel:
 *   - envío   : envía a SUNAT las boletas pendientes de una fecha.
 *   - anulación: da de baja boletas aceptadas (las boletas no usan comunicación RA).
 */
class SummaryService
{
    /**
     * @param  array<int,array{id:int,motivo?:string}>|null  $anular
     * @return array{ok:bool, error?:string, errores?:array<int,string>, summary?:Summary, boletas?:Collection, meta?:array<string,mixed>}
     */
    public function crear(Tenant $tenant, string $fechaResumenStr, ?array $anular, bool $enviarAuto = true, ?string $motivo = null, ?int $userId = null): array
    {
        $fechaResumen = Carbon::parse($fechaResumenStr);
        $hoy = Carbon::today('America/Lima');
        $limiteAnterior = $hoy->copy()->subDays(7);

        if ($fechaResumen->lt($limiteAnterior->copy()->startOfDay()) || $fechaResumen->gt($hoy->copy()->endOfDay())) {
            return ['ok' => false, 'error' => 'SUNAT solo permite resumen diario del mismo día de emisión o hasta 7 días calendario después. Fecha límite: '.$limiteAnterior->format('Y-m-d')];
        }

        $isAnulacion = ! empty($anular);

        if ($isAnulacion) {
            $documentIds = collect($anular)->pluck('id')->toArray();
            $boletas = Boleta::where('tenant_id', $tenant->id)
                ->whereIn('id', $documentIds)
                ->where('sunat_status', 'aceptado')
                ->get();

            if ($boletas->isEmpty()) {
                return ['ok' => false, 'error' => 'No se encontraron boletas aceptadas por SUNAT para anular.'];
            }

            $errores = $this->validarAnulacion($tenant, $boletas, $limiteAnterior);
            if (! empty($errores)) {
                return ['ok' => false, 'errores' => $errores];
            }
        } else {
            $boletas = Boleta::where('tenant_id', $tenant->id)
                ->whereDate('fecha_emision', $fechaResumen)
                ->where('sunat_status', 'pendiente')
                ->get();

            if ($boletas->isEmpty()) {
                return ['ok' => false, 'error' => 'No hay boletas pendientes para la fecha '.$fechaResumen->format('Y-m-d').'.'];
            }
        }

        // El correlativo se numera por fecha de ENVÍO (la que entra en el identificador).
        $fechaEnvio = Carbon::now('America/Lima')->format('Y-m-d');
        $correlativo = $this->generateCorrelativo($tenant, $fechaEnvio);
        $fechaId = str_replace('-', '', $fechaEnvio);
        $identifier = "RC-{$fechaId}-{$correlativo}";

        $summary = Summary::create([
            'tenant_id' => $tenant->id,
            'ambiente' => $tenant->environment === 'produccion' ? 'produccion' : 'prueba',
            'identifier' => $identifier,
            'correlativo' => $correlativo,
            'fecha_referencia' => $fechaResumen->format('Y-m-d'),
            'fecha_envio' => $fechaEnvio,
            'total_documentos' => $boletas->count(),
            'tipo' => $isAnulacion ? 'anulacion' : 'envio',
            'document_ids' => $boletas->pluck('id')->toArray(),
            'motivo' => $isAnulacion ? $motivo : null,
            'anulado_por' => $isAnulacion ? $userId : null,
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

        return [
            'ok' => true,
            'summary' => $summary,
            'boletas' => $boletas,
            'meta' => [
                'isAnulacion' => $isAnulacion,
                'fechaEnvio' => $fechaEnvio,
                'fechaResumen' => $fechaResumen->format('Y-m-d'),
                'correlativo' => $correlativo,
                'identifier' => $identifier,
            ],
        ];
    }

    /**
     * Validaciones por boleta al anular: plazo 7 días, NC asociada, duplicado en proceso.
     *
     * @return array<int,string>
     */
    private function validarAnulacion(Tenant $tenant, Collection $boletas, Carbon $limiteAnterior): array
    {
        $errores = [];

        foreach ($boletas as $b) {
            $ref = $b->serie.'-'.$b->correlativo;

            $fechaEmision = $b->fecha_emision instanceof Carbon
                ? $b->fecha_emision
                : Carbon::parse((string) $b->fecha_emision);

            if ($fechaEmision->lt($limiteAnterior->copy()->startOfDay())) {
                $errores[] = "Boleta {$ref} ya pasó el plazo de 7 días para anulación (emitida el {$fechaEmision->format('Y-m-d')}).";

                continue;
            }

            $hasNC = CreditNote::where('tenant_id', $tenant->id)
                ->where('doc_afectado_tipo', '03')
                ->where('doc_afectado_serie', $b->serie)
                ->where('doc_afectado_correlativo', $b->correlativo)
                ->exists();

            if ($hasNC) {
                $errores[] = "La boleta {$ref} tiene una nota de crédito asociada. Usa la NC en vez de anularla.";

                continue;
            }

            $duplicate = Summary::where('tenant_id', $tenant->id)
                ->where('tipo', 'anulacion')
                ->whereIn('sunat_status', ['pendiente', 'enviado', 'aceptado'])
                ->whereJsonContains('document_ids', $b->id)
                ->first();

            if ($duplicate) {
                $errores[] = "Ya existe un resumen de anulación para la boleta {$ref} ({$duplicate->identifier}).";
            }
        }

        return $errores;
    }

    /** Siguiente correlativo del día de envío (derivado de la BD + lock, no de caché). */
    private function generateCorrelativo(Tenant $tenant, string $fechaEnvio): string
    {
        $siguiente = fn (): int => 1 + (int) Summary::where('tenant_id', $tenant->id)
            ->where('fecha_envio', $fechaEnvio)
            ->pluck('correlativo')
            ->map(fn ($c): int => (int) $c)
            ->max();

        $correlativo = Cache::lock("summary_correlativo:{$tenant->id}:{$fechaEnvio}", 10)
            ->block(5, $siguiente);

        return str_pad((string) $correlativo, 3, '0', STR_PAD_LEFT);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Jobs\SendRetentionToSunat;
use App\Models\Retention;
use App\Models\Serie;
use App\Models\Tenant;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreateRetentionAction
{
    use Concerns\ResolvesEmisionDateTime;

    public function execute(Tenant $tenant, array $data, bool $enviarAutomatico = true): Retention
    {
        return DB::transaction(function () use ($tenant, $data, $enviarAutomatico) {
            $serie = Serie::where('tenant_id', $tenant->id)
                ->where('tipo_documento', '20')
                ->where('serie', $data['serie'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $correlativo = $serie->resolveCorrelativo(isset($data['correlativo']) ? (int) $data['correlativo'] : null);
            $tasa = (float) $data['tasa'];

            // Calcular totales a partir de los documentos
            $totalRetenido = 0;
            $totalPagado = 0;
            foreach ($data['documentos'] as $doc) {
                $impTotal = (float) $doc['imp_total'];
                $impRetenido = (float) ($doc['imp_retenido'] ?? round($impTotal * $tasa / 100, 2));
                $impPagar = (float) ($doc['imp_pagar'] ?? round($impTotal - $impRetenido, 2));
                $totalRetenido += $impRetenido;
                $totalPagado += $impPagar;
            }

            $retention = Retention::create([
                'tenant_id' => $tenant->id,
                'serie' => $data['serie'],
                'correlativo' => $correlativo,
                'cod_local' => $data['cod_local'] ?? '0000',
                'fecha_emision' => $this->resolveEmisionDateTime($data['fecha_emision']),
                'proveedor_tipo_doc' => $data['proveedor']['tipo_doc'],
                'proveedor_num_doc' => $data['proveedor']['num_doc'],
                'proveedor_razon_social' => $data['proveedor']['razon_social'],
                'proveedor_direccion' => $data['proveedor']['direccion'] ?? null,
                'regimen' => $data['regimen'],
                'tasa' => $tasa,
                'imp_retenido' => round($totalRetenido, 2),
                'imp_pagado' => round($totalPagado, 2),
                'observacion' => $data['observacion'] ?? null,
                'sunat_status' => 'pendiente',
            ]);

            // Crear ítems de retención
            foreach ($data['documentos'] as $doc) {
                $impTotal = (float) $doc['imp_total'];
                $impRetenido = (float) ($doc['imp_retenido'] ?? round($impTotal * $tasa / 100, 2));
                $impPagar = (float) ($doc['imp_pagar'] ?? round($impTotal - $impRetenido, 2));

                $retention->items()->create([
                    'tipo_doc' => $doc['tipo_doc'],
                    'num_doc' => $doc['num_doc'],
                    'fecha_emision_doc' => $doc['fecha_emision'],
                    'imp_total' => $impTotal,
                    'moneda' => $doc['moneda'] ?? 'PEN',
                    'pagos' => $doc['pagos'],
                    'fecha_retencion' => $doc['fecha_retencion'],
                    'imp_retenido' => $impRetenido,
                    'imp_pagar' => $impPagar,
                    'tipo_cambio' => $doc['tipo_cambio'] ?? null,
                ]);
            }

            Cache::forget("tenant:{$tenant->id}:sunat_count:" . now()->format('Y-m'));
            app(PlanService::class)->incrementUsage($tenant, 'documents');

            if ($enviarAutomatico) {
                SendRetentionToSunat::dispatch($retention->id);
                $retention->update(['sunat_status' => 'enviado']);
            }

            return $retention->fresh('items');
        });
    }
}

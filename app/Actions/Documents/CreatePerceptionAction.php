<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Jobs\SendPerceptionToSunat;
use App\Models\Perception;
use App\Models\Serie;
use App\Models\Tenant;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreatePerceptionAction
{
    use Concerns\ResolvesEmisionDateTime;

    public function execute(Tenant $tenant, array $data, bool $enviarAutomatico = true): Perception
    {
        return DB::transaction(function () use ($tenant, $data, $enviarAutomatico) {
            $serie = Serie::where('tenant_id', $tenant->id)
                ->where('tipo_documento', '40')
                ->where('serie', $data['serie'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $correlativo = $serie->resolveCorrelativo(isset($data['correlativo']) ? (int) $data['correlativo'] : null);
            $tasa = (float) $data['tasa'];

            $totalPercibido = 0;
            $totalCobrado = 0;
            foreach ($data['documentos'] as $doc) {
                $impTotal = (float) $doc['imp_total'];
                $impPercibido = (float) ($doc['imp_percibido'] ?? round($impTotal * $tasa / 100, 2));
                $impCobrar = (float) ($doc['imp_cobrar'] ?? round($impTotal + $impPercibido, 2));
                $totalPercibido += $impPercibido;
                $totalCobrado += $impCobrar;
            }

            $perception = Perception::create([
                'tenant_id' => $tenant->id,
                'serie' => $data['serie'],
                'correlativo' => $correlativo,
                'cod_local' => $data['cod_local'] ?? '0000',
                'fecha_emision' => $this->resolveEmisionDateTime($data['fecha_emision']),
                'cliente_tipo_doc' => $data['cliente']['tipo_doc'],
                'cliente_num_doc' => $data['cliente']['num_doc'],
                'cliente_razon_social' => $data['cliente']['razon_social'],
                'cliente_direccion' => $data['cliente']['direccion'] ?? null,
                'regimen' => $data['regimen'],
                'tasa' => $tasa,
                'imp_percibido' => round($totalPercibido, 2),
                'imp_cobrado' => round($totalCobrado, 2),
                'observacion' => $data['observacion'] ?? null,
                'sunat_status' => 'pendiente',
            ]);

            foreach ($data['documentos'] as $doc) {
                $impTotal = (float) $doc['imp_total'];
                $impPercibido = (float) ($doc['imp_percibido'] ?? round($impTotal * $tasa / 100, 2));
                $impCobrar = (float) ($doc['imp_cobrar'] ?? round($impTotal + $impPercibido, 2));

                $perception->items()->create([
                    'tipo_doc' => $doc['tipo_doc'],
                    'num_doc' => $doc['num_doc'],
                    'fecha_emision_doc' => $doc['fecha_emision'],
                    'imp_total' => $impTotal,
                    'moneda' => $doc['moneda'] ?? 'PEN',
                    'cobros' => $doc['cobros'],
                    'fecha_percepcion' => $doc['fecha_percepcion'],
                    'imp_percibido' => $impPercibido,
                    'imp_cobrar' => $impCobrar,
                    'tipo_cambio' => $doc['tipo_cambio'] ?? null,
                ]);
            }

            Cache::forget("tenant:{$tenant->id}:sunat_count:" . now()->format('Y-m'));
            app(PlanService::class)->incrementUsage($tenant, 'documents');

            if ($enviarAutomatico) {
                SendPerceptionToSunat::dispatch($perception->id);
                $perception->update(['sunat_status' => 'enviado']);
            }

            return $perception->fresh('items');
        });
    }
}

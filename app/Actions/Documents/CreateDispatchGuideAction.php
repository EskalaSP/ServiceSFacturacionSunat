<?php

namespace App\Actions\Documents;

use App\Events\DocumentCreated;
use App\Jobs\SendDispatchGuideToSunat;
use App\Models\DispatchGuide;
use App\Models\Serie;
use App\Models\Tenant;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreateDispatchGuideAction
{
    use Concerns\ResolvesEmisionDateTime;

    public function execute(Tenant $tenant, array $data, bool $enviarAutomatico = true): DispatchGuide
    {
        return DB::transaction(function () use ($tenant, $data, $enviarAutomatico) {
            // tipo_documento: '09' (GRR) por defecto o '31' (GRT)
            $tipoDocumento = $data['tipo_documento'] ?? '09';

            $serie = Serie::with('sucursal')
                ->where('tenant_id', $tenant->id)
                ->where('tipo_documento', $tipoDocumento)
                ->where('serie', $data['serie'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $correlativo = $serie->resolveCorrelativo(isset($data['correlativo']) ? (int) $data['correlativo'] : null);
            $data['correlativo'] = $correlativo;
            $data['tipo_documento'] = $tipoDocumento;

            $sucursal = $serie->sucursal;

            $guide = DispatchGuide::create([
                'tenant_id' => $tenant->id,
                'sucursal_id' => $sucursal?->id,
                'cod_local' => $sucursal?->cod_local ?? $data['cod_local'] ?? '0000',
                'tipo_documento' => $tipoDocumento,
                'serie' => $data['serie'],
                'correlativo' => $correlativo,
                'fecha_emision' => $this->resolveEmisionDateTime($data['fecha_emision']),
                'destinatario_tipo_doc' => $data['destinatario']['tipo_doc'],
                'destinatario_num_doc' => $data['destinatario']['num_doc'],
                'destinatario_razon_social' => $data['destinatario']['razon_social'],
                'cod_traslado' => $data['cod_traslado'],
                'mod_traslado' => $data['mod_traslado'],
                'fecha_traslado' => $data['fecha_traslado'],
                'fecha_entrega_transportista' => $data['fecha_entrega_transportista'] ?? null,
                'peso_total' => $data['peso_total'],
                'und_peso_total' => $data['und_peso_total'] ?? 'KGM',
                'num_bultos' => $data['num_bultos'] ?? null,
                'llegada_ubigeo' => $data['llegada_ubigeo'],
                'llegada_direccion' => $data['llegada_direccion'],
                'llegada_ruc' => $data['llegada_ruc'] ?? null,
                'llegada_cod_local' => $data['llegada_cod_local'] ?? null,
                'partida_ubigeo' => $data['partida_ubigeo'],
                'partida_direccion' => $data['partida_direccion'],
                'partida_ruc' => $data['partida_ruc'] ?? null,
                'partida_cod_local' => $data['partida_cod_local'] ?? null,
                'transportista' => $data['transportista'] ?? null,
                'vehiculo' => $data['vehiculo'] ?? null,
                'conductor' => $data['conductor'] ?? $data['conductores'] ?? null,
                'indicadores' => $data['indicadores'] ?? null,
                'remitente' => $data['remitente'] ?? null,
                'doc_relacionado' => $data['doc_relacionado'] ?? null,
                'datos_subcontratador' => $data['datos_subcontratador'] ?? null,
                'datos_pagador_flete' => $data['datos_pagador_flete'] ?? null,
                'autorizacion_especial' => $data['autorizacion_especial'] ?? null,
                'observacion' => $data['observacion'] ?? null,
                'items' => $data['items'],
                'sunat_status' => 'pendiente',
            ]);

            event(new DocumentCreated($guide));
            Cache::forget("tenant:{$tenant->id}:sunat_count:" . now()->format('Y-m'));
            app(PlanService::class)->incrementUsage($tenant, 'documents');

            if ($enviarAutomatico) {
                SendDispatchGuideToSunat::dispatch($guide->id);
                $guide->update(['sunat_status' => 'enviado']);
            }

            return $guide->fresh();
        });
    }
}

<?php

namespace App\Actions\Documents;

use App\Jobs\SendDispatchGuideToSunat;
use App\Models\DispatchGuide;
use App\Models\Serie;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CreateDispatchGuideAction
{
    public function execute(Tenant $tenant, array $data): DispatchGuide
    {
        return DB::transaction(function () use ($tenant, $data) {
            $serie = Serie::with('sucursal')
                ->where('tenant_id', $tenant->id)
                ->where('tipo_documento', '09')
                ->where('serie', $data['serie'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $correlativo = $serie->nextCorrelativo();
            $data['correlativo'] = $correlativo;

            $sucursal = $serie->sucursal;

            $guide = DispatchGuide::create([
                'tenant_id' => $tenant->id,
                'sucursal_id' => $sucursal?->id,
                'cod_local' => $sucursal?->cod_local ?? $data['cod_local'] ?? '0000',
                'serie' => $data['serie'],
                'correlativo' => $correlativo,
                'fecha_emision' => $data['fecha_emision'],
                'destinatario_tipo_doc' => $data['destinatario']['tipo_doc'],
                'destinatario_num_doc' => $data['destinatario']['num_doc'],
                'destinatario_razon_social' => $data['destinatario']['razon_social'],
                'cod_traslado' => $data['cod_traslado'],
                'mod_traslado' => $data['mod_traslado'],
                'fecha_traslado' => $data['fecha_traslado'],
                'peso_total' => $data['peso_total'],
                'und_peso_total' => $data['und_peso_total'] ?? 'KGM',
                'num_bultos' => $data['num_bultos'] ?? null,
                'llegada_ubigeo' => $data['llegada_ubigeo'],
                'llegada_direccion' => $data['llegada_direccion'],
                'partida_ubigeo' => $data['partida_ubigeo'],
                'partida_direccion' => $data['partida_direccion'],
                'transportista' => $data['transportista'] ?? null,
                'vehiculo' => $data['vehiculo'] ?? null,
                'conductor' => $data['conductor'] ?? $data['conductores'] ?? null,
                'items' => $data['items'],
                'sunat_status' => 'pendiente',
            ]);

            SendDispatchGuideToSunat::dispatch($guide->id);
            $guide->update(['sunat_status' => 'enviado']);

            return $guide->fresh();
        });
    }
}

<?php

namespace App\Actions\Documents;

use App\Jobs\SendDispatchGuideToSunat;
use App\Models\DispatchGuide;
use Illuminate\Support\Facades\DB;

class UpdateDispatchGuideAction
{
    public function execute(DispatchGuide $guide, array $data): DispatchGuide
    {
        return DB::transaction(function () use ($guide, $data) {
            $guide->update([
                'fecha_emision' => $data['fecha_emision'],
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
                'partida_ubigeo' => $data['partida_ubigeo'],
                'partida_direccion' => $data['partida_direccion'],
                'transportista' => $data['transportista'] ?? null,
                'vehiculo' => $data['vehiculo'] ?? null,
                'conductor' => $data['conductor'] ?? $data['conductores'] ?? null,
                'items' => $data['items'],
                // Reiniciar estado SUNAT
                'sunat_status' => 'pendiente',
                'sunat_code' => null,
                'sunat_description' => null,
                'ticket' => null,
                'xml_path' => null,
                'cdr_path' => null,
                'pdf_path' => null,
            ]);

            SendDispatchGuideToSunat::dispatch($guide->id);
            $guide->update(['sunat_status' => 'enviado']);

            return $guide->fresh();
        });
    }
}

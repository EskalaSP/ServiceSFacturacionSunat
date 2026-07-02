<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DispatchGuideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'numero_completo' => $this->numero_completo,
            'fecha_emision' => $this->fecha_emision->format('Y-m-d H:i:s'),
            'destinatario' => [
                'tipo_doc' => $this->destinatario_tipo_doc,
                'num_doc' => $this->destinatario_num_doc,
                'razon_social' => $this->destinatario_razon_social,
            ],
            'envio' => [
                'cod_traslado' => $this->cod_traslado,
                'mod_traslado' => $this->mod_traslado,
                'fecha_traslado' => $this->fecha_traslado->format('Y-m-d'),
                'fecha_entrega_transportista' => $this->fecha_entrega_transportista?->format('Y-m-d'),
                'peso_total' => (float) $this->peso_total,
                'und_peso_total' => $this->und_peso_total,
            ],
            'direcciones' => [
                'llegada' => ['ubigeo' => $this->llegada_ubigeo, 'direccion' => $this->llegada_direccion],
                'partida' => ['ubigeo' => $this->partida_ubigeo, 'direccion' => $this->partida_direccion],
            ],
            'observacion' => $this->observacion,
            'transportista' => $this->transportista,
            'vehiculo' => $this->vehiculo,
            'conductor' => $this->conductor,
            'num_bultos' => $this->num_bultos,
            'items' => $this->items,
            'sunat' => [
                'estado' => $this->sunat_status,
                'codigo' => $this->sunat_code,
                'descripcion' => $this->sunat_description,
                'ticket' => $this->ticket,
            ],
            'archivos' => [
                'xml' => $this->xml_path ? url("/api/v1/guias-remision/{$this->id}/xml") : null,
                'cdr' => $this->cdr_path ? url("/api/v1/guias-remision/{$this->id}/cdr") : null,
                'pdf' => $this->pdf_path ? url("/api/v1/guias-remision/{$this->id}/pdf") : null,
            ],
            'creado_en' => $this->created_at->toIso8601String(),
        ];
    }
}

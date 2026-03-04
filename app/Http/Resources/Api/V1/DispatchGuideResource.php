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
            'fecha_emision' => $this->fecha_emision->format('Y-m-d'),
            'destinatario' => [
                'tipo_doc' => $this->destinatario_tipo_doc,
                'num_doc' => $this->destinatario_num_doc,
                'razon_social' => $this->destinatario_razon_social,
            ],
            'envio' => [
                'cod_traslado' => $this->cod_traslado,
                'mod_traslado' => $this->mod_traslado,
                'fecha_traslado' => $this->fecha_traslado->format('Y-m-d'),
                'peso_total' => (float) $this->peso_total,
                'und_peso_total' => $this->und_peso_total,
            ],
            'direcciones' => [
                'llegada' => ['ubigeo' => $this->llegada_ubigeo, 'direccion' => $this->llegada_direccion],
                'partida' => ['ubigeo' => $this->partida_ubigeo, 'direccion' => $this->partida_direccion],
            ],
            'transportista' => $this->transportista,
            'items' => $this->items,
            'sunat' => [
                'status' => $this->sunat_status,
                'code' => $this->sunat_code,
                'description' => $this->sunat_description,
                'ticket' => $this->ticket,
            ],
            'archivos' => [
                'xml_path' => $this->xml_path,
                'cdr_path' => $this->cdr_path,
                'pdf_path' => $this->pdf_path,
            ],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo_documento' => $this->tipo_documento,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'numero_completo' => $this->numero_completo,
            'cod_local' => $this->cod_local,
            'fecha_emision' => $this->fecha_emision->format('Y-m-d'),
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'),
            'tipo_operacion' => $this->tipo_operacion,
            'tipo_moneda' => $this->tipo_moneda,
            'forma_pago' => $this->forma_pago,
            'cliente' => [
                'tipo_doc' => $this->client_tipo_doc,
                'num_doc' => $this->client_num_doc,
                'razon_social' => $this->client_razon_social,
                'direccion' => $this->client_direccion,
            ],
            'totales' => [
                'gravadas' => (float) $this->mto_oper_gravadas,
                'exoneradas' => (float) $this->mto_oper_exoneradas,
                'inafectas' => (float) $this->mto_oper_inafectas,
                'gratuitas' => (float) $this->mto_oper_gratuitas,
                'igv' => (float) $this->mto_igv,
                'isc' => (float) $this->mto_isc,
                'icbper' => (float) $this->mto_icbper,
                'total_impuestos' => (float) $this->total_impuestos,
                'valor_venta' => (float) $this->valor_venta,
                'sub_total' => (float) $this->sub_total,
                'total' => (float) $this->mto_imp_venta,
            ],
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'codigo' => $item->codigo,
                'descripcion' => $item->descripcion,
                'unidad' => $item->unidad,
                'cantidad' => (float) $item->cantidad,
                'precio_unitario' => (float) $item->mto_precio_unitario,
                'valor_unitario' => (float) $item->mto_valor_unitario,
                'igv' => (float) $item->igv,
                'total' => (float) $item->mto_valor_venta,
            ])),
            'doc_afectado' => $this->when($this->doc_afectado_tipo, [
                'tipo' => $this->doc_afectado_tipo,
                'serie' => $this->doc_afectado_serie,
                'correlativo' => $this->doc_afectado_correlativo,
                'motivo_codigo' => $this->cod_motivo,
                'motivo_descripcion' => $this->des_motivo,
            ]),
            'sunat' => [
                'status' => $this->sunat_status,
                'code' => $this->sunat_code,
                'description' => $this->sunat_description,
                'notes' => $this->sunat_notes,
                'hash_cpe' => $this->hash_cpe,
            ],
            'archivos' => [
                'xml' => $this->xml_path ? url("/api/v1/documents/{$this->id}/xml") : null,
                'cdr' => $this->cdr_path ? url("/api/v1/documents/{$this->id}/cdr") : null,
                'pdf' => $this->pdf_path ? url("/api/v1/documents/{$this->id}/pdf") : null,
                'xml_path' => $this->xml_path,
                'cdr_path' => $this->cdr_path,
                'pdf_path' => $this->pdf_path,
            ],
            'leyenda' => $this->leyenda,
            'observacion' => $this->observacion,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

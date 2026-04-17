<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo_documento' => '07',
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'numero_completo' => $this->numero_completo,
            'cod_local' => $this->cod_local,
            'fecha_emision' => $this->fecha_emision->format('Y-m-d H:i:s'),
            'tipo_moneda' => $this->tipo_moneda,
            'cliente' => array_filter([
                'tipo_doc' => $this->client_tipo_doc,
                'num_doc' => $this->client_num_doc,
                'razon_social' => $this->client_razon_social,
                'direccion' => $this->client_direccion,
                'email' => $this->client?->email,
                'telefono' => $this->client?->telefono,
            ], fn ($v) => $v !== null),
            'doc_afectado' => [
                'tipo' => $this->doc_afectado_tipo,
                'serie' => $this->doc_afectado_serie,
                'correlativo' => $this->doc_afectado_correlativo,
                'motivo_codigo' => $this->cod_motivo,
                'motivo_descripcion' => $this->des_motivo,
            ],
            'totales' => array_filter([
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
            ], fn ($v, $k) => $v > 0 || in_array($k, ['total_impuestos', 'valor_venta', 'sub_total', 'total']), ARRAY_FILTER_USE_BOTH),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => array_filter([
                'codigo' => $item->codigo,
                'descripcion' => $item->descripcion,
                'unidad' => $item->unidad,
                'cantidad' => (float) $item->cantidad,
                'precio_unitario' => (float) $item->mto_precio_unitario,
                'valor_unitario' => (float) $item->mto_valor_unitario,
                'igv' => (float) $item->igv,
                'descuento' => (float) $item->descuento,
                'total' => (float) $item->mto_valor_venta,
            ], fn ($v, $k) => ! ($k === 'descuento' && $v == 0), ARRAY_FILTER_USE_BOTH))),
            'guias' => $this->when($this->guias, $this->guias),
            'sunat' => [
                'estado' => $this->sunat_status,
                'codigo' => $this->sunat_code,
                'descripcion' => $this->sunat_description,
                'notas' => $this->sunat_notes,
                'hash_cpe' => $this->hash_cpe,
            ],
            'archivos' => [
                'xml' => $this->xml_path ? url("/api/v1/notas-credito/{$this->id}/xml") : null,
                'cdr' => $this->cdr_path ? url("/api/v1/notas-credito/{$this->id}/cdr") : null,
                'pdf' => $this->pdf_path ? url("/api/v1/notas-credito/{$this->id}/pdf") : null,
            ],
            'leyenda' => $this->leyenda,
            'observacion' => $this->observacion,
            'enviado_en' => $this->sent_at?->toIso8601String(),
            'creado_en' => $this->created_at->toIso8601String(),
        ];
    }
}

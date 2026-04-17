<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InternalDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tipoDoc = $this->getTipoDocumento();
        $routePrefix = $this->type === 'quotation' ? 'cotizaciones' : 'notas-venta';

        return [
            'id' => $this->id,
            'tipo' => $this->type,
            'tipo_documento' => $tipoDoc,
            'numero' => $this->numero,
            'fecha_emision' => $this->fecha_emision->format('Y-m-d H:i:s'),
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'),
            'tipo_moneda' => $this->tipo_moneda,
            'forma_pago' => $this->when($this->forma_pago, $this->forma_pago),
            'cliente' => array_filter([
                'tipo_doc' => $this->client_tipo_doc,
                'num_doc' => $this->client_num_doc,
                'razon_social' => $this->client_razon_social,
                'direccion' => $this->client_direccion,
                'email' => $this->client?->email,
                'telefono' => $this->client?->telefono,
            ], fn ($v) => $v !== null),
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
            'estado' => $this->status,
            'observacion' => $this->observacion,
            'estado_pago' => $this->when($this->type === 'sale_note', $this->payment_status),
            'monto_pagado' => $this->when($this->type === 'sale_note', (float) $this->monto_pagado),
            'pagos' => $this->when($this->type === 'sale_note', fn () => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'metodo' => $p->metodo,
                'monto' => (float) $p->monto,
                'referencia' => $p->referencia,
                'monto_recibido' => $p->monto_recibido ? (float) $p->monto_recibido : null,
                'vuelto' => $p->metodo === 'efectivo' && $p->monto_recibido
                    ? round((float) $p->monto_recibido - (float) $p->monto, 2) : null,
                'notas' => $p->notas,
                'creado_en' => $p->created_at->toIso8601String(),
            ]))),
            'archivos' => [
                'pdf' => url("/api/v1/{$routePrefix}/{$this->id}/pdf"),
            ],
            'creado_en' => $this->created_at->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoletaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo_documento' => '03',
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'numero_completo' => $this->numero_completo,
            'cod_local' => $this->cod_local,
            'fecha_emision' => $this->fecha_emision->format('Y-m-d H:i:s'),
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'),
            'tipo_operacion' => $this->tipo_operacion,
            'tipo_moneda' => $this->tipo_moneda,
            'forma_pago' => $this->forma_pago,
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
                'exportacion' => (float) $this->mto_oper_exportacion,
                'gratuitas' => (float) $this->mto_oper_gratuitas,
                'igv' => (float) $this->mto_igv,
                'base_ivap' => (float) $this->mto_base_ivap,
                'ivap' => (float) $this->mto_ivap,
                'isc' => (float) $this->mto_isc,
                'icbper' => (float) $this->mto_icbper,
                'total_impuestos' => (float) $this->total_impuestos,
                'valor_venta' => (float) $this->valor_venta,
                'sub_total' => (float) $this->sub_total,
                'total' => (float) $this->mto_imp_venta,
            ], fn ($v, $k) => $v > 0 || in_array($k, ['total_impuestos', 'valor_venta', 'sub_total', 'total']), ARRAY_FILTER_USE_BOTH),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(function ($item) {
                $precioUnitario = (float) $item->mto_precio_unitario;
                $cantidad = (float) $item->cantidad;
                $valorVenta = (float) $item->mto_valor_venta;
                $igv = (float) $item->igv;
                $descuentoBase = (float) $item->descuento;
                $descuentoConIgv = $descuentoBase > 0
                    ? round($precioUnitario * $cantidad - ($valorVenta + $igv), 2)
                    : 0;

                return array_filter([
                    'codigo' => $item->codigo,
                    'descripcion' => $item->descripcion,
                    'unidad' => $item->unidad,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'valor_unitario' => (float) $item->mto_valor_unitario,
                    'igv' => $igv,
                    'descuento' => $descuentoConIgv,
                    'total' => round($valorVenta + $igv, 2),
                ], fn ($v, $k) => ! ($k === 'descuento' && $v == 0), ARRAY_FILTER_USE_BOTH);
            })),
            'cuotas' => $this->when($this->cuotas, $this->cuotas),
            'detraccion' => $this->when($this->detraccion, $this->detraccion),
            'percepcion' => $this->when($this->percepcion, $this->percepcion),
            'anticipos' => $this->when($this->anticipos, $this->anticipos),
            'descuentos_globales' => $this->when($this->descuentos_globales, $this->descuentos_globales),
            'guias' => $this->when($this->guias, $this->guias),
            'sunat' => [
                'status' => $this->sunat_status,
                'code' => $this->sunat_code,
                'description' => $this->sunat_description,
                'notes' => $this->sunat_notes,
                'hash_cpe' => $this->hash_cpe,
            ],
            'archivos' => [
                'xml' => $this->xml_path ? url("/api/v1/boletas/{$this->id}/xml") : null,
                'cdr' => $this->cdr_path ? url("/api/v1/boletas/{$this->id}/cdr") : null,
                'pdf' => $this->pdf_path ? url("/api/v1/boletas/{$this->id}/pdf") : null,
            ],
            'leyenda' => $this->leyenda,
            'observacion' => $this->observacion,
            'payment_status' => $this->payment_status,
            'monto_pagado' => (float) $this->monto_pagado,
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'metodo' => $p->metodo,
                'monto' => (float) $p->monto,
                'referencia' => $p->referencia,
                'monto_recibido' => $p->monto_recibido ? (float) $p->monto_recibido : null,
                'vuelto' => $p->metodo === 'efectivo' && $p->monto_recibido
                    ? round((float) $p->monto_recibido - (float) $p->monto, 2) : null,
                'notas' => $p->notas,
                'created_at' => $p->created_at->toIso8601String(),
            ])),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

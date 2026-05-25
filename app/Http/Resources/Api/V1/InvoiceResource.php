<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo_documento' => '01',
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
                $icbper = (float) ($item->icbper ?? 0);
                $isc = (float) ($item->isc ?? 0);
                $descuentoBase = (float) $item->descuento;

                $descuentosItem = null;
                if (! empty($item->descuentos)) {
                    $descuentosItem = collect($item->descuentos)->map(fn ($d) => [
                        'cod_tipo'   => $d['cod_tipo'] ?? '00',
                        'monto_base' => (float) ($d['monto_base'] ?? 0),
                        'factor'     => (float) ($d['factor'] ?? 0),
                        'monto'      => (float) ($d['monto'] ?? 0),
                    ])->values()->all();
                }

                return array_filter([
                    'codigo'          => $item->codigo,
                    'descripcion'     => $item->descripcion,
                    'unidad'          => $item->unidad,
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'valor_unitario'  => (float) $item->mto_valor_unitario,
                    'igv'             => $igv,
                    'isc'             => $isc,
                    'icbper'          => $icbper,
                    'descuento'       => $descuentoBase,
                    'descuentos'      => $descuentosItem,
                    'total'           => round($valorVenta + $igv + $icbper + $isc, 2),
                ], fn ($v, $k) => ! (in_array($k, ['descuento', 'isc', 'icbper', 'descuentos'], true) && ($v == 0 || $v === null)), ARRAY_FILTER_USE_BOTH);
            })),
            'detraccion' => $this->when($this->detraccion, $this->detraccion),
            'percepcion' => $this->when($this->percepcion, $this->percepcion),
            'anticipos' => $this->when($this->anticipos, $this->anticipos),
            'cuotas' => $this->when($this->cuotas, $this->cuotas),
            'descuentos_globales' => $this->when(
                ! empty($this->descuentos_globales),
                fn () => collect($this->descuentos_globales)->map(fn ($d) => [
                    'cod_tipo'   => $d['cod_tipo'] ?? '02',
                    'monto_base' => (float) ($d['monto_base'] ?? 0),
                    'factor'     => (float) ($d['factor'] ?? 0),
                    'monto'      => (float) ($d['monto'] ?? 0),
                ])->values()
            ),
            'guias' => $this->when($this->guias, $this->guias),
            'extras' => $this->when($this->extras, $this->extras),
            'sunat' => [
                'estado' => $this->sunat_status,
                'codigo' => $this->sunat_code,
                'descripcion' => $this->sunat_description,
                'notas' => $this->sunat_notes,
                'hash_cpe' => $this->hash_cpe,
            ],
            'archivos' => [
                'xml' => $this->xml_path ? url("/api/v1/facturas/{$this->id}/xml") : null,
                'cdr' => $this->cdr_path ? url("/api/v1/facturas/{$this->id}/cdr") : null,
                'pdf' => $this->pdf_path ? url("/api/v1/facturas/{$this->id}/pdf") : null,
            ],
            'leyenda' => $this->leyenda,
            'observacion' => $this->observacion,
            'estado_pago' => $this->payment_status,
            'monto_pagado' => (float) $this->monto_pagado,
            'pagos' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'metodo' => $p->metodo,
                'monto' => (float) $p->monto,
                'referencia' => $p->referencia,
                'monto_recibido' => $p->monto_recibido ? (float) $p->monto_recibido : null,
                'vuelto' => $p->metodo === 'efectivo' && $p->monto_recibido
                    ? round((float) $p->monto_recibido - (float) $p->monto, 2) : null,
                'notas' => $p->notas,
                'creado_en' => $p->created_at->toIso8601String(),
            ])),
            'enviado_en' => $this->sent_at?->toIso8601String(),
            'creado_en' => $this->created_at->toIso8601String(),
        ];
    }
}

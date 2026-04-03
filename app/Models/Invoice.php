<?php

namespace App\Models;

use App\Contracts\Documentable;
use App\Models\Traits\HasDocumentFields;
use App\Models\Traits\HasPayments;
use App\Models\Traits\HasSunatIntegration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model implements Documentable
{
    use HasDocumentFields, HasPayments, HasSunatIntegration;

    protected $fillable = [
        'tenant_id', 'client_id', 'sucursal_id', 'serie', 'correlativo', 'cod_local',
        'fecha_emision', 'fecha_vencimiento', 'tipo_operacion', 'tipo_moneda',
        'forma_pago', 'client_tipo_doc', 'client_num_doc', 'client_razon_social',
        'client_direccion', 'mto_oper_gravadas', 'mto_oper_exoneradas',
        'mto_oper_inafectas', 'mto_oper_exportacion', 'mto_oper_gratuitas',
        'mto_igv', 'mto_base_ivap', 'mto_ivap', 'mto_isc', 'mto_icbper',
        'total_impuestos', 'valor_venta', 'sub_total', 'mto_imp_venta',
        'total_anticipos', 'total_descuentos', 'leyenda', 'observacion',
        'cuotas', 'detraccion', 'percepcion', 'anticipos',
        'descuentos_globales', 'guias', 'extras',
        'xml_path', 'cdr_path', 'pdf_path', 'hash_cpe',
        'sunat_status', 'sunat_code', 'sunat_description', 'sunat_notes',
        'ticket', 'sent_at',
        'payment_status', 'monto_pagado',
    ];

    protected function casts(): array
    {
        return array_merge($this->sharedCasts(), [
            'mto_oper_exportacion' => 'decimal:2',
            'mto_base_ivap' => 'decimal:2',
            'mto_ivap' => 'decimal:2',
            'cuotas' => 'array',
            'detraccion' => 'array',
            'percepcion' => 'array',
            'anticipos' => 'array',
            'descuentos_globales' => 'array',
            'guias' => 'array',
            'extras' => 'array',
        ]);
    }

    public function getTipoDocumento(): string
    {
        return '01';
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}

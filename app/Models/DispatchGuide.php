<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DispatchGuide extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'sucursal_id',
        'document_id',
        'tipo_documento',
        'serie',
        'correlativo',
        'fecha_emision',
        'destinatario_tipo_doc',
        'destinatario_num_doc',
        'destinatario_razon_social',
        'cod_traslado',
        'mod_traslado',
        'fecha_traslado',
        'fecha_entrega_transportista',
        'peso_total',
        'und_peso_total',
        'num_bultos',
        'llegada_ubigeo',
        'llegada_direccion',
        'llegada_ruc',
        'llegada_cod_local',
        'partida_ubigeo',
        'partida_direccion',
        'partida_ruc',
        'partida_cod_local',
        'transportista',
        'vehiculo',
        'conductor',
        'indicadores',
        'remitente',
        'doc_relacionado',
        'datos_subcontratador',
        'datos_pagador_flete',
        'autorizacion_especial',
        'observacion',
        'items',
        'cod_local',
        'xml_content',
        'cdr_content',
        'xml_path',
        'cdr_path',
        'pdf_path',
        'hash_cpe',
        'sunat_status',
        'sunat_code',
        'sunat_description',
        'ticket',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'fecha_traslado' => 'date',
            'fecha_entrega_transportista' => 'date',
            'correlativo' => 'integer',
            'peso_total' => 'decimal:3',
            'num_bultos' => 'integer',
            'transportista' => 'array',
            'vehiculo' => 'array',
            'conductor' => 'array',
            'indicadores' => 'array',
            'remitente' => 'array',
            'doc_relacionado' => 'array',
            'datos_subcontratador' => 'array',
            'datos_pagador_flete' => 'array',
            'autorizacion_especial' => 'array',
            'items' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function getNumeroCompletoAttribute(): string
    {
        return $this->serie . '-' . str_pad((string) $this->correlativo, 6, '0', STR_PAD_LEFT);
    }
}
